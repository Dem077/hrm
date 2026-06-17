<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Services\Attendance\PayrollPeriodService;
use App\Services\Payroll\PayrollProcessingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(Request $request, PayrollProcessingService $payrollProcessingService, PayrollPeriodService $payrollPeriodService): Response
    {
        $periodOffset = max(0, $request->integer('period_offset', 0));
        $departmentId = $request->filled('department_id') ? $request->integer('department_id') : null;
        $report = $payrollProcessingService->build($periodOffset, $departmentId);

        return Inertia::render('Payroll/Index', [
            'rows' => $report['rows'],
            'period' => $report['period'],
            'periodOptions' => $payrollPeriodService->presentation()['recent'],
            'departments' => Department::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'period_offset' => $periodOffset,
                'department_id' => $departmentId,
            ],
        ]);
    }

    public function export(Request $request, PayrollProcessingService $payrollProcessingService): StreamedResponse
    {
        $periodOffset = max(0, $request->integer('period_offset', 0));
        $departmentId = $request->filled('department_id') ? $request->integer('department_id') : null;

        return $payrollProcessingService->downloadExcel($periodOffset, $departmentId);
    }
}
