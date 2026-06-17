<?php

namespace App\Http\Requests\Concerns;

use App\Services\Attendance\DutyRosterScopeService;
use Illuminate\Validation\Validator;

trait ValidatesDutyRosterEmployeeAccess
{
    protected function validateDutyRosterEmployeeAccess(Validator $validator, int $employeeId): void
    {
        $scope = new DutyRosterScopeService($this->user());

        if (! $scope->employeeIsAccessible($employeeId)) {
            $validator->errors()->add('employee_id', 'You can only assign duty roster entries to staff in your department.');
        }
    }

    /**
     * @param  list<int>  $employeeIds
     */
    protected function validateDutyRosterEmployeesAccess(Validator $validator, array $employeeIds): void
    {
        $scope = new DutyRosterScopeService($this->user());
        $inaccessibleIds = $scope->inaccessibleEmployeeIds($employeeIds);

        if ($inaccessibleIds !== []) {
            $validator->errors()->add(
                'employee_ids',
                'You can only assign duty roster entries to staff in your department.',
            );
        }
    }
}
