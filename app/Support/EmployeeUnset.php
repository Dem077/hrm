<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Collection;

class EmployeeUnset
{
    public const VALUE = 'Unset';

    /**
     * Field keys that may be stored as Unset after CSV import (plus nullable joined_date).
     *
     * @var array<string, string>
     */
    public const FIELD_LABELS = [
        'national_id' => 'National ID',
        'email' => 'Email',
        'mobile_number' => 'Mobile number',
        'emergency_contact_number' => 'Emergency contact number',
        'joined_date' => 'Joined date',
        'bank_name' => 'Bank',
        'account_name' => 'Account name',
        'account_no' => 'Account number',
        'nationality' => 'Nationality',
        'work_location' => 'Work location',
        'personal_email' => 'Personal email',
        'office_email' => 'Office email',
    ];

    /**
     * Fields required before payroll processing / bank transfers.
     *
     * @var list<string>
     */
    public const PAYROLL_FIELDS = [
        'national_id',
        'bank_name',
        'account_name',
        'account_no',
    ];

    public static function isUnset(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (! is_string($value) && ! is_numeric($value)) {
            return false;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return true;
        }

        if (strcasecmp($normalized, self::VALUE) === 0) {
            return true;
        }

        // Import placeholder for unique national IDs: Unset-{staff_id}
        return str_starts_with(mb_strtolower($normalized), mb_strtolower(self::VALUE).'-');
    }

    public static function labelFor(string $field): string
    {
        return self::FIELD_LABELS[$field] ?? str_replace('_', ' ', ucfirst($field));
    }

    /**
     * @param  list<string>|null  $fields
     * @return list<string>
     */
    public static function missingFields(Employee $employee, ?array $fields = null): array
    {
        $fields ??= array_keys(self::FIELD_LABELS);
        $missing = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, self::FIELD_LABELS) && ! in_array($field, $fields, true)) {
                continue;
            }

            $value = $employee->{$field} ?? null;

            if (self::isUnset($value)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param  Collection<int, Employee>|iterable<int, Employee>  $employees
     * @param  list<string>  $fields
     * @return list<array{employee: Employee, fields: list<string>}>
     */
    public static function findIncomplete(iterable $employees, array $fields): array
    {
        $incomplete = [];

        foreach ($employees as $employee) {
            $missing = self::missingFields($employee, $fields);

            if ($missing !== []) {
                $incomplete[] = [
                    'employee' => $employee,
                    'fields' => $missing,
                ];
            }
        }

        return $incomplete;
    }
}
