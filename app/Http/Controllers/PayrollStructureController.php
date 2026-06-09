<?php

namespace App\Http\Controllers;

use App\Services\Payroll\PayrollStructureService;
use Inertia\Inertia;
use Inertia\Response;

class PayrollStructureController extends Controller
{
    public function __construct(
        protected PayrollStructureService $payrollStructureService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('PayrollStructure/Index', $this->payrollStructureService->indexPayload());
    }
}
