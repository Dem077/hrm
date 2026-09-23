<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportTemplateRequest;
use App\Http\Requests\UpdateReportTemplateRequest;
use App\Models\ReportTemplate;
use App\Services\ReportGenerator;
use App\Support\ReportFieldCatalog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportTemplateController extends Controller
{
    public function show(ReportTemplate $reportTemplate): Response
    {
        $user = request()->user();

        abort_unless($this->canViewTemplate($user, $reportTemplate), 403);

        $reportTemplate->load('createdBy:id,name,email');

        return Inertia::render('Reports/Show', [
            'template' => $reportTemplate->toPresentationArray(),
            'modelLabel' => ReportFieldCatalog::modelLabel(
                (string) (($reportTemplate->definition['model_type'] ?? '')),
            ),
        ]);
    }

    public function download(ReportTemplate $reportTemplate, ReportGenerator $generator): StreamedResponse
    {
        $user = request()->user();

        abort_unless($this->canViewTemplate($user, $reportTemplate), 403);

        return $generator->download($reportTemplate);
    }

    public function manage(): Response
    {
        return Inertia::render('Reports/Manage', [
            'templates' => ReportTemplate::query()
                ->with('createdBy:id,name,email')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (ReportTemplate $template) => $template->toPresentationArray()),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Reports/Form', [
            'template' => null,
            'catalog' => ReportFieldCatalog::inertiaCatalog(),
            'emptyTemplate' => $this->emptyTemplate(),
        ]);
    }

    public function edit(ReportTemplate $reportTemplate): Response
    {
        $reportTemplate->load('createdBy:id,name,email');

        return Inertia::render('Reports/Form', [
            'template' => $reportTemplate->toPresentationArray(),
            'catalog' => ReportFieldCatalog::inertiaCatalog(),
            'emptyTemplate' => $this->emptyTemplate(),
        ]);
    }

    public function store(StoreReportTemplateRequest $request): RedirectResponse
    {
        ReportTemplate::query()->create([
            ...$request->templateAttributes(),
            'created_by_user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('reports.manage')
            ->with('success', 'Report template created successfully.');
    }

    public function update(UpdateReportTemplateRequest $request, ReportTemplate $reportTemplate): RedirectResponse
    {
        $reportTemplate->update($request->templateAttributes());

        return redirect()
            ->route('reports.manage')
            ->with('success', 'Report template updated successfully.');
    }

    public function destroy(ReportTemplate $reportTemplate): RedirectResponse
    {
        $reportTemplate->delete();

        return back()->with('success', 'Report template deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyTemplate(): array
    {
        return [
            'id' => null,
            'name' => '',
            'code' => '',
            'description' => '',
            'is_global' => false,
            'is_active' => true,
            'sort_order' => 0,
            'definition' => [
                'model_type' => array_key_first(ReportFieldCatalog::MODELS) ?: '',
                'field_configs' => [],
                'from_date' => null,
                'to_date' => null,
            ],
        ];
    }

    protected function canViewTemplate(?\App\Models\User $user, ReportTemplate $template): bool
    {
        if (! $user || ! $template->is_active) {
            return false;
        }

        return $template->is_global || $template->created_by_user_id === $user->id;
    }
}
