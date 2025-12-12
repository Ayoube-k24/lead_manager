<?php

namespace App\Services;

use App\Models\Form;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
            'date', 'datetime' => $rules[] = 'date',
            'file' => $rules[] = 'file',
            'checkbox', 'consent' => $rules[] = 'boolean',
            'multiselect', 'checkboxlist' => $rules[] = 'array',
            'select', 'radiolist' => $rules[] = 'string',
            'url' => $rules[] = 'url',
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
                    'regex' => $rules[] = is_string($value) ? $this->normalizeRegexRule($value) : '',
                    'in' => $rules[] = is_array($value) ? 'in:'.implode(',', $value) : "in:{$value}",
                    default => null,
                };
            }
        }

        // Check content_regex if validation_rules.regex is not set
        if (! isset($customRules['regex']) && ! empty($field['content_regex'] ?? null) && is_string($field['content_regex'])) {
            $normalizedRegex = $this->normalizeRegexRule($field['content_regex']);
            if (! empty($normalizedRegex)) {
                $rules[] = $normalizedRegex;
            }
        }

        // Additional type-specific validations
        if ($type === 'email') {
            $rules[] = 'max:255';
        }

        if ($type === 'tel') {
            // Common phone validation
            if (! isset($customRules['regex']) && empty($field['content_regex'] ?? null)) {
                $rules[] = 'regex:/^[+]?[(]?[0-9]{1,4}[)]?[-\s.]?[(]?[0-9]{1,4}[)]?[-\s.]?[0-9]{1,9}$/';
            }
        }

        if (in_array($type, ['select', 'multiselect', 'radiolist', 'checkboxlist'])) {
            $options = $field['options'] ?? [];
            if (! empty($options)) {
                // Extraire les valeurs des options (structure label-value)
                $optionValues = [];
                foreach ($options as $option) {
                    if (is_array($option) && isset($option['value'])) {
                        $optionValues[] = $option['value'];
                    } elseif (is_string($option)) {
                        // Compatibilité avec l'ancienne structure
                        $optionValues[] = $option;
                    }
                }
                
                if (! empty($optionValues)) {
                    // Pour les types array (multiselect, checkboxlist), utiliser Rule::in() pour valider chaque élément
                    if ($type === 'multiselect' || $type === 'checkboxlist') {
                        $rules[] = Rule::in(array_filter($optionValues));
                    } else {
                        $rules[] = 'in:'.implode(',', array_filter($optionValues));
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * Normalize regex pattern for Laravel validation.
     *
     * @param  string  $pattern
     * @return string
     */
    protected function normalizeRegexRule(string $pattern): string
    {
        if (empty($pattern)) {
            return '';
        }

        // Remove leading/trailing whitespace
        $pattern = trim($pattern);

        // Check if pattern already has delimiters (e.g., /pattern/, #pattern#, ~pattern~)
        $hasDelimiters = preg_match('/^([\/#~@%`])(.*)\1[imsxADSUXJu]*$/', $pattern);

        if ($hasDelimiters) {
            // Pattern already has delimiters, use as is
            return "regex:{$pattern}";
        }

        // Add delimiters if missing
        // Escape forward slashes in the pattern to avoid conflicts
        $escapedPattern = str_replace('/', '\/', $pattern);

        return "regex:/{$escapedPattern}/";
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
        $messages["{$fieldName}.url"] = "Le champ {$fieldLabel} doit être une URL valide.";
        $messages["{$fieldName}.array"] = "Le champ {$fieldLabel} doit être un tableau.";

        return $messages;
    }
}
