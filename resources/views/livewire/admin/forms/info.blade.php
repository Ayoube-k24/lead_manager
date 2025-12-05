<?php

use App\Models\Form;
use Livewire\Volt\Component;

new class extends Component {
    public Form $form;

    public function mount(Form $form): void
    {
        $this->form = $form;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
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
            <span>/</span>
            <span class="text-neutral-900 dark:text-neutral-100">{{ __('Guide API') }}</span>
        </nav>
    </div>

    <div>
        <h1 class="text-2xl font-bold">{{ __('Guide d\'intégration API') }}</h1>
        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ __('Instructions complètes pour connecter ce formulaire à vos landing pages') }}</p>
    </div>

    <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <p class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ __('UID du formulaire (12 caractères)') }}</p>
                <code class="mt-2 inline-block rounded bg-white px-3 py-2 text-sm dark:bg-neutral-900">{{ $form->uid }}</code>
            </div>
            <div>
                <p class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ __('Endpoint API (POST)') }}</p>
                <div class="mt-2 flex items-center gap-2">
                    <code class="flex-1 rounded bg-white px-3 py-2 text-xs dark:bg-neutral-900" id="api-url">{{ route('forms.submit', $form) }}</code>
                    <flux:button size="xs" onclick="navigator.clipboard.writeText('{{ route('forms.submit', $form) }}')">
                        {{ __('Copier') }}
                    </flux:button>
                </div>
            </div>
        </div>
        <p class="mt-4 text-xs text-blue-900/80 dark:text-blue-200">{{ __('Envoyez vos leads via HTTPS en JSON sur cet endpoint.') }}</p>
    </div>

    <div class="space-y-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                    <span class="font-bold">1</span>
                </div>
            <h2 class="text-lg font-semibold">{{ __('Préparez votre formulaire HTML') }}</h2>
            </div>
            <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Ajoutez les champs suivants sur votre landing page. Respectez les attributs "name" indiqués ci-dessous.') }}
            </p>
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('Exemple de formulaire HTML') }}</h3>
                    <flux:button size="xs" onclick="copyExample('html-example')">{{ __('Copier') }}</flux:button>
                </div>
                <pre class="overflow-x-auto rounded bg-neutral-900 p-4 text-sm text-neutral-100"><code id="html-example">&lt;form id="leadForm"&gt;
@foreach ($form->fields ?? [] as $field)
    &lt;div class="form-group"&gt;
        &lt;label for="{{ $field['name'] }}"&gt;{{ $field['label'] }}@if($field['required'] ?? false) * @endif&lt;/label&gt;
        @if(($field['type'] ?? 'text') === 'textarea')
            &lt;textarea id="{{ $field['name'] }}" name="{{ $field['name'] }}" @if($field['required'] ?? false) required @endif placeholder="{{ $field['placeholder'] ?? '' }}"&gt;&lt;/textarea&gt;
        @elseif(($field['type'] ?? 'text') === 'select')
            &lt;select id="{{ $field['name'] }}" name="{{ $field['name'] }}" @if($field['required'] ?? false) required @endif&gt;
@foreach($field['options'] ?? [] as $option)
                &lt;option value="{{ $option }}"&gt;{{ $option }}&lt;/option&gt;
@endforeach
            &lt;/select&gt;
        @elseif(($field['type'] ?? 'text') === 'checkbox')
            &lt;input type="checkbox" id="{{ $field['name'] }}" name="{{ $field['name'] }}" @if($field['required'] ?? false) required @endif&gt;
        @else
            &lt;input type="{{ $field['type'] ?? 'text' }}" id="{{ $field['name'] }}" name="{{ $field['name'] }}" @if($field['required'] ?? false) required @endif placeholder="{{ $field['placeholder'] ?? '' }}"&gt;
        @endif
    &lt;/div&gt;
@endforeach
    &lt;button type="submit"&gt;Envoyer&lt;/button&gt;
&lt;/form&gt;</code></pre>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                    <span class="font-bold">2</span>
                </div>
                <h2 class="text-lg font-semibold">{{ __('Soumettez le formulaire via JavaScript') }}</h2>
            </div>
            <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Envoyez les données au format JSON avec fetch (ou Axios). Exemple complet :') }}
            </p>
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('Exemple JavaScript') }}</h3>
                    <flux:button size="xs" onclick="copyExample('js-example')">{{ __('Copier') }}</flux:button>
                </div>
                <pre class="overflow-x-auto rounded bg-neutral-900 p-4 text-sm text-neutral-100"><code id="js-example">const API_URL = '{{ route('forms.submit', $form) }}';

async function submitLead(formData) {
    const response = await fetch(API_URL, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body: JSON.stringify(formData),
    });

    const data = await response.json();

    if (! response.ok) {
        throw data;
    }

    return data;
}

document.getElementById('leadForm').addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = Object.fromEntries(new FormData(event.currentTarget));

    try {
        const result = await submitLead(formData);
        alert(result.message ?? 'Lead envoyé !');
        event.currentTarget.reset();
    } catch (error) {
        alert(error.message ?? 'Une erreur est survenue.');
    }
});</code></pre>
            </div>
        </div>

        <div class="rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mb-4 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300">
                    <span class="font-bold">3</span>
                </div>
                <h2 class="text-lg font-semibold">{{ __('Tester avec cURL') }}</h2>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900/40">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold">{{ __('Commande cURL') }}</h3>
                    <flux:button size="xs" onclick="copyExample('curl-example')">{{ __('Copier') }}</flux:button>
                </div>
                <pre class="overflow-x-auto rounded bg-neutral-900 p-4 text-sm text-neutral-100"><code id="curl-example">curl -X POST {{ route('forms.submit', $form) }} \
    -H "Content-Type: application/json" \
    -d '{@json(collect($form->fields)->mapWithKeys(fn ($field) => [$field['name'] => 'EXEMPLE'])->toArray(), JSON_PRETTY_PRINT)}'</code></pre>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-6 dark:border-yellow-800 dark:bg-yellow-900/30">
        <h2 class="mb-3 text-lg font-semibold text-yellow-900 dark:text-yellow-200">{{ __('Notes importantes') }}</h2>
        <ul class="list-disc space-y-2 pl-6 text-sm text-yellow-900/90 dark:text-yellow-100">
            <li>{{ __('Toutes les requêtes doivent être envoyées en HTTPS.') }}</li>
            <li>{{ __('Le champ email est requis pour déclencher le double opt-in.') }}</li>
            <li>{{ __('Le taux de réussite dépend de la configuration SMTP et des templates email associés.') }}</li>
            <li>{{ __('Récupérez l\'UID depuis la page d\'édition du formulaire ou cette page.') }}</li>
            <li>{{ __('✅ Support CORS activé : vous pouvez soumettre des formulaires depuis n\'importe quel domaine externe (landing pages).') }}</li>
        </ul>
    </div>
</div>

<script>
    function copyExample(id) {
        const el = document.getElementById(id);
        if (! el) return;

        navigator.clipboard.writeText(el.innerText).then(() => {
            showCopyNotification('Code copié dans le presse-papiers !');
        }).catch(() => {
            showCopyNotification('Erreur lors de la copie', 'error');
        });
    }

    function showCopyNotification(message, type = 'success') {
        // Créer l'élément de notification
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 rounded-lg px-4 py-3 shadow-lg transition-all duration-300 ${
            type === 'success' 
                ? 'bg-green-500 text-white' 
                : 'bg-red-500 text-white'
        }`;
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
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }
</script>

