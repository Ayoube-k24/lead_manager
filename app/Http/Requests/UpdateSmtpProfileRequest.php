<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSmtpProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'host' => ['sometimes', 'required', 'string', 'max:255'],
            'port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['sometimes', 'required', 'string', Rule::in(['tls', 'ssl', 'none'])],
            'username' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string'],
            'from_address' => ['sometimes', 'required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
