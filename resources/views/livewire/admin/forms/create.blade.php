<?php

use App\Http\Requests\StoreFormRequest;
use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\SmtpProfile;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public ?string $description = null;
    public array $fields = [];
    public ?int $smtp_profile_id = null;
    public ?int $email_template_id = null;
    public ?int $call_center_id = null;
    public bool $is_active = true;

    public function mount(): void
    {
        // Initialize with one empty field
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

    public function store(): void
    {
        $validated = $this->validate((new StoreFormRequest)->rules());

        Form::create($validated);

        session()->flash('message', __('Formulaire créé avec succès !'));

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
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Créer') }}</span>
        </nav>
    </div>

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold">{{ __('Créer un formulaire') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Créez un nouveau formulaire de capture de leads avec validation dynamique') }}</p>
    </div>

    <flux:callout variant="neutral" icon="information-circle">
        {{ __('Un identifiant API unique de 12 caractères sera généré automatiquement après la création. Utilisez-le pour connecter vos landing pages.') }}
    </flux:callout>

    <form wire:submit="store" class="space-y-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-lg font-semibold">{{ __('Informations générales') }}</h2>
            <div class="space-y-4">
                <flux:input wire:model="name" :label="__('Nom du formulaire')" required />
                <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center justify-between">
<div>
                    <h2 class="text-lg font-semibold">{{ __('Champs du formulaire') }}</h2>
                    <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ __('Définissez les champs que les utilisateurs devront remplir') }}</p>
                </div>
                <flux:button type="button" wire:click="addField" variant="ghost" size="sm" icon="plus">
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

        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
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
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
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
                    <p class="mb-2 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('L\'URL de l\'API sera disponible après la création du formulaire. Format :') }}
                    </p>
                    <code class="rounded bg-neutral-100 px-3 py-2 text-sm dark:bg-neutral-900">{{ url('/forms') }}/<span class="text-blue-600 dark:text-blue-400">[FORM_ID]</span>/submit</code>
                </div>

                <div class="rounded-lg border border-blue-200 bg-white p-4 dark:border-blue-700 dark:bg-neutral-800">
                    <h3 class="mb-2 font-semibold text-blue-900 dark:text-blue-100">{{ __('Méthode HTTP') }}</h3>
                    <code class="rounded bg-neutral-100 px-3 py-2 text-sm dark:bg-neutral-900">POST</code>
                </div>

                <div class="rounded-lg border border-blue-200 bg-white p-4 dark:border-blue-700 dark:bg-neutral-800">
                    <h3 class="mb-3 font-semibold text-blue-900 dark:text-blue-100">{{ __('Exemple de code JavaScript pour landing page') }}</h3>
                    <pre class="overflow-x-auto rounded bg-neutral-900 p-4 text-sm text-neutral-100"><code id="js-example">// Configuration - Remplacez FORM_ID par l'ID de votre formulaire
const FORM_ID = 1; // À remplacer par l'ID du formulaire après création
const API_URL = `{{ url('/forms') }}/${FORM_ID}/submit`;

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
});</code></pre>
                    <button type="button" onclick="copyJsExample()" class="mt-2 rounded bg-blue-600 px-3 py-2 text-sm text-white hover:bg-blue-700">
                        {{ __('Copier le code JavaScript') }}
                    </button>
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
                        <li>{{ __('Notez l\'ID du formulaire après création pour l\'utiliser dans votre code') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between border-t border-neutral-200 pt-6 dark:border-neutral-700">
            <flux:button href="{{ route('admin.forms') }}" variant="ghost" wire:navigate>
                {{ __('Annuler') }}
            </flux:button>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="store">{{ __('Créer le formulaire') }}</span>
                <span wire:loading wire:target="store">{{ __('Création en cours...') }}</span>
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

function copyJsExample() {
    const code = document.getElementById('js-example').textContent;
    navigator.clipboard.writeText(code).then(() => {
        showCopyNotification('Code JavaScript copié dans le presse-papiers !');
    }).catch(() => {
        showCopyNotification('Erreur lors de la copie', 'error');
    });
}
</script>
