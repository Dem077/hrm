<?php

namespace App\Http\Requests;

use App\Services\AppBrandingService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAppSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $hexColor = ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'];

        return [
            'app_name' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'brand_color_400' => $hexColor,
            'brand_color_500' => $hexColor,
            'brand_color_600' => $hexColor,
            'brand_color_700' => $hexColor,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'remove_logo' => $this->boolean('remove_logo'),
            'tagline' => $this->input('tagline') ?: null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function colorValues(): array
    {
        return [
            'brand_color_400' => (string) $this->input('brand_color_400'),
            'brand_color_500' => (string) $this->input('brand_color_500'),
            'brand_color_600' => (string) $this->input('brand_color_600'),
            'brand_color_700' => (string) $this->input('brand_color_700'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function defaultColors(): array
    {
        return app(AppBrandingService::class)->defaultColors();
    }
}
