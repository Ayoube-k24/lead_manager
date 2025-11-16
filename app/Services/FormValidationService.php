<?php

namespace App\Services;

use App\Models\Form;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FormValidationService
{
    /**
     * Validate form data against form field definitions.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Form $form, array $data): array
    {
        $rules = [];
        $messages = [];

        foreach ($form->fields ?? [] as $field) {
            $fieldName = $field['name'] ?? null;
            if (! $fieldName) {
                continue;
            }

            $fieldRules = $this->buildValidationRules($field);
            if (! empty($fieldRules)) {
                $rules[$fieldName] = $fieldRules;
            }

            // Build custom messages
            $fieldMessages = $this->buildValidationMessages($field);
            if (! empty($fieldMessages)) {
                $messages = array_merge($messages, $fieldMessages);
            }
        }

        return Validator::make($data, $rules, $messages)->validate();
    }

    /**
     * Build validation rules for a field.
     *
     * @param  array<string, mixed>  $field
     * @return array<string>
     */
    protected function buildValidationRules(array $field): array
    {
        $rules = [];

        // Required rule
        if ($field['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type-specific rules
        $type = $field['type'] ?? 'text';
        match ($type) {
            'email' => $rules[] = 'email',
            'tel' => $rules[] = 'string',
            'number' => $rules[] = 'numeric',
            'date' => $rules[] = 'date',
            'file' => $rules[] = 'file',
            'checkbox' => $rules[] = 'boolean',
            'select' => $rules[] = 'string',
            default => $rules[] = 'string',
        };

        // Custom validation rules from field definition
        $customRules = $field['validation_rules'] ?? [];
        if (is_array($customRules)) {
            foreach ($customRules as $rule => $value) {
                match ($rule) {
                    'min' => $rules[] = "min:{$value}",
                    'max' => $rules[] = "max:{$value}",
                    'min_length' => $rules[] = "min:{$value}",
                    'max_length' => $rules[] = "max:{$value}",
                    'regex' => $rules[] = "regex:{$value}",
                    'in' => $rules[] = is_array($value) ? 'in:'.implode(',', $value) : "in:{$value}",
                    default => null,
                };
            }
        }

        // Additional type-specific validations
        if ($type === 'email') {
            $rules[] = 'max:255';
        }

        if ($type === 'tel') {
            // Common phone validation
            if (! isset($customRules['regex'])) {
                $rules[] = 'regex:/^[+]?[(]?[0-9]{1,4}[)]?[-\s.]?[(]?[0-9]{1,4}[)]?[-\s.]?[0-9]{1,9}$/';
            }
        }

        if ($type === 'select') {
            $options = $field['options'] ?? [];
            if (! empty($options)) {
                $rules[] = 'in:'.implode(',', array_filter($options));
            }
        }

        return $rules;
    }

    /**
     * Build custom validation messages for a field.
     *
     * @param  array<string, mixed>  $field
     * @return array<string, string>
     */
    protected function buildValidationMessages(array $field): array
    {
        $messages = [];
        $fieldName = $field['name'] ?? '';
        $fieldLabel = $field['label'] ?? $fieldName;

        $messages["{$fieldName}.required"] = "Le champ {$fieldLabel} est obligatoire.";
        $messages["{$fieldName}.email"] = "Le champ {$fieldLabel} doit être une adresse email valide.";
        $messages["{$fieldName}.numeric"] = "Le champ {$fieldLabel} doit être un nombre.";
        $messages["{$fieldName}.date"] = "Le champ {$fieldLabel} doit être une date valide.";
        $messages["{$fieldName}.file"] = "Le champ {$fieldLabel} doit être un fichier.";
        $messages["{$fieldName}.boolean"] = "Le champ {$fieldLabel} doit être coché ou non.";
        $messages["{$fieldName}.regex"] = "Le format du champ {$fieldLabel} est invalide.";
        $messages["{$fieldName}.in"] = "La valeur du champ {$fieldLabel} n'est pas valide.";

        return $messages;
    }
}
