<?php

use Livewire\Volt\Component;

new class extends Component {
    public function mount(): void
    {
        //
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            @php
                $backRoute = auth()->user()?->role?->slug === 'super_admin' 
                    ? route('admin.api-tokens') 
                    : route('owner.api-tokens');
            @endphp
            <flux:button href="{{ $backRoute }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
                {{ __('Retour') }}
            </flux:button>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900 dark:text-neutral-100">
                {{ __('Documentation API') }}
            </h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Guide complet pour utiliser l\'API Lead Manager') }}
            </p>
        </div>
    </div>

    <div class="space-y-8">
        <!-- Introduction -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Introduction') }}
            </h2>
            <p class="text-neutral-600 dark:text-neutral-400">
                {{ __('L\'API Lead Manager vous permet de gérer vos formulaires, profils SMTP et templates d\'email via des requêtes HTTP. Tous les endpoints nécessitent une authentification par token.') }}
            </p>
        </section>

        <!-- Authentification -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Authentification') }}
            </h2>
            <p class="mb-4 text-neutral-600 dark:text-neutral-400">
                {{ __('Toutes les requêtes API doivent inclure un token d\'authentification dans l\'en-tête. Vous pouvez créer des tokens depuis la page de gestion des tokens API.') }}
            </p>

            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <p class="mb-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Méthode 1 : Bearer Token (recommandé)') }}
                </p>
                <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                    Authorization: Bearer {votre_token}
                </code>
            </div>

            <div class="mt-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <p class="mb-2 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                    {{ __('Méthode 2 : Header personnalisé') }}
                </p>
                <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                    X-API-Token: {votre_token}
                </code>
            </div>
        </section>

        <!-- Base URL -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('URL de base') }}
            </h2>
            <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                <code class="text-sm text-neutral-800 dark:text-neutral-200">
                    {{ url('/api') }}
                </code>
            </div>
        </section>

        <!-- Formulaires -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Formulaires') }}
            </h2>

            <div class="space-y-6">
                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Liste des formulaires') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            GET /api/forms
                        </code>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Créer un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            POST /api/forms
                        </code>
                        <pre class="mt-2 overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>{
  "name": "Formulaire de contact",
  "description": "Formulaire pour capturer les leads",
  "call_center_id": 1,
  "fields": [
    {
      "name": "email",
      "type": "email",
      "label": "Email",
      "placeholder": "votre@email.com",
      "required": true
    },
    {
      "name": "name",
      "type": "text",
      "label": "Nom",
      "required": true,
      "validation_rules": {
        "min_length": 2,
        "max_length": 50
      }
    },
    {
      "name": "phone",
      "type": "tel",
      "label": "Téléphone",
      "required": false,
      "validation_rules": {
        "regex": "^[+]?[(]?[0-9]{1,4}[)]?[-\\s.]?[(]?[0-9]{1,4}[)]?[-\\s.]?[0-9]{1,9}$"
      }
    },
    {
      "name": "message",
      "type": "textarea",
      "label": "Message",
      "required": false
    }
  ],
  "smtp_profile_id": 1,
  "email_template_id": 1,
  "is_active": true
}</code></pre>
                        <p class="mt-2 text-xs text-neutral-600 dark:text-neutral-400">
                            {{ __('Note : Les règles de validation (`validation_rules`) sont optionnelles. Vous pouvez créer des champs avec ou sans règles de validation personnalisées.') }}
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Voir un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            GET /api/forms/{id}
                        </code>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Modifier un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            PUT /api/forms/{id}
                        </code>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Supprimer un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            DELETE /api/forms/{id}
                        </code>
                    </div>
                </div>
            </div>

            <!-- Règles de validation -->
            <div class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                <h3 class="mb-3 font-semibold text-blue-900 dark:text-blue-100">
                    {{ __('Règles de validation personnalisées (optionnel)') }}
                </h3>
                <p class="mb-4 text-sm text-blue-700 dark:text-blue-300">
                    {{ __('Vous pouvez optionnellement définir des règles de validation personnalisées pour chaque champ via l\'objet `validation_rules`. Ces règles ne sont pas obligatoires - vous pouvez créer un formulaire sans aucune règle de validation personnalisée.') }}
                </p>
                
                <div class="space-y-3">
                    <div>
                        <h4 class="mb-1 text-sm font-semibold text-blue-900 dark:text-blue-100">
                            {{ __('Règles disponibles') }}
                        </h4>
                        <ul class="list-disc space-y-1 pl-5 text-xs text-blue-700 dark:text-blue-300">
                            <li><code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900/40">min_length</code> / <code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900/40">min</code> : Longueur minimale (pour text, textarea, email, tel)</li>
                            <li><code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900/40">max_length</code> / <code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900/40">max</code> : Longueur maximale (pour text, textarea, email, tel)</li>
                            <li><code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900/40">min</code> : Valeur minimale (pour number)</li>
                            <li><code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900/40">max</code> : Valeur maximale (pour number)</li>
                            <li><code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900/40">regex</code> : Expression régulière personnalisée</li>
                            <li><code class="rounded bg-blue-100 px-1 py-0.5 dark:bg-blue-900/40">in</code> : Liste de valeurs autorisées (tableau ou chaîne séparée par virgules)</li>
                        </ul>
                    </div>

                    <div>
                        <h4 class="mb-1 text-sm font-semibold text-blue-900 dark:text-blue-100">
                            {{ __('Exemples') }}
                        </h4>
                        <pre class="mt-2 overflow-x-auto rounded border border-blue-200 bg-white p-3 text-xs text-neutral-700 dark:border-blue-700 dark:bg-neutral-800 dark:text-neutral-300"><code>{
  "name": "age",
  "type": "number",
  "label": "Âge",
  "required": true,
  "validation_rules": {
    "min": 18,
    "max": 120
  }
}

{
  "name": "country",
  "type": "select",
  "label": "Pays",
  "required": true,
  "options": ["FR", "BE", "CH"],
  "validation_rules": {
    "in": ["FR", "BE", "CH"]
  }
}

{
  "name": "postal_code",
  "type": "text",
  "label": "Code postal",
  "required": true,
  "validation_rules": {
    "min_length": 5,
    "max_length": 5,
    "regex": "^[0-9]{5}$"
  }
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Profils SMTP -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Profils SMTP') }}
            </h2>

            <div class="space-y-6">
                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Liste des profils SMTP') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            GET /api/smtp-profiles
                        </code>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Créer un profil SMTP') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            POST /api/smtp-profiles
                        </code>
                        <pre class="mt-2 overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>{
  "name": "SMTP Gmail",
  "host": "smtp.gmail.com",
  "port": 587,
  "encryption": "tls",
  "username": "votre@email.com",
  "password": "votre_mot_de_passe",
  "from_address": "noreply@example.com",
  "from_name": "Lead Manager",
  "is_active": true
}</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Voir un profil SMTP') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            GET /api/smtp-profiles/{id}
                        </code>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Modifier un profil SMTP') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            PUT /api/smtp-profiles/{id}
                        </code>
                        <p class="mt-2 text-xs text-neutral-600 dark:text-neutral-400">
                            {{ __('Note : Le mot de passe n\'est requis que si vous souhaitez le modifier.') }}
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Supprimer un profil SMTP') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            DELETE /api/smtp-profiles/{id}
                        </code>
                    </div>
                </div>
            </div>
        </section>

        <!-- Templates d'email -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Templates d\'email') }}
            </h2>

            <div class="space-y-6">
                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Liste des templates') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            GET /api/email-templates
                        </code>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Créer un template') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            POST /api/email-templates
                        </code>
                        <pre class="mt-2 overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>{
  "name": "Confirmation d'inscription",
  "subject": "Confirmez votre inscription",
  "body_html": "&lt;h1&gt;Bonjour @{{name}}&lt;/h1&gt;&lt;p&gt;Cliquez sur le lien pour confirmer...&lt;/p&gt;",
  "body_text": "Bonjour @{{name}}\n\nCliquez sur le lien pour confirmer...",
  "variables": ["name", "email", "confirmation_link"]
}</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Voir un template') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            GET /api/email-templates/{id}
                        </code>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Modifier un template') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            PUT /api/email-templates/{id}
                        </code>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Supprimer un template') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            DELETE /api/email-templates/{id}
                        </code>
                    </div>
                </div>
            </div>
        </section>

        <!-- Exemples -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Exemples de requêtes') }}
            </h2>

            <div class="space-y-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                    <p class="mb-2 text-xs font-semibold text-blue-900 dark:text-blue-100">
                        {{ __('Note importante :') }}
                    </p>
                    <p class="text-xs text-blue-700 dark:text-blue-300">
                        {{ __('Les exemples ci-dessous utilisent la syntaxe Linux/Mac. Pour Windows PowerShell, utilisez la syntaxe ci-dessous.') }}
                    </p>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Windows PowerShell - Créer un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>$body = @{
    name = "Formulaire de contact"
    call_center_id = 1
    fields = @(
        @{
            name = "email"
            type = "email"
            label = "Email"
            required = $true
        }
    )
} | ConvertTo-Json -Depth 10

curl.exe -X POST "{{ url('/api/forms') }}" `
  -H "Authorization: Bearer votre_token_ici" `
  -H "Content-Type: application/json" `
  -d $body</code></pre>
                        <p class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                            {{ __('Alternative simple (une ligne) :') }}
                        </p>
                        <pre class="mt-1 overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>curl.exe -X POST "{{ url('/api/forms') }}" -H "Authorization: Bearer votre_token_ici" -H "Content-Type: application/json" -d '{\"name\":\"Formulaire\",\"call_center_id\":1,\"fields\":[{\"name\":\"email\",\"type\":\"email\",\"label\":\"Email\",\"required\":true}]}'</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('cURL - Créer un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>curl -X POST "{{ url('/api/forms') }}" \
  -H "Authorization: Bearer votre_token_ici" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Formulaire de contact\",
    \"description\": \"Formulaire pour capturer les leads\",
    \"call_center_id\": 1,
    \"fields\": [
      {
        \"name\": \"email\",
        \"type\": \"email\",
        \"label\": \"Email\",
        \"placeholder\": \"votre@email.com\",
        \"required\": true
      },
      {
        \"name\": \"name\",
        \"type\": \"text\",
        \"label\": \"Nom\",
        \"required\": true,
        \"validation_rules\": {
          \"min_length\": 2,
          \"max_length\": 50
        }
      },
      {
        \"name\": \"phone\",
        \"type\": \"tel\",
        \"label\": \"Téléphone\",
        \"required\": false
      }
    ],
    \"is_active\": true
  }"</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('cURL - Créer un profil SMTP') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>curl -X POST "{{ url('/api/smtp-profiles') }}" \
  -H "Authorization: Bearer votre_token_ici" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"SMTP Gmail\",
    \"host\": \"smtp.gmail.com\",
    \"port\": 587,
    \"encryption\": \"tls\",
    \"username\": \"votre@email.com\",
    \"password\": \"votre_mot_de_passe\",
    \"from_address\": \"noreply@example.com\",
    \"from_name\": \"Lead Manager\",
    \"is_active\": true
  }"</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('cURL - Créer un template d\'email') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>curl -X POST "{{ url('/api/email-templates') }}" \
  -H "Authorization: Bearer votre_token_ici" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Confirmation d'inscription\",
    \"subject\": \"Confirmez votre inscription\",
    \"body_html\": \"&lt;h1&gt;Bonjour @{{name}}&lt;/h1&gt;&lt;p&gt;Cliquez sur le lien pour confirmer...&lt;/p&gt;\",
    \"body_text\": \"Bonjour @{{name}}\\n\\nCliquez sur le lien pour confirmer...\",
    \"variables\": [\"name\", \"email\", \"confirmation_link\"]
  }"</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('cURL - Liste des formulaires') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>curl -X GET "{{ url('/api/forms') }}" \
  -H "Authorization: Bearer votre_token_ici" \
  -H "Content-Type: application/json"</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('cURL - Voir un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>curl -X GET "{{ url('/api/forms/1') }}" \
  -H "Authorization: Bearer votre_token_ici" \
  -H "Content-Type: application/json"</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('cURL - Modifier un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>curl -X PUT "{{ url('/api/forms/1') }}" \
  -H "Authorization: Bearer votre_token_ici" \
  -H "Content-Type: application/json" \
  -d "{
    \"name\": \"Formulaire de contact mis à jour\",
    \"is_active\": false
  }"</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('cURL - Supprimer un formulaire') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>curl -X DELETE "{{ url('/api/forms/1') }}" \
  -H "Authorization: Bearer votre_token_ici" \
  -H "Content-Type: application/json"</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('JavaScript (Fetch)') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>fetch('{{ url('/api/forms') }}', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer votre_token_ici',
    'Content-Type': 'application/json'
  }
})
.then(response => response.json())
.then(data => console.log(data));</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('PHP (Guzzle)') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>$client = new \GuzzleHttp\Client();

$response = $client->request('GET', '{{ url('/api/forms') }}', [
    'headers' => [
        'Authorization' => 'Bearer votre_token_ici',
        'Content-Type' => 'application/json',
    ]
]);

$data = json_decode($response->getBody(), true);</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Gestion des tokens API -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Gestion des tokens API') }}
            </h2>
            <p class="mb-4 text-neutral-600 dark:text-neutral-400">
                {{ __('Vous pouvez gérer vos tokens API directement via l\'API. Cela est utile pour l\'automatisation et l\'intégration dans vos applications.') }}
            </p>

            <div class="space-y-6">
                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Liste des tokens') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            GET /api/tokens
                        </code>
                        <p class="mt-2 text-xs text-neutral-600 dark:text-neutral-400">
                            {{ __('Retourne tous les tokens de l\'utilisateur authentifié. Le token complet n\'est jamais retourné pour des raisons de sécurité.') }}
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Créer un token') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            POST /api/tokens
                        </code>
                        <pre class="mt-2 overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>{
  "name": "Token pour mon application",
  "expires_at": "2025-12-31T23:59:59Z"
}</code></pre>
                        <p class="mt-2 text-xs text-neutral-600 dark:text-neutral-400">
                            {{ __('Note : Le token complet n\'est retourné qu\'une seule fois lors de la création. Assurez-vous de le sauvegarder.') }}
                        </p>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Supprimer un token') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <code class="block text-sm text-neutral-800 dark:text-neutral-200">
                            DELETE /api/tokens/{id}
                        </code>
                    </div>
                </div>
            </div>
        </section>

        <!-- Exemples de réponses -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Exemples de réponses') }}
            </h2>

            <div class="space-y-4">
                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Réponse de succès (200)') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>{
  "data": {
    "id": 1,
    "name": "Formulaire de contact",
    "description": "Formulaire pour capturer les leads",
    "call_center_id": 1,
    "fields": [...],
    "created_at": "2025-01-01T00:00:00.000000Z"
  }
}</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Réponse d\'erreur de validation (422)') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>{
  "message": "Erreur de validation",
  "errors": {
    "name": ["Le champ nom est obligatoire."],
    "call_center_id": ["Le centre d'appels sélectionné n'est pas valide."]
  }
}</code></pre>
                    </div>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Réponse d\'erreur d\'authentification (401)') }}
                    </h3>
                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                        <pre class="overflow-x-auto text-xs text-neutral-700 dark:text-neutral-300"><code>{
  "message": "Token d'authentification manquant"
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <!-- Codes de réponse -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Codes de réponse HTTP') }}
            </h2>
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <span class="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-800 dark:bg-green-900/20 dark:text-green-400">200</span>
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Succès') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">201</span>
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Ressource créée avec succès') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded bg-yellow-100 px-2 py-1 text-xs font-semibold text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400">401</span>
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Non authentifié - Token manquant ou invalide') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-800 dark:bg-red-900/20 dark:text-red-400">403</span>
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Accès interdit') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-800 dark:bg-red-900/20 dark:text-red-400">404</span>
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Ressource non trouvée') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded bg-orange-100 px-2 py-1 text-xs font-semibold text-orange-800 dark:bg-orange-900/20 dark:text-orange-400">422</span>
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">{{ __('Erreur de validation') }}</span>
                </div>
            </div>
        </section>

        <!-- Bonnes pratiques et limites -->
        <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
            <h2 class="mb-4 text-xl font-semibold text-neutral-900 dark:text-neutral-100">
                {{ __('Bonnes pratiques et limites') }}
            </h2>
            <div class="space-y-4">
                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Sécurité des tokens') }}
                    </h3>
                    <ul class="list-disc space-y-1 pl-6 text-sm text-neutral-600 dark:text-neutral-400">
                        <li>{{ __('Ne partagez jamais vos tokens API publiquement') }}</li>
                        <li>{{ __('Utilisez des variables d\'environnement pour stocker vos tokens') }}</li>
                        <li>{{ __('Créez des tokens avec des dates d\'expiration pour limiter les risques') }}</li>
                        <li>{{ __('Supprimez les tokens non utilisés régulièrement') }}</li>
                    </ul>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Gestion des erreurs') }}
                    </h3>
                    <ul class="list-disc space-y-1 pl-6 text-sm text-neutral-600 dark:text-neutral-400">
                        <li>{{ __('Toujours vérifier les codes de statut HTTP') }}</li>
                        <li>{{ __('Gérer les erreurs 401 (token expiré) en régénérant un nouveau token') }}</li>
                        <li>{{ __('Valider les données avant de les envoyer à l\'API') }}</li>
                        <li>{{ __('Implémenter une logique de retry pour les erreurs temporaires') }}</li>
                    </ul>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Limites de taux') }}
                    </h3>
                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('L\'API applique des limites de taux pour protéger le serveur. Si vous dépassez ces limites, vous recevrez une réponse 429 (Too Many Requests).') }}
                    </p>
                </div>

                <div>
                    <h3 class="mb-2 font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ __('Permissions') }}
                    </h3>
                    <ul class="list-disc space-y-1 pl-6 text-sm text-neutral-600 dark:text-neutral-400">
                        <li>{{ __('Les Super Admins peuvent accéder à toutes les ressources') }}</li>
                        <li>{{ __('Les propriétaires de centres d\'appels ne peuvent gérer que les ressources de leur centre') }}</li>
                        <li>{{ __('Les agents n\'ont pas accès à l\'API') }}</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
</div>

