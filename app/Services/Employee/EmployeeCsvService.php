<?php

namespace App\Services\Employee;

use App\Enums\DutyType;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\ZktDevicePrivilege;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\Nationality;
use App\Models\StructureGrade;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeCsvService
{
    public const HEADERS = [
        'staff_id',
        'name',
        'national_id',
        'email',
        'mobile_number',
        'joined_date',
        'gender',
        'employment_type',
        'duty_type',
        'works_saturday',
        'is_active',
        'grade_id',
        'manager_staff_id',
        'bank_name',
        'account_name',
        'account_no',
        'nationality',
        'work_location',
        'personal_email',
        'office_email',
    ];

    public function downloadSample(): StreamedResponse
    {
        $filename = 'employees-import-template.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens the file correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADERS);

            foreach ($this->sampleRows() as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, summary: array{rows: int, create: int}}
     */
    public function preview(UploadedFile $file): array
    {
        $validatedRows = $this->validateAllRows($file);

        $previewRows = [];

        foreach ($validatedRows as $row) {
            $previewRows[] = [
                'line' => $row['line'],
                'staff_id' => $row['staff_id'],
                'name' => $row['name'],
                'national_id' => $row['national_id'],
                'email' => $row['email'],
                'joined_date' => $row['joined_date'],
                'gender' => $row['gender']->value,
                'employment_type' => $row['employment_type']?->value,
                'duty_type' => $row['duty_type']->value,
                'grade_id' => $row['grade_id'],
                'grade_label' => $row['grade_label'],
                'manager_staff_id' => $row['manager_staff_id'],
                'bank_name' => $row['bank_name'],
                'account_name' => $row['account_name'],
                'account_no' => $row['account_no'],
                'action' => 'create',
            ];
        }

        return [
            'rows' => $previewRows,
            'summary' => [
                'rows' => count($previewRows),
                'create' => count($previewRows),
            ],
        ];
    }

    /**
     * @return array{created: int, rows: int}
     */
    public function import(UploadedFile $file): array
    {
        $validatedRows = $this->validateAllRows($file);

        $created = 0;

        DB::transaction(function () use ($validatedRows, &$created): void {
            $createdByStaffId = [];

            foreach ($validatedRows as $row) {
                $password = Str::password(12);

                $user = User::query()->create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'password' => $password,
                ]);

                $employee = Employee::query()->create([
                    'staff_id' => $row['staff_id'],
                    'name' => $row['name'],
                    'national_id' => $row['national_id'],
                    'email' => $row['email'],
                    'mobile_number' => $row['mobile_number'],
                    'joined_date' => $row['joined_date'],
                    'gender' => $row['gender'],
                    'employment_type' => $row['employment_type'],
                    'duty_type' => $row['duty_type'],
                    'works_saturday' => $row['works_saturday'],
                    'is_active' => $row['is_active'],
                    'uses_custom_duty_times' => false,
                    'grade_id' => $row['grade_id'],
                    'device_privilege' => ZktDevicePrivilege::Employee,
                    'bank_name' => $row['bank_name'],
                    'account_name' => $row['account_name'],
                    'account_no' => $row['account_no'],
                    'nationality' => $row['nationality'],
                    'work_location' => $row['work_location'],
                    'personal_email' => $row['personal_email'],
                    'office_email' => $row['office_email'],
                    'user_id' => $user->id,
                ]);

                $createdByStaffId[mb_strtolower($employee->staff_id)] = $employee;
                $created++;
            }

            foreach ($validatedRows as $row) {
                if ($row['manager_staff_id'] === null) {
                    continue;
                }

                $employee = $createdByStaffId[mb_strtolower($row['staff_id'])] ?? null;
                $managerKey = mb_strtolower($row['manager_staff_id']);
                $manager = $createdByStaffId[$managerKey]
                    ?? Employee::query()->whereRaw('LOWER(staff_id) = ?', [$managerKey])->first();

                if ($employee && $manager && (int) $employee->id !== (int) $manager->id) {
                    $employee->update(['manager_id' => $manager->id]);
                }
            }
        });

        return [
            'created' => $created,
            'rows' => count($validatedRows),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function validateAllRows(UploadedFile $file): array
    {
        $rows = $this->requireDataRows($file);

        $activeBankCodes = Bank::query()->active()->pluck('code')->map(fn ($code) => mb_strtoupper((string) $code))->all();
        $nationalities = Nationality::query()->active()->pluck('name')->map(fn ($name) => mb_strtolower((string) $name))->all();
        $gradeLabels = StructureGrade::query()
            ->where('is_active', true)
            ->with(['level.node', 'level.group'])
            ->get()
            ->keyBy('id')
            ->map(fn (StructureGrade $grade) => $grade->label())
            ->all();

        $seenStaffIds = [];
        $seenNationalIds = [];
        $seenEmails = [];
        $validated = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $validated[] = $this->validateRow(
                $row,
                $line,
                $activeBankCodes,
                $nationalities,
                $gradeLabels,
                $seenStaffIds,
                $seenNationalIds,
                $seenEmails,
            );
        }

        // Resolve manager references after all staff_ids in the file are known.
        foreach ($validated as $row) {
            if ($row['manager_staff_id'] === null) {
                continue;
            }

            $managerKey = mb_strtolower($row['manager_staff_id']);

            if (isset($seenStaffIds[$managerKey])) {
                if ($managerKey === mb_strtolower($row['staff_id'])) {
                    throw ValidationException::withMessages([
                        'file' => "Row {$row['line']}: manager_staff_id cannot be the same as staff_id.",
                    ]);
                }

                continue;
            }

            if (! Employee::query()->whereRaw('LOWER(staff_id) = ?', [$managerKey])->exists()) {
                throw ValidationException::withMessages([
                    'file' => "Row {$row['line']}: manager_staff_id \"{$row['manager_staff_id']}\" was not found in the CSV or existing employees.",
                ]);
            }
        }

        return $validated;
    }

    /**
     * @param  list<string>  $activeBankCodes
     * @param  list<string>  $nationalities
     * @param  array<int, string>  $gradeLabels
     * @param  array<string, true>  $seenStaffIds
     * @param  array<string, true>  $seenNationalIds
     * @param  array<string, true>  $seenEmails
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    protected function validateRow(
        array $row,
        int $line,
        array $activeBankCodes,
        array $nationalities,
        array $gradeLabels,
        array &$seenStaffIds,
        array &$seenNationalIds,
        array &$seenEmails,
    ): array {
        $staffId = trim($row['staff_id'] ?? '');
        $name = trim($row['name'] ?? '');
        $nationalId = trim($row['national_id'] ?? '');
        $email = mb_strtolower(trim($row['email'] ?? ''));
        $joinedDate = trim($row['joined_date'] ?? '');
        $genderRaw = mb_strtolower(trim($row['gender'] ?? ''));
        $bankName = mb_strtoupper(trim($row['bank_name'] ?? ''));
        $accountName = trim($row['account_name'] ?? '');
        $accountNo = trim($row['account_no'] ?? '');

        if ($staffId === '') {
            throw ValidationException::withMessages(['file' => "Row {$line}: staff_id is required."]);
        }

        if ($name === '') {
            throw ValidationException::withMessages(['file' => "Row {$line}: name is required."]);
        }

        if ($nationalId === '') {
            throw ValidationException::withMessages(['file' => "Row {$line}: national_id is required."]);
        }

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['file' => "Row {$line}: a valid email is required."]);
        }

        if ($joinedDate === '' || strtotime($joinedDate) === false) {
            throw ValidationException::withMessages(['file' => "Row {$line}: joined_date must be a valid date (YYYY-MM-DD)."]);
        }

        $gender = Gender::tryFrom($genderRaw);
        if (! $gender) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: gender must be one of: male, female, other.",
            ]);
        }

        if ($bankName === '' || ! in_array($bankName, $activeBankCodes, true)) {
            $allowed = implode(', ', $activeBankCodes) ?: '(none configured)';
            throw ValidationException::withMessages([
                'file' => "Row {$line}: bank_name must be an active bank code ({$allowed}).",
            ]);
        }

        if ($accountName === '') {
            throw ValidationException::withMessages(['file' => "Row {$line}: account_name is required."]);
        }

        if ($accountNo === '') {
            throw ValidationException::withMessages(['file' => "Row {$line}: account_no is required."]);
        }

        $staffKey = mb_strtolower($staffId);
        $nationalKey = mb_strtolower($nationalId);

        if (isset($seenStaffIds[$staffKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: duplicate staff_id \"{$staffId}\" in the CSV."]);
        }

        if (isset($seenNationalIds[$nationalKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: duplicate national_id \"{$nationalId}\" in the CSV."]);
        }

        if (isset($seenEmails[$email])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: duplicate email \"{$email}\" in the CSV."]);
        }

        if (Employee::query()->whereRaw('LOWER(staff_id) = ?', [$staffKey])->exists()) {
            throw ValidationException::withMessages(['file' => "Row {$line}: staff_id \"{$staffId}\" already exists."]);
        }

        if (Employee::query()->whereRaw('LOWER(national_id) = ?', [$nationalKey])->exists()) {
            throw ValidationException::withMessages(['file' => "Row {$line}: national_id \"{$nationalId}\" already exists."]);
        }

        if (
            Employee::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
            || User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()
        ) {
            throw ValidationException::withMessages(['file' => "Row {$line}: email \"{$email}\" already exists."]);
        }

        $employmentRaw = mb_strtolower(trim($row['employment_type'] ?? ''));
        $employmentType = $employmentRaw === '' ? null : EmploymentType::tryFrom($employmentRaw);
        if ($employmentRaw !== '' && ! $employmentType) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: employment_type must be one of: permanent, contract, probation, temporary, intern.",
            ]);
        }

        $dutyRaw = mb_strtolower(trim($row['duty_type'] ?? ''));
        $dutyType = $dutyRaw === '' ? DutyType::Normal : DutyType::tryFrom($dutyRaw);
        if (! $dutyType) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: duty_type must be one of: normal, shift.",
            ]);
        }

        $gradeIdRaw = trim($row['grade_id'] ?? '');
        $gradeId = null;
        $gradeLabel = null;
        if ($gradeIdRaw !== '') {
            if (! ctype_digit($gradeIdRaw)) {
                throw ValidationException::withMessages(['file' => "Row {$line}: grade_id must be a numeric designation id."]);
            }

            $gradeId = (int) $gradeIdRaw;
            if (! isset($gradeLabels[$gradeId])) {
                throw ValidationException::withMessages(['file' => "Row {$line}: grade_id {$gradeId} was not found (or is inactive)."]);
            }

            $gradeLabel = $gradeLabels[$gradeId];
        }

        $nationality = trim($row['nationality'] ?? '');
        if ($nationality !== '' && ! in_array(mb_strtolower($nationality), $nationalities, true)) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: nationality \"{$nationality}\" was not found in active nationalities.",
            ]);
        }

        $personalEmail = trim($row['personal_email'] ?? '');
        if ($personalEmail !== '' && ! filter_var($personalEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['file' => "Row {$line}: personal_email is invalid."]);
        }

        $officeEmail = trim($row['office_email'] ?? '');
        if ($officeEmail !== '' && ! filter_var($officeEmail, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['file' => "Row {$line}: office_email is invalid."]);
        }

        $managerStaffId = trim($row['manager_staff_id'] ?? '');
        $managerStaffId = $managerStaffId === '' ? null : $managerStaffId;

        $seenStaffIds[$staffKey] = true;
        $seenNationalIds[$nationalKey] = true;
        $seenEmails[$email] = true;

        return [
            'line' => $line,
            'staff_id' => $staffId,
            'name' => $name,
            'national_id' => $nationalId,
            'email' => $email,
            'mobile_number' => trim($row['mobile_number'] ?? '') ?: null,
            'joined_date' => date('Y-m-d', strtotime($joinedDate)),
            'gender' => $gender,
            'employment_type' => $employmentType,
            'duty_type' => $dutyType,
            'works_saturday' => $this->parseBoolean($row['works_saturday'] ?? '', false, $line, 'works_saturday'),
            'is_active' => $this->parseBoolean($row['is_active'] ?? '', true, $line, 'is_active'),
            'grade_id' => $gradeId,
            'grade_label' => $gradeLabel,
            'manager_staff_id' => $managerStaffId,
            'bank_name' => $bankName,
            'account_name' => $accountName,
            'account_no' => $accountNo,
            'nationality' => $nationality !== '' ? $nationality : null,
            'work_location' => trim($row['work_location'] ?? '') ?: null,
            'personal_email' => $personalEmail !== '' ? $personalEmail : null,
            'office_email' => $officeEmail !== '' ? $officeEmail : null,
        ];
    }

    protected function parseBoolean(mixed $value, bool $default, int $line, string $field): bool
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return $default;
        }

        $normalized = mb_strtolower($raw);

        if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
            return false;
        }

        throw ValidationException::withMessages([
            'file' => "Row {$line}: {$field} must be yes/no, true/false, or 1/0.",
        ]);
    }

    /**
     * @return list<array<string, string>>
     */
    protected function requireDataRows(UploadedFile $file): array
    {
        $rows = $this->parseCsv($file);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file has no data rows. Add at least one employee after the header.',
            ]);
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    protected function sampleRows(): array
    {
        $defaultBank = Bank::defaultCode() ?? 'BML';
        $today = now()->toDateString();

        return [
            [
                'EMP001',
                'Aisha Mohamed',
                'A1234567',
                'aisha.mohamed@example.com',
                '7900001',
                $today,
                'female',
                'permanent',
                'normal',
                'no',
                'yes',
                '',
                '',
                $defaultBank,
                'Aisha Mohamed',
                '7700000001',
                'Maldivian',
                "Male' Head Office",
                'aisha.personal@example.com',
                'aisha.mohamed@example.com',
            ],
            [
                'EMP002',
                'Hassan Ali',
                'A1234568',
                'hassan.ali@example.com',
                '7900002',
                $today,
                'male',
                'contract',
                'normal',
                'yes',
                'yes',
                '',
                'EMP001',
                $defaultBank,
                'Hassan Ali',
                '7700000002',
                'Maldivian',
                "Male' Head Office",
                '',
                '',
            ],
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    protected function parseCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded CSV file.',
            ]);
        }

        $header = fgetcsv($handle);

        if (! is_array($header)) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => 'The CSV file is empty.',
            ]);
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);

        if ($header !== [] && str_starts_with($header[0], "\xEF\xBB\xBF")) {
            $header[0] = substr($header[0], 3);
        }

        foreach (self::HEADERS as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => "Missing required CSV column: {$required}. Download the template CSV for the correct format.",
                ]);
            }
        }

        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->rowIsEmpty($data)) {
                continue;
            }

            $row = [];

            foreach ($header as $index => $column) {
                $row[$column] = isset($data[$index]) ? trim((string) $data[$index]) : '';
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  list<mixed>|false  $data
     */
    protected function rowIsEmpty(array|false $data): bool
    {
        if ($data === false) {
            return true;
        }

        foreach ($data as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
