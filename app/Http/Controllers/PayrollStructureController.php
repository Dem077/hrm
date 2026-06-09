<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDesignationRequest;
use App\Http\Requests\StorePayrollComponentRequest;
use App\Http\Requests\UpdateDesignationRequest;
use App\Http\Requests\UpdatePayrollComponentRequest;
use App\Models\Designation;
use App\Models\PayrollComponent;
use App\Services\Payroll\PayrollStructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollStructureController extends Controller
{
    public function __construct(
        protected PayrollStructureService $payrollStructureService,
    ) {}

    public function index(): Response
    {
        $components = PayrollComponent::query()
            ->withCount('designations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (PayrollComponent $component) => $component->toPresentationArray());

        $designations = Designation::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Designation $designation) => $this->payrollStructureService->formatDesignation($designation));

        return Inertia::render('PayrollStructure/Index', [
            'components' => $components,
            'designations' => $designations,
            'emptyComponent' => $this->emptyComponent(),
            'emptyDesignation' => $this->emptyDesignation(),
            'defaultDesignationItems' => $this->payrollStructureService->defaultItemsForNewDesignation(),
        ]);
    }

    public function storeComponent(StorePayrollComponentRequest $request): RedirectResponse
    {
        $component = DB::transaction(function () use ($request) {
            $component = PayrollComponent::query()->create($request->validated());
            $this->payrollStructureService->attachMandatoryComponentToAllDesignations($component);

            return $component;
        });

        if ($component->is_mandatory) {
            return back()->with('success', 'Payroll component created and added to all designations.');
        }

        return back()->with('success', 'Payroll component created successfully.');
    }

    public function updateComponent(UpdatePayrollComponentRequest $request, PayrollComponent $payrollComponent): RedirectResponse
    {
        if ($payrollComponent->isSystemMandatory()) {
            return back()->with('error', 'Basic Salary cannot be edited.');
        }

        $wasMandatory = $payrollComponent->is_mandatory;

        DB::transaction(function () use ($request, $payrollComponent, $wasMandatory): void {
            $payrollComponent->update($request->validated());

            if (! $wasMandatory && $payrollComponent->is_mandatory) {
                $this->payrollStructureService->attachMandatoryComponentToAllDesignations($payrollComponent);
            }
        });

        return back()->with('success', 'Payroll component updated successfully.');
    }

    public function destroyComponent(PayrollComponent $payrollComponent): RedirectResponse
    {
        if ($payrollComponent->isSystemMandatory()) {
            return back()->with('error', 'Basic Salary cannot be deleted.');
        }

        if ($payrollComponent->is_mandatory) {
            return back()->with('error', 'Mandatory payroll components cannot be deleted. Remove the mandatory flag first or deactivate the component.');
        }

        if ($payrollComponent->designations()->exists()) {
            return back()->with('error', 'This component is assigned to designations and cannot be deleted. Deactivate it instead.');
        }

        $payrollComponent->delete();

        return back()->with('success', 'Payroll component deleted successfully.');
    }

    public function storeDesignation(StoreDesignationRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $designation = Designation::query()->create($request->safe()->only([
                'name',
                'code',
                'description',
                'sort_order',
                'is_active',
            ]));

            $this->payrollStructureService->syncDesignationPayrollItems(
                $designation,
                $request->validated('items'),
            );
        });

        return back()->with('success', 'Designation created successfully.');
    }

    public function updateDesignation(UpdateDesignationRequest $request, Designation $designation): RedirectResponse
    {
        DB::transaction(function () use ($request, $designation): void {
            $designation->update($request->safe()->only([
                'name',
                'code',
                'description',
                'sort_order',
                'is_active',
            ]));

            $this->payrollStructureService->syncDesignationPayrollItems(
                $designation,
                $request->validated('items'),
            );
        });

        return back()->with('success', 'Designation updated successfully.');
    }

    public function destroyDesignation(Designation $designation): RedirectResponse
    {
        $designation->delete();

        return back()->with('success', 'Designation deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyComponent(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'type' => 'addition',
            'is_mandatory' => false,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyDesignation(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'sort_order' => 0,
            'is_active' => true,
            'items' => [],
        ];
    }
}
