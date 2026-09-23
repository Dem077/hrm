<?php

namespace App\Exceptions;

use App\Models\Employee;
use App\Support\EmployeeUnset;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmployeeUnsetFieldsException extends Exception
{
    /**
     * @param  list<array{employee: Employee, fields: list<string>}>  $incomplete
     * @param  list<string>  $requiredFields
     */
    public function __construct(
        public readonly array $incomplete,
        public readonly array $requiredFields = [],
        public readonly string $context = 'this action',
    ) {
        parent::__construct($this->buildMessage());
    }

    /**
     * @param  Collection<int, Employee>|iterable<int, Employee>  $employees
     * @param  list<string>  $fields
     */
    public static function throwIfIncomplete(
        iterable $employees,
        array $fields,
        string $context = 'this action',
    ): void {
        $incomplete = EmployeeUnset::findIncomplete($employees, $fields);

        if ($incomplete === []) {
            return;
        }

        throw new self($incomplete, $fields, $context);
    }

    public function buildMessage(): string
    {
        $fieldLabels = collect($this->incomplete)
            ->flatMap(fn (array $row) => $row['fields'])
            ->unique()
            ->map(fn (string $field) => EmployeeUnset::labelFor($field))
            ->values()
            ->all();

        $fieldList = $this->joinList($fieldLabels);
        $count = count($this->incomplete);

        if ($count === 1) {
            /** @var Employee $employee */
            $employee = $this->incomplete[0]['employee'];
            $employeeFields = collect($this->incomplete[0]['fields'])
                ->map(fn (string $field) => EmployeeUnset::labelFor($field))
                ->all();

            return sprintf(
                'Employee "%s" (%s) is missing %s. Update their profile before %s.',
                $employee->name,
                $employee->staff_id,
                $this->joinList($employeeFields),
                $this->context,
            );
        }

        $names = collect($this->incomplete)
            ->take(5)
            ->map(fn (array $row) => sprintf('%s (%s)', $row['employee']->name, $row['employee']->staff_id))
            ->all();

        $more = $count > 5 ? ' and '.($count - 5).' more' : '';

        return sprintf(
            '%d employees have incomplete profiles (%s%s). Update these fields before %s: %s.',
            $count,
            implode(', ', $names),
            $more,
            $this->context,
            $fieldList,
        );
    }

    /**
     * @return array{
     *     message: string,
     *     context: string,
     *     fields: list<string>,
     *     field_labels: list<string>,
     *     employees: list<array{id: int, staff_id: string, name: string, edit_url: string, fields: list<string>, field_labels: list<string>}>
     * }
     */
    public function toFlashPayload(): array
    {
        return [
            'message' => $this->getMessage(),
            'context' => $this->context,
            'fields' => collect($this->incomplete)->flatMap(fn (array $row) => $row['fields'])->unique()->values()->all(),
            'field_labels' => collect($this->incomplete)
                ->flatMap(fn (array $row) => $row['fields'])
                ->unique()
                ->map(fn (string $field) => EmployeeUnset::labelFor($field))
                ->values()
                ->all(),
            'employees' => collect($this->incomplete)
                ->map(fn (array $row) => [
                    'id' => $row['employee']->id,
                    'staff_id' => $row['employee']->staff_id,
                    'name' => $row['employee']->name,
                    'edit_url' => '/employees/'.$row['employee']->id.'/edit',
                    'fields' => $row['fields'],
                    'field_labels' => collect($row['fields'])
                        ->map(fn (string $field) => EmployeeUnset::labelFor($field))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    public function render(Request $request): Response
    {
        $payload = $this->toFlashPayload();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => $payload['message'],
                'employee_unset' => $payload,
            ], 422);
        }

        return redirect()
            ->back()
            ->with('error', $payload['message'])
            ->with('employee_unset', $payload);
    }

    /**
     * @param  list<string>  $items
     */
    protected function joinList(array $items): string
    {
        $items = array_values($items);

        if ($items === []) {
            return 'required information';
        }

        if (count($items) === 1) {
            return $items[0];
        }

        if (count($items) === 2) {
            return $items[0].' and '.$items[1];
        }

        $last = array_pop($items);

        return implode(', ', $items).', and '.$last;
    }
}
