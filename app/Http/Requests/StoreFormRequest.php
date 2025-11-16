<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', Rule::in(['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'file', 'number', 'date'])],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.validation_rules' => ['nullable', 'array'],
            'fields.*.options' => ['nullable', 'array', 'required_if:fields.*.type,select'],
            'smtp_profile_id' => ['nullable', 'exists:smtp_profiles,id'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
