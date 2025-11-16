<?php

use App\Http\Requests\UpdateFormRequest;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\SmtpProfile;
use Livewire\Volt\Component;

new class extends Component {
    public Form $form;
    public string $name = '';
    public ?string $description = null;
    public array $fields = [];
    public ?int $smtp_profile_id = null;
    public ?int $email_template_id = null;
    public bool $is_active = true;

    public function mount(Form $form): void
    {
        $this->form = $form;
        $this->name = $form->name;
        $this->description = $form->description;
        $this->fields = $form->fields ?? [];
        $this->smtp_profile_id = $form->smtp_profile_id;
        $this->email_template_id = $form->email_template_id;
        $this->is_active = $form->is_active;

        if (empty($this->fields)) {
            $this->fields = [
                [
                    'name' => '',
                    'type' => 'text',
                    'label' => '',
                    'placeholder' => '',
                    'required' => false,
                    'validation_rules' => [],
                    'options' => [],
                ],
            ];
        }
    }

    public function addField(): void
    {
        $this->fields[] = [
            'name' => '',
            'type' => 'text',
            'label' => '',
            'placeholder' => '',
            'required' => false,
            'validation_rules' => [],
            'options' => [],
        ];
    }

    public function removeField(int $index): void
    {
        unset($this->fields[$index]);
        $this->fields = array_values($this->fields);
    }

    public function addOption(int $fieldIndex): void
    {
        if (! isset($this->fields[$fieldIndex]['options'])) {
            $this->fields[$fieldIndex]['options'] = [];
        }
        $this->fields[$fieldIndex]['options'][] = '';
    }

    public function removeOption(int $fieldIndex, int $optionIndex): void
    {
        unset($this->fields[$fieldIndex]['options'][$optionIndex]);
        $this->fields[$fieldIndex]['options'] = array_values($this->fields[$fieldIndex]['options']);
    }

    public function update(): void
    {
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

    <form wire:submit="update" class="space-y-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations générales') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Nom du formulaire')" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ __('Champs du formulaire') }}</h2>
                <flux:button type="button" wire:click="addField" variant="ghost" size="sm">
                    {{ __('Ajouter un champ') }}
                </flux:button>
            </div>

            <div class="space-y-6">
                @foreach ($fields as $index => $field)
                    <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="font-semibold">{{ __('Champ') }} #{{ $index + 1 }}</h3>
                            @if (count($fields) > 1)
                                <flux:button
                                    type="button"
                                    wire:click="removeField({{ $index }})"
                                    variant="danger"
                                    size="sm"
                                >
                                    {{ __('Supprimer') }}
                                </flux:button>
                            @endif
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <flux:input
                                wire:model="fields.{{ $index }}.name"
                                :label="__('Nom du champ (technique)')"
                                required
                            />
                            <flux:input
                                wire:model="fields.{{ $index }}.label"
                                :label="__('Libellé')"
                                required
                            />
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
                                <option value="checkbox">{{ __('Case à cocher') }}</option>
                                <option value="file">{{ __('Fichier') }}</option>
                                <option value="number">{{ __('Nombre') }}</option>
                                <option value="date">{{ __('Date') }}</option>
                            </flux:select>
                            <flux:input
                                wire:model="fields.{{ $index }}.placeholder"
                                :label="__('Placeholder')"
                            />
                        </div>

                        <div class="mt-4">
                            <flux:checkbox
                                wire:model="fields.{{ $index }}.required"
                                :label="__('Champ obligatoire')"
                            />
                        </div>

                        <!-- Règles de validation personnalisées -->
                        <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/50">
                            <h4 class="mb-3 text-sm font-semibold">{{ __('Règles de validation (optionnel)') }}</h4>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @if (in_array($field['type'], ['text', 'textarea', 'email', 'tel']))
                                    <flux:input
                                        wire:model="fields.{{ $index }}.validation_rules.min_length"
                                        type="number"
                                        :label="__('Longueur minimale')"
                                        placeholder="Ex: 3"
                                    />
                                    <flux:input
                                        wire:model="fields.{{ $index }}.validation_rules.max_length"
                                        type="number"
                                        :label="__('Longueur maximale')"
                                        placeholder="Ex: 255"
                                    />
                                @endif
                                @if (in_array($field['type'], ['number']))
                                    <flux:input
                                        wire:model="fields.{{ $index }}.validation_rules.min"
                                        type="number"
                                        :label="__('Valeur minimale')"
                                        placeholder="Ex: 0"
                                    />
                                    <flux:input
                                        wire:model="fields.{{ $index }}.validation_rules.max"
                                        type="number"
                                        :label="__('Valeur maximale')"
                                        placeholder="Ex: 100"
                                    />
                                @endif
                                @if (in_array($field['type'], ['text', 'textarea', 'email', 'tel']))
                                    <div class="sm:col-span-2">
                                        <flux:input
                                            wire:model="fields.{{ $index }}.validation_rules.regex"
                                            :label="__('Expression régulière (regex)')"
                                            placeholder="Ex: /^[A-Za-z]+$/"
                                        />
                                        <flux:text class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                            {{ __('Format: /pattern/ ou pattern') }}
                                        </flux:text>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if ($field['type'] === 'select')
                            <div class="mt-4">
                                <div class="mb-2 flex items-center justify-between">
                                    <flux:text class="font-semibold">{{ __('Options') }}</flux:text>
                                    <flux:button
                                        type="button"
                                        wire:click="addOption({{ $index }})"
                                        variant="ghost"
                                        size="sm"
                                    >
                                        {{ __('Ajouter une option') }}
                                    </flux:button>
                                </div>
                                <div class="space-y-2">
                                    @foreach ($field['options'] ?? [] as $optionIndex => $option)
                                        <div class="flex items-center gap-2">
                                            <flux:input
                                                wire:model="fields.{{ $index }}.options.{{ $optionIndex }}"
                                                :placeholder="__('Valeur de l\'option')"
                                            />
                                            <flux:button
                                                type="button"
                                                wire:click="removeOption({{ $index }}, {{ $optionIndex }})"
                                                variant="danger"
                                                size="sm"
                                            >
                                                {{ __('Supprimer') }}
                                            </flux:button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Configuration') }}</h2>
            <div class="space-y-4">
                <flux:select wire:model="smtp_profile_id" :label="__('Profil SMTP')">
                    <option value="">{{ __('Aucun') }}</option>
                    @foreach ($smtpProfiles as $profile)
                        <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="email_template_id" :label="__('Template d\'email')">
                    <option value="">{{ __('Aucun') }}</option>
                    @foreach ($emailTemplates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </flux:select>
                <flux:switch wire:model="is_active" :label="__('Formulaire actif')" />
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between border-t border-neutral-200 pt-6 dark:border-neutral-700">
            <flux:button href="{{ route('admin.forms') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="update">{{ __('Enregistrer les modifications') }}</span>
                <span wire:loading wire:target="update">{{ __('Enregistrement...') }}</span>
            </flux:button>
        </div>
    </form>
</div>

