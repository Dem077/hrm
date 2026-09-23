<?php

namespace App\Http\Requests;

use App\Models\ReportTemplate;
use Illuminate\Validation\Rule;

class UpdateReportTemplateRequest extends StoreReportTemplateRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ReportTemplate $template */
        $template = $this->route('report_template');

        return [
            ...parent::rules(),
            'code' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('report_templates', 'code')->ignore($template->id),
            ],
        ];
    }
}
