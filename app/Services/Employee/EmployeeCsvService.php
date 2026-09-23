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
use App\Support\EmployeeUnset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeCsvService
{
    public const UNSET = EmployeeUnset::VALUE;

    public const DEFAULT_PASSWORD = 'Agro@1234';

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
            fputcsv($handle, self::HEADERS, ',', '"', '\\');

            foreach ($this->sampleRows() as $row) {
                fputcsv($handle, $row, ',', '"', '\\');
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
        $this->prepareLongRunningImport();

        $validatedRows = $this->validateAllRows($file);

        $previewRows = [];

        foreach ($validatedRows as $row) {
            $previewRows[] = [
                'line' => $row['line'],
                'staff_id' => $row['staff_id'],
                'name' => $row['name'],
                'national_id' => $row['national_id'],
                'email' => $row['email'],
                'mobile_number' => $row['mobile_number'],
                'emergency_contact_number' => $row['emergency_contact_number'],
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
        $this->prepareLongRunningImport();

        $validatedRows = $this->validateAllRows($file);

        $created = 0;

        DB::transaction(function () use ($validatedRows, &$created): void {
            $createdByStaffId = [];

            foreach ($validatedRows as $row) {
                $user = User::query()->create([
                    'name' => $row['name'],
                    'email' => $row['user_email'],
                    'password' => self::DEFAULT_PASSWORD,
                    'must_change_password' => true,
                ]);

                $employee = Employee::query()->create([
                    'staff_id' => $row['staff_id'],
                    'name' => $row['name'],
                    'national_id' => $row['national_id'],
                    'email' => $row['email'],
                    'mobile_number' => $row['mobile_number'],
                    'emergency_contact_number' => $row['emergency_contact_number'],
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

    protected function prepareLongRunningImport(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '0');
            @ini_set('memory_limit', '512M');
        }

        DB::disableQueryLog();
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

        $existingStaffIds = Employee::query()
            ->pluck('staff_id')
            ->mapWithKeys(fn ($id) => [mb_strtolower((string) $id) => true])
            ->all();
        $existingNationalIds = Employee::query()
            ->pluck('national_id')
            ->mapWithKeys(fn ($id) => [mb_strtolower((string) $id) => true])
            ->all();
        $existingEmployeeEmails = Employee::query()
            ->whereNotNull('email')
            ->pluck('email')
            ->mapWithKeys(fn ($email) => [mb_strtolower((string) $email) => true])
            ->all();
        $existingUserEmails = User::query()
            ->pluck('email')
            ->mapWithKeys(fn ($email) => [mb_strtolower((string) $email) => true])
            ->all();

        $seenStaffIds = [];
        $seenNationalIds = [];
        $seenEmails = [];
        $seenUserEmails = [];
        $validated = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $validated[] = $this->validateRow(
                $row,
                $line,
                $activeBankCodes,
                $nationalities,
                $gradeLabels,
                $existingStaffIds,
                $existingNationalIds,
                $existingEmployeeEmails,
                $existingUserEmails,
                $seenStaffIds,
                $seenNationalIds,
                $seenEmails,
                $seenUserEmails,
            );
        }

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

            if (! isset($existingStaffIds[$managerKey])) {
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
     * @param  array<string, true>  $existingStaffIds
     * @param  array<string, true>  $existingNationalIds
     * @param  array<string, true>  $existingEmployeeEmails
     * @param  array<string, true>  $existingUserEmails
     * @param  array<string, true>  $seenStaffIds
     * @param  array<string, true>  $seenNationalIds
     * @param  array<string, true>  $seenEmails
     * @param  array<string, true>  $seenUserEmails
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    protected function validateRow(
        array $row,
        int $line,
        array $activeBankCodes,
        array $nationalities,
        array $gradeLabels,
        array $existingStaffIds,
        array $existingNationalIds,
        array $existingEmployeeEmails,
        array $existingUserEmails,
        array &$seenStaffIds,
        array &$seenNationalIds,
        array &$seenEmails,
        array &$seenUserEmails,
    ): array {
        $staffId = $this->rawValue($row['staff_id'] ?? '');
        $name = $this->rawValue($row['name'] ?? '');

        if ($staffId === null) {
            throw ValidationException::withMessages(['file' => "Row {$line}: staff_id is required."]);
        }

        if ($name === null) {
            throw ValidationException::withMessages(['file' => "Row {$line}: name is required."]);
        }

        $nationalIdRaw = $this->rawValue($row['national_id'] ?? '');
        $nationalId = $nationalIdRaw ?? (self::UNSET.'-'.$staffId);

        $emailRaw = $this->rawValue($row['email'] ?? '');
        $emailIsValid = $emailRaw !== null && filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
        $employeeEmail = $emailIsValid ? mb_strtolower($emailRaw) : self::UNSET;
        $userEmail = $emailIsValid
            ? mb_strtolower($emailRaw)
            : 'unset.'.Str::slug($staffId, '').'@import.local';

        $contacts = $this->parseContactNumbers($row['mobile_number'] ?? '');
        $mobileNumber = $contacts['mobile'] ?? self::UNSET;
        $emergencyContactNumber = $contacts['secondary'];

        $joinedDate = $this->parseOptionalDate($row['joined_date'] ?? '', $line);

        $genderRaw = mb_strtolower((string) ($this->rawValue($row['gender'] ?? '') ?? ''));
        $gender = $genderRaw === '' ? Gender::Other : Gender::tryFrom($genderRaw);
        if (! $gender) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: gender must be one of: male, female, other (or blank/NULL for Unset → other).",
            ]);
        }

        $bankNameRaw = $this->rawValue($row['bank_name'] ?? '');
        $bankName = $bankNameRaw === null ? self::UNSET : mb_strtoupper($bankNameRaw);
        if ($bankName !== self::UNSET && ! in_array($bankName, $activeBankCodes, true)) {
            $allowed = implode(', ', $activeBankCodes) ?: '(none configured)';
            throw ValidationException::withMessages([
                'file' => "Row {$line}: bank_name must be an active bank code ({$allowed}), blank/NULL, or Unset.",
            ]);
        }

        $accountName = $this->unsettableString($row['account_name'] ?? '');
        $accountNo = $this->unsettableString($row['account_no'] ?? '');

        $staffKey = mb_strtolower($staffId);
        $nationalKey = mb_strtolower($nationalId);
        $emailKey = mb_strtolower($employeeEmail);
        $userEmailKey = mb_strtolower($userEmail);

        if (isset($seenStaffIds[$staffKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: duplicate staff_id \"{$staffId}\" in the CSV."]);
        }

        if (isset($seenNationalIds[$nationalKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: duplicate national_id \"{$nationalId}\" in the CSV."]);
        }

        if ($employeeEmail !== self::UNSET && isset($seenEmails[$emailKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: duplicate email \"{$employeeEmail}\" in the CSV."]);
        }

        if (isset($seenUserEmails[$userEmailKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: duplicate login email \"{$userEmail}\" in the CSV."]);
        }

        if (isset($existingStaffIds[$staffKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: staff_id \"{$staffId}\" already exists."]);
        }

        if (isset($existingNationalIds[$nationalKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: national_id \"{$nationalId}\" already exists."]);
        }

        if (
            $employeeEmail !== self::UNSET
            && (isset($existingEmployeeEmails[$emailKey]) || isset($existingUserEmails[$emailKey]))
        ) {
            throw ValidationException::withMessages(['file' => "Row {$line}: email \"{$employeeEmail}\" already exists."]);
        }

        if (isset($existingUserEmails[$userEmailKey])) {
            throw ValidationException::withMessages(['file' => "Row {$line}: login email \"{$userEmail}\" already exists."]);
        }

        $employmentRaw = mb_strtolower((string) ($this->rawValue($row['employment_type'] ?? '') ?? ''));
        $employmentType = $employmentRaw === '' ? null : EmploymentType::tryFrom($employmentRaw);
        if ($employmentRaw !== '' && ! $employmentType) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: employment_type must be one of: permanent, contract, probation, temporary, intern.",
            ]);
        }

        $dutyRaw = mb_strtolower((string) ($this->rawValue($row['duty_type'] ?? '') ?? ''));
        $dutyType = $dutyRaw === '' ? DutyType::Normal : DutyType::tryFrom($dutyRaw);
        if (! $dutyType) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: duty_type must be one of: normal, shift.",
            ]);
        }

        $gradeIdRaw = $this->rawValue($row['grade_id'] ?? '');
        $gradeId = null;
        $gradeLabel = null;
        if ($gradeIdRaw !== null) {
            if (! ctype_digit($gradeIdRaw)) {
                throw ValidationException::withMessages(['file' => "Row {$line}: grade_id must be a numeric designation id."]);
            }

            $gradeId = (int) $gradeIdRaw;
            if (! isset($gradeLabels[$gradeId])) {
                throw ValidationException::withMessages(['file' => "Row {$line}: grade_id {$gradeId} was not found (or is inactive)."]);
            }

            $gradeLabel = $gradeLabels[$gradeId];
        }

        $nationalityRaw = $this->rawValue($row['nationality'] ?? '');
        $nationality = $nationalityRaw === null ? self::UNSET : $nationalityRaw;
        if ($nationality !== self::UNSET && ! in_array(mb_strtolower($nationality), $nationalities, true)) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: nationality \"{$nationality}\" was not found in active nationalities.",
            ]);
        }

        $personalEmail = $this->optionalEmailOrUnset($row['personal_email'] ?? '', $line, 'personal_email');
        $officeEmail = $this->optionalEmailOrUnset($row['office_email'] ?? '', $line, 'office_email');
        $workLocation = $this->unsettableString($row['work_location'] ?? '');

        $managerStaffId = $this->rawValue($row['manager_staff_id'] ?? '');

        $seenStaffIds[$staffKey] = true;
        $seenNationalIds[$nationalKey] = true;
        if ($employeeEmail !== self::UNSET) {
            $seenEmails[$emailKey] = true;
        }
        $seenUserEmails[$userEmailKey] = true;

        return [
            'line' => $line,
            'staff_id' => $staffId,
            'name' => $name,
            'national_id' => $nationalId,
            'email' => $employeeEmail,
            'user_email' => $userEmail,
            'mobile_number' => $mobileNumber,
            'emergency_contact_number' => $emergencyContactNumber,
            'joined_date' => $joinedDate,
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
            'nationality' => $nationality,
            'work_location' => $workLocation,
            'personal_email' => $personalEmail,
            'office_email' => $officeEmail,
        ];
    }

    /**
     * Blank or the literal "NULL" (any case) counts as missing.
     */
    protected function rawValue(mixed $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '' || strcasecmp($raw, 'NULL') === 0) {
            return null;
        }

        return $raw;
    }

    protected function unsettableString(mixed $value): string
    {
        return $this->rawValue($value) ?? self::UNSET;
    }

    /**
     * @return array{mobile: ?string, secondary: ?string}
     */
    protected function parseContactNumbers(mixed $value): array
    {
        $raw = $this->rawValue($value);

        if ($raw === null) {
            return [
                'mobile' => null,
                'secondary' => self::UNSET,
            ];
        }

        $parts = preg_split('/\s*\/\s*/', $raw) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn (string $part) => $part !== ''));

        $mobile = $parts[0] ?? null;
        $secondary = $parts[1] ?? null;

        return [
            'mobile' => $mobile,
            'secondary' => $secondary ?? self::UNSET,
        ];
    }

    protected function optionalEmailOrUnset(mixed $value, int $line, string $field): string
    {
        $raw = $this->rawValue($value);

        if ($raw === null) {
            return self::UNSET;
        }

        if (! filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: {$field} is invalid (use a valid email, blank, or NULL).",
            ]);
        }

        return $raw;
    }

    protected function parseOptionalDate(mixed $value, int $line): ?string
    {
        $raw = $this->rawValue($value);

        if ($raw === null) {
            return null;
        }

        $timestamp = strtotime($raw);

        if ($timestamp === false) {
            throw ValidationException::withMessages([
                'file' => "Row {$line}: joined_date must be a valid date, blank, or NULL.",
            ]);
        }

        return date('Y-m-d', $timestamp);
    }

    protected function parseBoolean(mixed $value, bool $default, int $line, string $field): bool
    {
        $raw = $this->rawValue($value);

        if ($raw === null) {
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
                '7900001/7900002',
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
                'NULL',
                'NULL',
                'NULL',
                'NULL',
                '',
                'contract',
                'normal',
                'yes',
                'yes',
                '',
                'EMP001',
                '',
                '',
                '',
                '',
                '',
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

        $header = fgetcsv($handle, null, ',', '"', '\\');

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

        while (($data = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
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
