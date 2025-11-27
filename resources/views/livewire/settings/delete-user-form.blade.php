<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component {
    public function deleteUser(): void
    {
        $this->addError('disabled', __('Account deletion is disabled by the administrator.'));
    }
}; ?>

<section class="mt-10 space-y-4">
    <flux:callout variant="neutral">
        <flux:heading>{{ __('Suppression du compte désactivée') }}</flux:heading>
        <flux:subheading>
            {{ __('Cette fonctionnalité a été désactivée. Veuillez contacter un administrateur si vous devez fermer votre compte.') }}
        </flux:subheading>
    </flux:callout>
</section>
