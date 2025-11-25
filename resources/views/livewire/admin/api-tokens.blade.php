<?php

use App\Models\ApiToken;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public string $tokenName = '';
    public ?string $expiresAt = null;
    public bool $showCreateModal = false;
    public ?string $newToken = null;
    public bool $showTokenModal = false;

    public function mount(): void
    {
        //
    }

    public function openCreateModal(): void
    {
        $this->reset(['tokenName', 'expiresAt', 'newToken']);
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->showTokenModal = false;
        $this->reset(['tokenName', 'expiresAt', 'newToken']);
    }

    public function createToken(): void
    {
        $validated = $this->validate([
            'tokenName' => ['required', 'string', 'max:255'],
            'expiresAt' => ['nullable', 'date', 'after:now'],
        ]);

        $token = ApiToken::generate();
        $expiresAt = $validated['expiresAt'] ? now()->parse($validated['expiresAt']) : null;

        $apiToken = ApiToken::create([
            'user_id' => Auth::id(),
            'name' => $validated['tokenName'],
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);

        $this->newToken = $token;
        $this->showTokenModal = true;
        $this->reset(['tokenName', 'expiresAt']);
        $this->showCreateModal = false;

        session()->flash('message', __('Token API créé avec succès. Assurez-vous de le copier maintenant, vous ne pourrez plus le voir.'));
    }

    public function deleteToken(ApiToken $apiToken): void
    {
        if ($apiToken->user_id !== Auth::id()) {
            return;
        }

        $apiToken->delete();
        session()->flash('message', __('Token supprimé avec succès.'));
    }

    public function getTokensProperty()
    {
        return ApiToken::where('user_id', Auth::id())
            ->latest()
            ->get();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ __('Tokens API') }}</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                {{ __('Gérez vos tokens d\'authentification API pour accéder aux endpoints') }}
            </p>
        </div>
        <flux:button wire:click="openCreateModal" variant="primary">
            {{ __('Créer un token') }}
        </flux:button>
    </div>

    @if (session('message'))
        <flux:callout variant="success" icon="check-circle">
            {{ session('message') }}
        </flux:callout>
    @endif

    <!-- Liste des tokens -->
    <div class="rounded-xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-neutral-800">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="border-b border-neutral-200 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Nom') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Dernière utilisation') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Expire le') }}
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Créé le') }}
                        </th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                            {{ __('Actions') }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-200 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                    @forelse ($this->tokens as $token)
                        <tr>
                            <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-neutral-900 dark:text-neutral-100">
                                {{ $token->name }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $token->last_used_at ? $token->last_used_at->format('d/m/Y H:i') : __('Jamais') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                @if ($token->expires_at)
                                    @if ($token->isExpired())
                                        <span class="text-red-600 dark:text-red-400">{{ __('Expiré') }}</span>
                                    @else
                                        {{ $token->expires_at->format('d/m/Y H:i') }}
                                    @endif
                                @else
                                    <span class="text-neutral-400">{{ __('Jamais') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                {{ $token->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-right text-sm font-medium">
                                <flux:button
                                    wire:click="deleteToken({{ $token->id }})"
                                    wire:confirm="{{ __('Êtes-vous sûr de vouloir supprimer ce token ?') }}"
                                    variant="ghost"
                                    size="sm"
                                >
                                    {{ __('Supprimer') }}
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-sm text-neutral-500 dark:text-neutral-400">
                                {{ __('Aucun token API créé. Créez-en un pour commencer à utiliser l\'API.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal de création -->
    @if ($showCreateModal)
        <flux:modal name="create-token" wire:model="showCreateModal">
            <form wire:submit="createToken" class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold">{{ __('Créer un token API') }}</h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Donnez un nom à votre token pour l\'identifier facilement') }}
                    </p>
                </div>

                <flux:input wire:model.blur="tokenName" :label="__('Nom du token')" required autofocus />

                <flux:input wire:model.blur="expiresAt" type="datetime-local" :label="__('Date d\'expiration (optionnel)')" />

                <div class="flex items-center justify-end gap-3">
                    <flux:button type="button" wire:click="closeCreateModal" variant="ghost">
                        {{ __('Annuler') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ __('Créer') }}
                    </flux:button>
                </div>
            </form>
        </flux:modal>
    @endif

    <!-- Modal d'affichage du token -->
    @if ($showTokenModal && $newToken)
        <flux:modal name="show-token" wire:model="showTokenModal" :closeable="false">
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold text-green-600 dark:text-green-400">
                        {{ __('Token créé avec succès !') }}
                    </h2>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ __('Copiez ce token maintenant. Vous ne pourrez plus le voir après avoir fermé cette fenêtre.') }}
                    </p>
                </div>

                <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-700 dark:bg-neutral-900">
                    <code class="block break-all text-sm font-mono text-neutral-900 dark:text-neutral-100">
                        {{ $newToken }}
                    </code>
                </div>

                <flux:callout variant="warning" icon="exclamation-triangle">
                    {{ __('⚠️ Important : Assurez-vous de copier ce token maintenant. Il ne sera plus affiché après la fermeture de cette fenêtre.') }}
                </flux:callout>

                <div class="flex items-center justify-end">
                    <flux:button wire:click="closeCreateModal" variant="primary">
                        {{ __('J\'ai copié le token') }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif

    <!-- Lien vers la documentation -->
    <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-800 dark:bg-blue-900/20">
        <div class="flex items-start gap-4">
            <div class="flex-shrink-0">
                <svg class="h-6 w-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </div>
            <div class="flex-1">
                <h3 class="text-lg font-semibold text-blue-900 dark:text-blue-100">
                    {{ __('Documentation API') }}
                </h3>
                <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                    {{ __('Consultez la documentation complète de l\'API pour apprendre à utiliser tous les endpoints disponibles.') }}
                </p>
                <div class="mt-4">
                    @php
                        $docRoute = auth()->user()?->role?->slug === 'super_admin' 
                            ? route('admin.api.documentation') 
                            : route('owner.api.documentation');
                    @endphp
                    <flux:button href="{{ $docRoute }}" variant="primary" wire:navigate>
                        {{ __('Voir la documentation') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </div>
</div>

