<?php

use App\Http\Requests\UpdateFormRequest;
use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\SmtpProfile;
use Livewire\Volt\Component;

new class extends Component
{
    public Form $form;

    public string $name = '';

    public ?string $description = null;

    public array $fields = [];

    public ?int $smtp_profile_id = null;

    public ?int $email_template_id = null;

    public ?int $call_center_id = null;

    public bool $is_active = true;

    /**
     * Get the required custom fields that should be present in all forms.
     */
    protected function getRequiredCustomFields(): array
    {
        return [];
    }

    /**
     * Get the optional source field template.
     */
    protected function getSourceFieldTemplate(): array
    {
        return [
            'name' => 'SOURCE',
            'tag' => 'SOURCE',
            'type' => 'text',
            'label' => 'Source de trafic',
            'placeholder' => '',
            'required' => '0',
            'visibility' => 'hidden',
            'sort_order' => 1,
            'help_text' => '',
            'default_value' => '',
            'description' => '',
            'validation_rules' => [
                'min_length' => 1,
                'max_length' => 255,
            ],
            'content_regex' => '',
            'options' => [],
        ];
    }

    /**
     * Ensure all required custom fields are present in the form.
     * (No longer used - fields are now optional)
     */
    protected function ensureRequiredCustomFields(): void
    {
        // No longer automatically adding required fields - they are optional now
    }

    public function mount(Form $form): void
    {
        $this->form = $form;
        $this->name = $form->name;
        $this->description = $form->description;
        $this->fields = $form->fields ?? [];
        $this->smtp_profile_id = $form->smtp_profile_id;
        $this->email_template_id = $form->email_template_id;
        $this->call_center_id = $form->call_center_id;
        $this->is_active = $form->is_active;

        // Types de champs valides
        $validTypes = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'file', 'number', 'date', 'datetime', 'url', 'multiselect', 'radiolist', 'checkboxlist', 'consent'];

        // Migrer les anciens champs vers le nouveau format
        foreach ($this->fields as $index => $field) {
            // Valider et corriger le type de champ
            if (! isset($field['type']) || ! in_array($field['type'], $validTypes, true)) {
                $this->fields[$index]['type'] = 'text'; // Type par défaut si invalide
            }
            if (! isset($field['tag'])) {
                $this->fields[$index]['tag'] = $field['name'] ?? '';
            }
            if (! isset($field['visibility'])) {
                $this->fields[$index]['visibility'] = 'visible';
            }
            if (! isset($field['sort_order'])) {
                $this->fields[$index]['sort_order'] = $index + 1;
            }
            if (! isset($field['help_text'])) {
                $this->fields[$index]['help_text'] = '';
            }
            if (! isset($field['default_value'])) {
                $this->fields[$index]['default_value'] = '';
            }
            if (! isset($field['description'])) {
                $this->fields[$index]['description'] = '';
            }
            if (! isset($field['content_regex'])) {
                $this->fields[$index]['content_regex'] = '';
            }
            if (! isset($field['validation_rules']['min_length'])) {
                $this->fields[$index]['validation_rules']['min_length'] = 1;
            }
            if (! isset($field['validation_rules']['max_length'])) {
                $this->fields[$index]['validation_rules']['max_length'] = 255;
            }
            // Convertir required boolean en string pour le select
            if (isset($field['required']) && is_bool($field['required'])) {
                $this->fields[$index]['required'] = $field['required'] ? '1' : '0';
            } elseif (! isset($field['required'])) {
                $this->fields[$index]['required'] = '0';
            }
            // Migrer les options vers la structure label-value
            if (isset($field['options']) && is_array($field['options'])) {
                $migratedOptions = [];
                foreach ($field['options'] as $option) {
                    if (is_string($option)) {
                        // Ancienne structure : juste une string
                        $migratedOptions[] = [
                            'label' => $option,
                            'value' => $option,
                        ];
                    } elseif (is_array($option) && ! isset($option['label'])) {
                        // Structure intermédiaire sans label
                        $migratedOptions[] = [
                            'label' => $option['value'] ?? $option[0] ?? '',
                            'value' => $option['value'] ?? $option[0] ?? '',
                        ];
                    } else {
                        // Déjà dans le bon format
                        $migratedOptions[] = [
                            'label' => $option['label'] ?? '',
                            'value' => $option['value'] ?? '',
                        ];
                    }
                }
                $this->fields[$index]['options'] = $migratedOptions;
            }
        }

        if (empty($this->fields)) {
            $this->fields = [
                [
                    'name' => '',
                    'tag' => '',
                    'type' => 'text',
                    'label' => '',
                    'placeholder' => '',
                    'required' => '0',
                    'visibility' => 'visible',
                    'sort_order' => 1,
                    'help_text' => '',
                    'default_value' => '',
                    'description' => '',
                    'validation_rules' => [
                        'min_length' => 1,
                        'max_length' => 255,
                    ],
                    'content_regex' => '',
                    'options' => [],
                ],
            ];
        }

        // Ensure all required custom fields are present
        $this->ensureRequiredCustomFields();
    }

    public function addField(string $type = 'text'): void
    {
        $fieldCount = count($this->fields);
        $defaultField = [
            'name' => '',
            'tag' => '',
            'type' => $type,
            'label' => '',
            'placeholder' => '',
            'required' => '0',
            'visibility' => 'visible',
            'sort_order' => $fieldCount + 1,
            'help_text' => '',
            'default_value' => '',
            'description' => '',
            'validation_rules' => [
                'min_length' => 1,
                'max_length' => 255,
            ],
            'content_regex' => '',
            'options' => [],
        ];

        // Type-specific defaults
        match ($type) {
            'select', 'multiselect', 'radiolist', 'checkboxlist' => $defaultField['options'] = [],
            'number' => $defaultField['validation_rules'] = ['min' => 0, 'max' => null],
            'email' => $defaultField['validation_rules'] = ['min_length' => 1, 'max_length' => 255],
            'tel' => $defaultField['validation_rules'] = ['min_length' => 1, 'max_length' => 20],
            'date', 'datetime' => $defaultField['validation_rules'] = [],
            'consent' => $defaultField['required'] = '1',
            default => null,
        };

        $this->fields[] = $defaultField;
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
        
        // Réorganiser les sort_order
        foreach ($this->fields as $newIndex => $field) {
            $this->fields[$newIndex]['sort_order'] = $newIndex + 1;
        }
    }

    public function addSourceField(): void
    {
        $existingFieldTags = array_column($this->fields, 'tag');
        
        // Vérifier si le champ SOURCE existe déjà
        if (! in_array('SOURCE', $existingFieldTags, true)) {
            $fieldCount = count($this->fields);
            $sourceField = $this->getSourceFieldTemplate();
            $sourceField['sort_order'] = $fieldCount + 1;
            $this->fields[] = $sourceField;
        } else {
            session()->flash('error', __('Le champ Source de trafic existe déjà.'));
        }
    }

    public function addOption(int $fieldIndex): void
    {
        if (! isset($this->fields[$fieldIndex]['options'])) {
            $this->fields[$fieldIndex]['options'] = [];
        }
        $this->fields[$fieldIndex]['options'][] = [
            'label' => '',
            'value' => '',
        ];
    }

    public function removeOption(int $fieldIndex, int $optionIndex): void
    {
        unset($this->fields[$fieldIndex]['options'][$optionIndex]);
        $this->fields[$fieldIndex]['options'] = array_values($this->fields[$fieldIndex]['options']);
    }

    public function update(): void
    {
        // No longer automatically adding required fields - they are optional now

        // Types de champs valides
        $validTypes = ['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'file', 'number', 'date', 'datetime', 'url', 'multiselect', 'radiolist', 'checkboxlist', 'consent'];

        // S'assurer que tag = name pour la compatibilité et valider les types
        foreach ($this->fields as $index => $field) {
            // Valider et corriger le type de champ
            if (! isset($this->fields[$index]['type']) || ! in_array($this->fields[$index]['type'], $validTypes, true)) {
                $this->fields[$index]['type'] = 'text'; // Type par défaut si invalide
            }
            if (empty($this->fields[$index]['name']) && ! empty($this->fields[$index]['tag'])) {
                $this->fields[$index]['name'] = $this->fields[$index]['tag'];
            }
            if (empty($this->fields[$index]['tag']) && ! empty($this->fields[$index]['name'])) {
                $this->fields[$index]['tag'] = $this->fields[$index]['name'];
            }
            // Convertir required en boolean
            if (isset($this->fields[$index]['required'])) {
                $this->fields[$index]['required'] = (bool) $this->fields[$index]['required'];
            }
        }

        $validated = $this->validate((new UpdateFormRequest)->rules());

        $this->form->update($validated);

        session()->flash('message', __('Formulaire modifié avec succès !'));

        $this->redirect(route('admin.forms'), navigate: true);
    }

    public function with(): array
    {
        return [
            'smtpProfiles' => SmtpProfile::where('is_active', true)->get(),
            'emailTemplates' => EmailTemplate::all(),
            'callCenters' => CallCenter::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <!-- Breadcrumb avec bouton de retour -->
    <div class="flex items-center justify-between">
        <flux:button href="{{ route('admin.forms') }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
            {{ __('Retour') }}
        </flux:button>
        <nav class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
            <a href="{{ route('admin.forms') }}" wire:navigate class="hover:text-neutral-900 dark:hover:text-neutral-100">
                {{ __('Formulaires') }}
            </a>
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ $form->name }}</span>
        </nav>
    </div>

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold">{{ __('Modifier le formulaire') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Modifiez le formulaire de capture de leads') }}</p>
    </div>

    <!-- Messages flash -->
    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    @if (session('error'))
        <flux:callout variant="danger" icon="exclamation-circle">
            {{ session('error') }}
        </flux:callout>
    @endif

    <!-- UID & API info -->
    <div class="rounded-xl border border-primary-200 bg-gradient-to-br from-primary-50 to-white p-6 shadow-sm dark:border-primary-800 dark:from-primary-950/50 dark:to-neutral-800">
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-900/30">
                <svg class="h-5 w-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Identifiant API du formulaire') }}</h2>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ __('Utilisez cet identifiant pour connecter vos landing pages à l\'API publique d\'insertion de leads.') }}
                </p>
            </div>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <flux:input
                value="{{ $form->uid }}"
                :label="__('UID (12 caractères)')"
                readonly
            />
            <flux:input
                value="{{ route('forms.submit', $form) }}"
                :label="__('Endpoint API (POST)')"
                readonly
            />
        </div>
        <flux:callout class="mt-4" variant="neutral" icon="information-circle">
            {{ __('Endpoint d’exemple : POST ') }}<code class="rounded bg-neutral-100 px-2 py-1 text-xs dark:bg-neutral-900">curl -X POST {{ route('forms.submit', $form) }}</code>
        </flux:callout>
    </div>

    <form wire:submit="update" class="space-y-8">
        <div class="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 border-b border-neutral-200 pb-3 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Informations générales') }}</h2>
            </div>
            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Nom du formulaire')" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            </div>
        </div>

        <div class="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 border-b border-neutral-200 pb-3 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Champs du formulaire') }}</h2>
            </div>

            <!-- Grille de boutons pour ajouter des champs -->
            <div class="mb-8">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                <button type="button" wire:click="addField('text')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Texte') }}</span>
                </button>
                <button type="button" wire:click="addField('select')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Liste déroulante') }}</span>
                </button>
                <button type="button" wire:click="addField('email')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Email') }}</span>
                </button>
                <button type="button" wire:click="addField('tel')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Téléphone') }}</span>
                </button>
                <button type="button" wire:click="addField('textarea')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Zone de texte') }}</span>
                </button>
                <button type="button" wire:click="addField('checkbox')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Case à cocher') }}</span>
                </button>
                <button type="button" wire:click="addField('date')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Date') }}</span>
                </button>
                <button type="button" wire:click="addField('datetime')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Date et heure') }}</span>
                </button>
                <button type="button" wire:click="addField('number')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Nombre') }}</span>
                </button>
                <button type="button" wire:click="addField('file')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Fichier') }}</span>
                </button>
                <button type="button" wire:click="addField('url')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('URL') }}</span>
                </button>
                <button type="button" wire:click="addField('multiselect')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Sélection multiple') }}</span>
                </button>
                <button type="button" wire:click="addField('radiolist')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Liste radio') }}</span>
                </button>
                <button type="button" wire:click="addField('checkboxlist')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6" />
                    </svg>
                    <span>{{ __('Liste de cases') }}</span>
                </button>
                <button type="button" wire:click="addField('consent')" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-3 text-xs font-medium text-neutral-700 transition-colors hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-300 dark:hover:border-neutral-600 dark:hover:bg-neutral-700">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ __('Consentement') }}</span>
                </button>
                <button type="button" wire:click="addSourceField()" class="flex flex-col items-center justify-center gap-1.5 rounded-md border border-blue-200 bg-blue-50 px-3 py-3 text-xs font-medium text-blue-700 transition-colors hover:border-blue-300 hover:bg-blue-100 dark:border-blue-700 dark:bg-blue-900/20 dark:text-blue-300 dark:hover:border-blue-600 dark:hover:bg-blue-900/30">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    <span>{{ __('Source de trafic') }}</span>
                </button>
            </div>

            <div class="mt-6 space-y-6">
                @if (count($fields) > 0)
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ __('Champs configurés') }} ({{ count($fields) }})</h3>
                    </div>
                @endif
                @foreach ($fields as $index => $field)
                    <div class="group relative rounded-lg border border-neutral-200 bg-white p-5 transition-colors hover:border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-neutral-600">
                        <!-- Header avec type de champ -->
                        <div class="mb-5 flex items-center justify-between border-b border-neutral-200 pb-3 dark:border-neutral-700">
                            <div class="flex items-center gap-3">
                                <div>
                                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">
                                        {{ !empty($field['label']) ? $field['label'] : ucfirst($field['type'] ?? 'text') . ' ' . __('field') }}
                                    </h3>
                                    <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">{{ ucfirst($field['type'] ?? 'text') }}</p>
                                </div>
                            </div>
                            @if(count($fields) > 1)
                                <button
                                    type="button"
                                    wire:click="removeField({{ $index }})"
                                    wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ce champ ?') }}"
                                    class="flex items-center gap-1.5 rounded-md bg-red-50 px-3 py-1.5 text-xs font-medium text-red-600 transition-colors hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span>{{ __('Supprimer') }}</span>
                                </button>
                            @else
                                <button
                                    type="button"
                                    disabled
                                    class="flex items-center gap-1.5 rounded-md bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-400 cursor-not-allowed dark:bg-neutral-700 dark:text-neutral-600"
                                >
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span>{{ __('Supprimer') }}</span>
                                </button>
                            @endif
                        </div>

                        <!-- Première rangée : Label, Tag -->
                        <div class="mb-4 grid gap-4 md:grid-cols-2">
                            <flux:input
                                wire:model="fields.{{ $index }}.label"
                                :label="__('Label')"
                                required
                            />
                            <flux:input
                                wire:model="fields.{{ $index }}.tag"
                                :label="__('Tag')"
                                required
                            />
                        </div>

                        <!-- Deuxième rangée : Required, Visibility, Sort order -->
                        <div class="mb-4 grid gap-4 md:grid-cols-3">
                            <flux:select
                                wire:model="fields.{{ $index }}.required"
                                :label="__('Required')"
                                required
                            >
                                <option value="0">{{ __('No') }}</option>
                                <option value="1">{{ __('Yes') }}</option>
                            </flux:select>
                            <flux:select
                                wire:model="fields.{{ $index }}.visibility"
                                :label="__('Visibility')"
                                required
                            >
                                <option value="visible">{{ __('Visible') }}</option>
                                <option value="hidden">{{ __('Hidden') }}</option>
                            </flux:select>
                            <flux:input
                                wire:model="fields.{{ $index }}.sort_order"
                                type="number"
                                :label="__('Sort order')"
                                required
                            />
                        </div>

                        <!-- Troisième rangée : Help text, Default value -->
                        <div class="mb-4 grid gap-4 md:grid-cols-2">
                            <flux:input
                                wire:model="fields.{{ $index }}.help_text"
                                :label="__('Help text')"
                                placeholder="{{ __('Help text') }}"
                            />
                            <flux:input
                                wire:model="fields.{{ $index }}.default_value"
                                :label="__('Default value')"
                                placeholder="{{ __('Default value') }}"
                            />
                        </div>

                        <!-- Quatrième rangée : Description, Minimum length, Maximum length, Content regex -->
                        <div class="mb-4 grid gap-4 md:grid-cols-4">
                            <flux:input
                                wire:model="fields.{{ $index }}.description"
                                :label="__('Description')"
                                placeholder="{{ __('Description') }}"
                            />
                            <flux:input
                                wire:model="fields.{{ $index }}.validation_rules.min_length"
                                type="number"
                                :label="__('Minimum length')"
                                required
                            />
                            <flux:input
                                wire:model="fields.{{ $index }}.validation_rules.max_length"
                                type="number"
                                :label="__('Maximum length')"
                                required
                            />
                            <flux:input
                                wire:model="fields.{{ $index }}.content_regex"
                                :label="__('Content regex')"
                                placeholder="{{ __('Content regex') }}"
                            />
                        </div>

                        <!-- Type de champ (caché mais nécessaire pour la logique) -->
                        <div class="hidden">
                            <flux:select
                                wire:model="fields.{{ $index }}.type"
                                :label="__('Type de champ')"
                                required
                            >
                                <option value="text">{{ __('Texte') }}</option>
                                <option value="email">{{ __('Email') }}</option>
                                <option value="tel">{{ __('Téléphone') }}</option>
                                <option value="textarea">{{ __('Zone de texte') }}</option>
                                <option value="select">{{ __('Liste déroulante') }}</option>
                                <option value="multiselect">{{ __('Sélection multiple') }}</option>
                                <option value="checkbox">{{ __('Case à cocher') }}</option>
                                <option value="consent">{{ __('Consentement') }}</option>
                                <option value="checkboxlist">{{ __('Liste de cases') }}</option>
                                <option value="radiolist">{{ __('Liste radio') }}</option>
                                <option value="file">{{ __('Fichier') }}</option>
                                <option value="number">{{ __('Nombre') }}</option>
                                <option value="date">{{ __('Date') }}</option>
                                <option value="datetime">{{ __('Date et heure') }}</option>
                                <option value="url">{{ __('URL') }}</option>
                            </flux:select>
                        </div>

                        @if (in_array($field['type'], ['select', 'multiselect', 'radiolist', 'checkboxlist']))
                            <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900">
                                <div class="mb-3 flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-neutral-700 dark:text-neutral-300">{{ __('Options') }}</h4>
                                    <flux:button
                                        type="button"
                                        wire:click="addOption({{ $index }})"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        {{ __('Ajouter') }}
                                    </flux:button>
                                </div>
                                <div class="space-y-2">
                                    <!-- En-têtes de colonnes -->
                                    <div class="grid grid-cols-[1fr_1fr_auto] gap-2 px-1">
                                        <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Label') }}</div>
                                        <div class="text-xs font-medium text-neutral-600 dark:text-neutral-400">{{ __('Value') }}</div>
                                        <div></div>
                                    </div>
                                    @foreach ($field['options'] ?? [] as $optionIndex => $option)
                                        <div class="flex items-center gap-2 rounded-md border border-neutral-200 bg-neutral-50 p-2 dark:border-neutral-600 dark:bg-neutral-800">
                                            <flux:input
                                                wire:model="fields.{{ $index }}.options.{{ $optionIndex }}.label"
                                                :placeholder="__('Label')"
                                                class="flex-1"
                                            />
                                            <flux:input
                                                wire:model="fields.{{ $index }}.options.{{ $optionIndex }}.value"
                                                :placeholder="__('Value')"
                                                class="flex-1"
                                            />
                                            <button
                                                type="button"
                                                wire:click="removeOption({{ $index }}, {{ $optionIndex }})"
                                                class="flex h-9 w-9 items-center justify-center rounded-md bg-red-50 text-red-600 transition-colors hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:hover:bg-red-900/30"
                                                title="{{ __('Supprimer') }}"
                                            >
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-8 rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 border-b border-neutral-200 pb-3 dark:border-neutral-700">
                <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ __('Configuration') }}</h2>
            </div>
            <div class="space-y-4">
                <flux:select wire:model="smtp_profile_id" :label="__('Profil SMTP')" required>
                    <option value="">{{ __('Sélectionner un profil SMTP') }}</option>
                    @foreach ($smtpProfiles as $profile)
                        <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="email_template_id" :label="__('Template d\'email')" required>
                    <option value="">{{ __('Sélectionner un template d\'email') }}</option>
                    @foreach ($emailTemplates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="call_center_id" :label="__('Centre d\'appels')" required>
                    <option value="">{{ __('Sélectionner un centre d\'appels') }}</option>
                    @foreach ($callCenters as $callCenter)
                        <option value="{{ $callCenter->id }}">{{ $callCenter->name }}</option>
                    @endforeach
                </flux:select>
                <flux:switch wire:model="is_active" :label="__('Formulaire actif')" />
            </div>
        </div>

        <!-- Section d'aide pour l'utilisation de l'API -->
        <div class="mt-10 rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h2 class="text-lg font-semibold text-blue-900 dark:text-blue-100">{{ __('Comment utiliser ce formulaire') }}</h2>
                </div>
                <button type="button" onclick="toggleApiHelp()" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-200">
                    <svg id="api-help-icon" class="h-5 w-5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
            </div>
            
            <div id="api-help-content" class="hidden space-y-4">
                <div class="rounded-lg border border-blue-200 bg-white p-4 dark:border-blue-700 dark:bg-neutral-800">
                    <h3 class="mb-2 font-semibold text-blue-900 dark:text-blue-100">{{ __('URL de l\'API') }}</h3>
                    <div class="flex items-center gap-2">
                        <code class="flex-1 rounded bg-neutral-100 px-3 py-2 text-sm dark:bg-neutral-900" id="api-url">{{ route('forms.submit', $form) }}</code>
                        <button type="button" onclick="copyApiUrl()" class="rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                            {{ __('Copier') }}
                        </button>
                    </div>
                </div>

                <div class="rounded-lg border border-blue-200 bg-white p-4 dark:border-blue-700 dark:bg-neutral-800">
                    <h3 class="mb-2 font-semibold text-blue-900 dark:text-blue-100">{{ __('Méthode HTTP') }}</h3>
                    <code class="rounded bg-neutral-100 px-3 py-2 text-sm dark:bg-neutral-900">POST</code>
                </div>

                <div class="rounded-lg border border-blue-200 bg-white p-4 dark:border-blue-700 dark:bg-neutral-800">
                    <h3 class="mb-3 font-semibold text-blue-900 dark:text-blue-100">{{ __('Exemple de code JavaScript pour landing page') }}</h3>
                    <pre class="overflow-x-auto rounded bg-neutral-900 p-4 text-sm text-neutral-100"><code id="js-example">// Configuration
const FORM_UID = '{{ $form->uid }}';
const API_URL = '{{ route('forms.submit', $form) }}';

// Fonction pour soumettre le formulaire
async function submitForm(formData) {
    try {
        const response = await fetch(API_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (response.ok) {
            // Succès
            alert(data.message || 'Formulaire soumis avec succès !');
            // Réinitialiser le formulaire ou rediriger
            document.getElementById('leadForm').reset();
        } else {
            // Erreur de validation
            if (data.errors) {
                let errorMessage = 'Erreurs de validation :\n';
                for (const [field, errors] of Object.entries(data.errors)) {
                    errorMessage += `- ${field}: ${errors.join(', ')}\n`;
                }
                alert(errorMessage);
            } else {
                alert(data.message || 'Une erreur est survenue.');
            }
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('Une erreur réseau est survenue. Veuillez réessayer.');
    }
}

// Exemple d'utilisation avec un formulaire HTML
document.getElementById('leadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = {};
    
    // Convertir FormData en objet
    formData.forEach((value, key) => {
        data[key] = value;
    });
    
    // Soumettre via l'API
    await submitForm(data);
});

// Exemple avec jQuery (si vous utilisez jQuery)
/*
$('#leadForm').on('submit', async function(e) {
    e.preventDefault();
    
    const formData = $(this).serializeArray();
    const data = {};
    
    $.each(formData, function(i, field) {
        data[field.name] = field.value;
    });
    
    await submitForm(data);
});
*/</code></pre>
                    <button type="button" onclick="copyJsExample()" class="mt-2 rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                        {{ __('Copier le code JavaScript') }}
                    </button>
                </div>

                <div class="rounded-lg border border-blue-200 bg-white p-4 dark:border-blue-700 dark:bg-neutral-800">
                    <h3 class="mb-2 font-semibold text-blue-900 dark:text-blue-100">{{ __('Exemple de formulaire HTML') }}</h3>
                    <pre class="overflow-x-auto rounded bg-neutral-900 p-4 text-sm text-neutral-100"><code id="html-example">&lt;form id="leadForm"&gt;
    &lt;div class="form-group"&gt;
        &lt;label for="name"&gt;Nom complet *&lt;/label&gt;
        &lt;input type="text" id="name" name="name" required placeholder="Votre nom"&gt;
    &lt;/div&gt;
    &lt;div class="form-group"&gt;
        &lt;label for="email"&gt;Email *&lt;/label&gt;
        &lt;input type="email" id="email" name="email" required placeholder="votre@email.com"&gt;
    &lt;/div&gt;
    &lt;div class="form-group"&gt;
        &lt;label for="phone"&gt;Téléphone&lt;/label&gt;
        &lt;input type="tel" id="phone" name="phone" placeholder="+33 6 12 34 56 78"&gt;
    &lt;/div&gt;
    &lt;button type="submit"&gt;Envoyer&lt;/button&gt;
&lt;/form&gt;

&lt;!-- Note: Adaptez les champs selon votre formulaire --&gt;</code></pre>
                    <button type="button" onclick="copyHtmlExample()" class="mt-2 rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                        {{ __('Copier le code HTML') }}
                    </button>
                    <p class="mt-2 text-xs text-neutral-600 dark:text-neutral-400">
                        {{ __('Note: Adaptez les champs (name, email, phone, etc.) selon les champs définis dans votre formulaire ci-dessus.') }}
                    </p>
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20">
                    <h3 class="mb-2 flex items-center gap-2 font-semibold text-amber-900 dark:text-amber-100">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        {{ __('Points importants') }}
                    </h3>
                    <ul class="ml-6 list-disc space-y-1 text-sm text-amber-800 dark:text-amber-200">
                        <li>{{ __('L\'API accepte uniquement les requêtes POST en JSON') }}</li>
                        <li>{{ __('Le header Content-Type doit être application/json') }}</li>
                        <li>{{ __('Tous les champs marqués comme obligatoires doivent être fournis') }}</li>
                        <li>{{ __('Un champ email est requis pour créer un lead') }}</li>
                        <li>{{ __('En cas de succès, un email de confirmation sera envoyé au lead') }}</li>
                        <li>{{ __('Les erreurs de validation retournent un code 422 avec les détails') }}</li>
                        <li>{{ __('✅ Support CORS activé : soumission possible depuis n\'importe quel domaine externe') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="sticky bottom-0 z-10 -mx-6 -mb-6 mt-8 flex items-center justify-between border-t border-neutral-200 bg-white px-6 py-4 shadow-lg dark:border-neutral-700 dark:bg-neutral-800">
            <flux:button href="{{ route('admin.forms') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled" class="min-w-[180px]">
                <span wire:loading.remove wire:target="update" class="flex items-center gap-2">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('Enregistrer les modifications') }}
                </span>
                <span wire:loading wire:target="update" class="flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ __('Enregistrement...') }}
                </span>
            </flux:button>
        </div>
    </form>
</div>

<script>
function toggleApiHelp() {
    const content = document.getElementById('api-help-content');
    const icon = document.getElementById('api-help-icon');
    content.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}

function showCopyNotification(message, type = 'success') {
    // Créer l'élément de notification
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 rounded-lg px-4 py-3 shadow-lg transition-all duration-300 ${
        type === 'success' 
            ? 'bg-green-500 text-white' 
            : 'bg-red-500 text-white'
    }`;
    notification.style.opacity = '0';
    notification.style.transform = 'translateY(-20px)';
    notification.textContent = message;
    
    // Ajouter au DOM
    document.body.appendChild(notification);
    
    // Animation d'entrée
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateY(0)';
    }, 10);
    
    // Supprimer après 3 secondes
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

function copyApiUrl() {
    const url = document.getElementById('api-url').textContent;
    navigator.clipboard.writeText(url).then(() => {
        showCopyNotification('URL copiée dans le presse-papiers !');
    }).catch(() => {
        showCopyNotification('Erreur lors de la copie', 'error');
    });
}

function copyJsExample() {
    const code = document.getElementById('js-example').textContent;
    navigator.clipboard.writeText(code).then(() => {
        showCopyNotification('Code JavaScript copié dans le presse-papiers !');
    }).catch(() => {
        showCopyNotification('Erreur lors de la copie', 'error');
    });
}

function copyHtmlExample() {
    const code = document.getElementById('html-example').textContent;
    navigator.clipboard.writeText(code).then(() => {
        showCopyNotification('Code HTML copié dans le presse-papiers !');
    }).catch(() => {
        showCopyNotification('Erreur lors de la copie', 'error');
    });
}
</script>

