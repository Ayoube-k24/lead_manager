<div>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" size="sm" icon="bell" class="relative">
            @if ($unreadCount > 0)
                <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-bold text-white">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu>
            <div class="max-h-96 w-80 overflow-y-auto">
                <div class="flex items-center justify-between border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                    <h3 class="text-sm font-semibold">{{ __('Notifications') }}</h3>
                    @if ($unreadCount > 0)
                        <flux:button 
                            wire:click="markAllAsRead" 
                            variant="ghost" 
                            size="sm"
                            class="text-xs"
                        >
                            {{ __('Tout marquer comme lu') }}
                        </flux:button>
                    @endif
                </div>

                <div class="divide-y divide-neutral-200 dark:divide-neutral-700">
                    <!-- Placeholder for notifications -->
                    <div class="px-4 py-8 text-center">
                        <svg class="mx-auto h-12 w-12 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <p class="mt-2 text-sm text-neutral-500 dark:text-neutral-400">{{ __('Aucune notification') }}</p>
                    </div>
                </div>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
