<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="app-shell">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-slate-200 bg-white/95 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/95">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            @php
                $sidebarUser = auth()->user()->loadMissing([
                    'barangay.municipalityRelation.province.region',
                    'municipality.province.region',
                ]);
                $sidebarBarangay = $sidebarUser->barangay;
                $sidebarMunicipality = $sidebarBarangay?->municipalityRelation ?? $sidebarUser->municipality;
                $sidebarProvince = $sidebarMunicipality?->province;
                $sidebarRegion = $sidebarProvince?->region;
                $sidebarPrimaryLocation = collect([$sidebarBarangay?->name, $sidebarMunicipality?->name])->filter()->implode(' · ');
                $sidebarSecondaryLocation = collect([$sidebarProvince?->name, $sidebarRegion?->name])->filter()->implode(' · ');
            @endphp

            @if ($sidebarRegion || $sidebarProvince || $sidebarMunicipality || $sidebarBarangay)
                <div class="space-y-0.5 px-3 py-2 text-[10px] leading-4 text-slate-500 dark:text-zinc-400">
                    @if ($sidebarPrimaryLocation !== '')
                        <div class="truncate font-medium text-slate-600 dark:text-zinc-300">{{ $sidebarPrimaryLocation }}</div>
                    @endif
                    @if ($sidebarSecondaryLocation !== '')
                        <div class="truncate">{{ $sidebarSecondaryLocation }}</div>
                    @endif
                </div>
            @endif

            <flux:sidebar.nav>
                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                @if (auth()->user()->canViewChildrenRegistry()
                    || auth()->user()->canViewVerificationQueue())
                    <flux:sidebar.group expandable :heading="__('Child Records')" class="grid">
                        @if (auth()->user()->canViewChildrenRegistry())
                            <flux:sidebar.item icon="users" :href="route('children.index')" :current="request()->routeIs('children.*')" wire:navigate>
                                {{ __('Children') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()->canViewVerificationQueue())
                            <flux:sidebar.item icon="clipboard-document-check" :href="route('verification-queue.index')" :current="request()->routeIs('verification-queue.*')" wire:navigate>
                                {{ __('Verification Queue') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif

                @if (auth()->user()->canManagePlatform() || auth()->user()->canViewInventory())
                    <flux:sidebar.group expandable :heading="__('Inventory')" class="grid">
                        @if (auth()->user()->canManagePlatform())
                            <flux:sidebar.item icon="calendar-days" :href="route('vaccine-schedules.index')" :current="request()->routeIs('vaccine-schedules.*')" wire:navigate>
                                {{ __('Schedules') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()->canViewInventory())
                            <flux:sidebar.item icon="archive-box" :href="route('vaccine-inventory.index')" :current="request()->routeIs('vaccine-inventory.*')" wire:navigate>
                                {{ __('Vaccine Inventory') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif

                @if ((auth()->user()->canManageBarangayStaff() && ! auth()->user()->isSuperAdmin()) || auth()->user()->isSuperAdmin())
                    <flux:sidebar.group expandable :heading="__('Administration')" class="grid">
                        @if (auth()->user()->canManageBarangayStaff() && ! auth()->user()->isSuperAdmin())
                            <flux:sidebar.item icon="user-plus" :href="route(auth()->user()->canManageBarangayAdmins() ? 'municipal-admins.index' : 'nurses.index')" :current="request()->routeIs('nurses.*', 'municipal-admins.*')" wire:navigate>
                                {{ auth()->user()->canManageBarangayAdmins() ? __('Barangay Admins') : __('Nurses') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()->isSuperAdmin())
                            <flux:sidebar.item icon="user-group" :href="route('groups.index')" :current="request()->routeIs('groups.*')" wire:navigate>
                                {{ __('Group Management') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif

                @if (auth()->user()->canViewOversight() || auth()->user()->canViewDefaulters())
                    <flux:sidebar.group expandable :heading="__('Insights & Oversight')" class="grid">
                        @if (auth()->user()->canViewDefaulters())
                            <flux:sidebar.item icon="calendar-days" :href="route('schedule-monitoring.index')" :current="request()->routeIs('schedule-monitoring.*')" wire:navigate>
                                {{ __('Schedule monitoring') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="chart-bar" :href="route('predictive-analytics.index')" :current="request()->routeIs('predictive-analytics.*')" wire:navigate>
                                {{ __('Vaccine demand forecast') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()->canViewOversight())
                            <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                                {{ __('Reports') }}
                            </flux:sidebar.item>
                            <flux:sidebar.item icon="clipboard-document-list" :href="route('population-background.index')" :current="request()->routeIs('population-background.*')" wire:navigate>
                                {{ __('Population Background') }}
                            </flux:sidebar.item>
                        @endif
                    </flux:sidebar.group>
                @endif
                @if (auth()->user()->canArchiveReports() || auth()->user()->canViewOversight() || auth()->user()->canArchiveChildren())
                    <flux:sidebar.group expandable :heading="__('Data Management')" class="grid">
                        @if (auth()->user()->canViewOversight())
                            <flux:sidebar.item icon="list-bullet" :href="route('audit-logs.index')" :current="request()->routeIs('audit-logs.*')" wire:navigate>
                                {{ __('Audit Logs') }}
                            </flux:sidebar.item>
                        @endif
                        @if (auth()->user()->canArchiveChildren())
                            <flux:sidebar.item icon="archive-box" :href="route('children.archive.index')" :current="request()->routeIs('children.archive.*')" wire:navigate>
                                {{ __('Archived Children') }}
                            </flux:sidebar.item>
                        @endif
                        <flux:sidebar.item icon="archive-box" :href="route('archives.index')" :current="request()->routeIs('archives.*')" wire:navigate>
                            {{ __('Archive Center') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif

                @if (auth()->user()->isSuperAdmin() || auth()->user()->isMunicipalAdmin() || auth()->user()->isBarangayAdmin())
                    <flux:sidebar.group expandable :heading="__('Operations')" class="grid">
                        <flux:sidebar.item icon="arrow-path" :href="route('sync.index')" :current="request()->routeIs('sync.*')" wire:navigate>
                            {{ __('Sync Data') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="hidden px-3 pb-3 lg:block">
                <x-notification-bell />

                <div class="mt-3">
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="app-appearance-toggle">
                    <flux:radio value="light" icon="sun">Day</flux:radio>
                    <flux:radio value="dark" icon="moon">Night</flux:radio>
                </flux:radio.group>
                </div>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <x-notification-bell drawer-only />

        <!-- Mobile User Menu -->
        <flux:header class="border-b border-slate-200 bg-white/95 dark:border-zinc-800 dark:bg-zinc-950/95 lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <x-notification-bell compact />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <div class="px-2 py-2">
                        <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="app-appearance-toggle w-full">
                            <flux:radio value="light" icon="sun">Day</flux:radio>
                            <flux:radio value="dark" icon="moon">Night</flux:radio>
                        </flux:radio.group>
                    </div>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
