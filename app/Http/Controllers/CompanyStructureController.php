<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportCompanyStructureRequest;
use App\Services\CompanyStructure\CompanyStructureCsvService;
use App\Services\CompanyStructure\CompanyStructureService;
use App\Services\Leave\LeaveApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyStructureController extends Controller
{
    private const IMPORT_SESSION_KEY = 'company_structure_import';

    public function __construct(
        protected CompanyStructureService $companyStructureService,
        protected CompanyStructureCsvService $companyStructureCsvService,
        protected LeaveApprovalWorkflowService $leaveApprovalWorkflowService,
    ) {}

    public function index(Request $request): Response
    {
        $pending = $request->session()->get(self::IMPORT_SESSION_KEY);

        return Inertia::render('CompanyStructure/Index', [
            'groups' => $this->companyStructureService->treePayload(),
            'approvalTemplates' => $this->leaveApprovalWorkflowService->templateOptions(),
            'importPreview' => is_array($pending) ? ($pending['preview'] ?? null) : null,
            'importFileName' => is_array($pending) ? ($pending['original_name'] ?? null) : null,
        ]);
    }

    public function chart(): Response
    {
        return Inertia::render('CompanyStructure/Chart', [
            'groups' => $this->companyStructureService->treePayload(),
            'approvalTemplates' => $this->leaveApprovalWorkflowService->templateOptions(),
        ]);
    }

    public function downloadSample(): StreamedResponse
    {
        return $this->companyStructureCsvService->downloadSample();
    }

    public function previewImport(ImportCompanyStructureRequest $request): RedirectResponse
    {
        $this->clearPendingImport($request);

        $file = $request->file('file');
        $preview = $this->companyStructureCsvService->preview($file);
        $path = $file->store('company-structure-imports');

        $request->session()->put(self::IMPORT_SESSION_KEY, [
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'preview' => $preview,
        ]);

        return back();
    }

    public function import(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::IMPORT_SESSION_KEY);

        if (! is_array($pending) || empty($pending['path']) || ! Storage::disk('local')->exists($pending['path'])) {
            return back()->with('error', 'No import file ready to confirm. Upload a CSV again.');
        }

        $absolutePath = Storage::disk('local')->path($pending['path']);
        $uploaded = new UploadedFile(
            $absolutePath,
            $pending['original_name'] ?? 'company-structure.csv',
            'text/csv',
            null,
            true,
        );

        $stats = $this->companyStructureCsvService->import($uploaded);
        $this->clearPendingImport($request);

        return redirect()
            ->route('company-structure.index')
            ->with(
                'success',
                sprintf(
                    'Imported %d CSV row(s): %d nodes created, %d updated; %d levels created, %d updated; %d grades created, %d updated.',
                    $stats['rows'],
                    $stats['created_nodes'],
                    $stats['updated_nodes'],
                    $stats['created_levels'],
                    $stats['updated_levels'],
                    $stats['created_grades'],
                    $stats['updated_grades'],
                ),
            );
    }

    public function cancelImport(Request $request): RedirectResponse
    {
        $this->clearPendingImport($request);

        return back();
    }

    protected function clearPendingImport(Request $request): void
    {
        $pending = $request->session()->get(self::IMPORT_SESSION_KEY);

        if (is_array($pending) && ! empty($pending['path'])) {
            Storage::disk('local')->delete($pending['path']);
        }

        $request->session()->forget(self::IMPORT_SESSION_KEY);
    }
}
