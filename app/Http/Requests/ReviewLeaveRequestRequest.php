<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('leave-requests.approve') ?? false)
            || ($user?->can('leave-requests.approve-hr') ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
