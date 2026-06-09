<?php

namespace App\Services\Payroll;

use App\Models\PayrollComponent;
use Illuminate\Support\Facades\DB;

class PayrollComponentService
{
    public function __construct(
        protected DesignationPayrollService $designationPayrollService,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listForIndex(): array
    {
        return PayrollComponent::query()
            ->withCount('designations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PayrollComponent $component) => $component->toPresentationArray())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PayrollComponent
    {
        return DB::transaction(function () use ($data) {
            $component = PayrollComponent::query()->create($data);
            $this->designationPayrollService->attachMandatoryComponentToAllDesignations($component);

            return $component;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PayrollComponent $component, array $data): ?string
    {
        if ($component->isSystemMandatory()) {
            return 'Basic Salary cannot be edited.';
        }

        $wasMandatory = $component->is_mandatory;

        DB::transaction(function () use ($component, $data, $wasMandatory): void {
            $component->update($data);

            if (! $wasMandatory && $component->is_mandatory) {
                $this->designationPayrollService->attachMandatoryComponentToAllDesignations($component);
            }
        });

        return null;
    }

    public function delete(PayrollComponent $component): ?string
    {
        if ($component->isSystemMandatory()) {
            return 'Basic Salary cannot be deleted.';
        }

        if ($component->is_mandatory) {
            return 'Mandatory payroll components cannot be deleted. Remove the mandatory flag first or deactivate the component.';
        }

        if ($component->designations()->exists()) {
            return 'This component is assigned to designations and cannot be deleted. Deactivate it instead.';
        }

        $component->delete();

        return null;
    }

    public function createSuccessMessage(PayrollComponent $component): string
    {
        if ($component->is_mandatory) {
            return 'Payroll component created and added to all designations.';
        }

        return 'Payroll component created successfully.';
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyAttributes(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'type' => 'addition',
            'calculation_method' => 'fixed',
            'is_mandatory' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
