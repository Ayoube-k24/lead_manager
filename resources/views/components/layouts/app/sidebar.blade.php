<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="me-5 flex items-center space-x-2 rtl:space-x-reverse" wire:navigate>
                <x-app-logo />
            </a>

            <flux:navlist variant="outline">
                <!-- Dashboard -->
                <flux:navlist.group :heading="__('Vue d\'ensemble')" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard*')" wire:navigate>{{ __('Dashboard') }}</flux:navlist.item>
                </flux:navlist.group>

                @if (auth()->user()?->role?->slug === 'super_admin')
                    <!-- Leads & Distribution -->
                    <flux:navlist.group :heading="__('Leads & Distribution')" class="grid">
                        <flux:navlist.item icon="user-group" :href="route('admin.leads')" :current="request()->routeIs('admin.leads*')" wire:navigate>{{ __('Tous les Leads') }}</flux:navlist.item>
                        <flux:navlist.item icon="table-cells" :href="route('admin.call-centers.leads')" :current="request()->routeIs('admin.call-centers.leads')" wire:navigate>{{ __('Leads par centre') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Centres d'appels -->
                    <flux:navlist.group :heading="__('Centres d\'appels')" class="grid">
                        <flux:navlist.item icon="building-office-2" :href="route('admin.call-centers')" :current="request()->routeIs('admin.call-centers*')" wire:navigate>{{ __('Centres d\'appels') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Configuration -->
                    <flux:navlist.group :heading="__('Configuration')" class="grid">
                        <flux:navlist.item icon="document-text" :href="route('admin.forms')" :current="request()->routeIs('admin.forms*')" wire:navigate>{{ __('Formulaires') }}</flux:navlist.item>
                        <flux:navlist.item icon="envelope" :href="route('admin.smtp-profiles')" :current="request()->routeIs('admin.smtp-profiles*')" wire:navigate>{{ __('Profils SMTP') }}</flux:navlist.item>
                        <flux:navlist.item icon="document-duplicate" :href="route('admin.email-templates')" :current="request()->routeIs('admin.email-templates*')" wire:navigate>{{ __('Templates d\'email') }}</flux:navlist.item>
                        <flux:navlist.item icon="tag" :href="route('admin.tags')" :current="request()->routeIs('admin.tags*')" wire:navigate>{{ __('Tags') }}</flux:navlist.item>
                        <flux:navlist.item icon="check-circle" :href="route('admin.statuses')" :current="request()->routeIs('admin.statuses*')" wire:navigate>{{ __('Statuts') }}</flux:navlist.item>
                        <flux:navlist.item icon="chart-bar" :href="route('admin.scoring')" :current="request()->routeIs('admin.scoring*')" wire:navigate>{{ __('Scoring') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Analytics & Reporting -->
                    <flux:navlist.group :heading="__('Analytics & Reporting')" class="grid">
                        <flux:navlist.item icon="chart-bar" :href="route('admin.statistics')" :current="request()->routeIs('admin.statistics*')" wire:navigate>{{ __('Statistiques') }}</flux:navlist.item>
                        <flux:navlist.item icon="document-text" :href="route('admin.audit-logs')" :current="request()->routeIs('admin.audit-logs*')" wire:navigate>{{ __('Journal d\'Audit') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Intégrations -->
                    <flux:navlist.group :heading="__('Intégrations')" class="grid">
                        <flux:navlist.item icon="key" :href="route('admin.api-tokens')" :current="request()->routeIs('admin.api*')" wire:navigate>{{ __('API & Tokens') }}</flux:navlist.item>
                        <flux:navlist.item icon="globe-alt" :href="route('admin.webhooks')" :current="request()->routeIs('admin.webhooks*')" wire:navigate>{{ __('Webhooks') }}</flux:navlist.item>
                        <flux:navlist.item icon="arrow-down-tray" :href="route('admin.mailwizz.index')" :current="request()->routeIs('admin.mailwizz*')" wire:navigate>{{ __('MailWizz') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                @if (auth()->user()?->role?->slug === 'call_center_owner')
                    <!-- Leads & Distribution -->
                    <flux:navlist.group :heading="__('Leads & Distribution')" class="grid">
                        <flux:navlist.item icon="user-group" :href="route('owner.leads')" :current="request()->routeIs('owner.leads*')" wire:navigate>{{ __('Leads') }}</flux:navlist.item>
                        <flux:navlist.item icon="arrows-right-left" :href="route('owner.distribution')" :current="request()->routeIs('owner.distribution*')" wire:navigate>{{ __('Distribution') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Équipe -->
                    <flux:navlist.group :heading="__('Équipe')" class="grid">
                        <flux:navlist.item icon="users" :href="route('owner.agents')" :current="request()->routeIs('owner.agents*')" wire:navigate>{{ __('Agents') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Configuration -->
                    <flux:navlist.group :heading="__('Configuration')" class="grid">
                        <flux:navlist.item icon="tag" :href="route('owner.tags')" :current="request()->routeIs('owner.tags*')" wire:navigate>{{ __('Tags') }}</flux:navlist.item>
                        <flux:navlist.item icon="check-circle" :href="route('owner.statuses')" :current="request()->routeIs('owner.statuses*')" wire:navigate>{{ __('Statuts') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Analytics -->
                    <flux:navlist.group :heading="__('Analytics')" class="grid">
                        <flux:navlist.item icon="chart-bar" :href="route('owner.statistics')" :current="request()->routeIs('owner.statistics*')" wire:navigate>{{ __('Statistiques') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                @if (auth()->user()?->role?->slug === 'supervisor')
                    <!-- Équipe -->
                    <flux:navlist.group :heading="__('Équipe')" class="grid">
                        <flux:navlist.item icon="users" :href="route('supervisor.agents')" :current="request()->routeIs('supervisor.agents*')" wire:navigate>{{ __('Mes Agents') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Leads -->
                    <flux:navlist.group :heading="__('Leads')" class="grid">
                        <flux:navlist.item icon="user-group" :href="route('supervisor.leads')" :current="request()->routeIs('supervisor.leads*')" wire:navigate>{{ __('Leads de l\'équipe') }}</flux:navlist.item>
                    </flux:navlist.group>

                    <!-- Analytics -->
                    <flux:navlist.group :heading="__('Analytics')" class="grid">
                        <flux:navlist.item icon="chart-bar" :href="route('supervisor.statistics')" :current="request()->routeIs('supervisor.statistics*')" wire:navigate>{{ __('Statistiques') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                @if (auth()->user()?->role?->slug === 'agent')
                    <!-- Mes Leads -->
                    <flux:navlist.group :heading="__('Mes Leads')" class="grid">
                        <flux:navlist.item icon="user-group" :href="route('agent.leads')" :current="request()->routeIs('agent.leads*')" wire:navigate>{{ __('Mes Leads') }}</flux:navlist.item>
                        <flux:navlist.item icon="calendar" :href="route('agent.reminders.calendar')" :current="request()->routeIs('agent.reminders*')" wire:navigate>{{ __('Calendrier des Rappels') }}</flux:navlist.item>
                    </flux:navlist.group>
                @endif

                <!-- Paramètres -->
                <flux:navlist.group :heading="__('Paramètres')" class="grid">
                    <flux:navlist.item icon="bell" :href="route('settings.alerts')" :current="request()->routeIs('settings.alerts*')" wire:navigate>{{ __('Alertes') }}</flux:navlist.item>
                </flux:navlist.group>
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown class="hidden lg:block" position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon:trailing="chevrons-up-down"
                    data-test="sidebar-menu-button"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>{{ __('Settings') }}</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full" data-test="logout-button">
                            {{ __('Log Out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
