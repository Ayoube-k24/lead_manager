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
            'fields.*.tag' => ['nullable', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', Rule::in(['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'file', 'number', 'date', 'datetime', 'url', 'multiselect', 'radiolist', 'checkboxlist', 'consent'])],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.visibility' => ['nullable', 'string', Rule::in(['visible', 'hidden'])],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'fields.*.help_text' => ['nullable', 'string', 'max:500'],
            'fields.*.default_value' => ['nullable'],
            'fields.*.description' => ['nullable', 'string', 'max:1000'],
            'fields.*.content_regex' => ['nullable', 'string', 'max:500'],
            'fields.*.validation_rules' => ['nullable', 'array'],
            'fields.*.options' => ['nullable', 'array', 'required_if:fields.*.type,select', 'required_if:fields.*.type,multiselect', 'required_if:fields.*.type,radiolist', 'required_if:fields.*.type,checkboxlist'],
            'smtp_profile_id' => ['required', 'exists:smtp_profiles,id'],
            'email_template_id' => ['required', 'exists:email_templates,id'],
            'call_center_id' => ['required', 'exists:call_centers,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
