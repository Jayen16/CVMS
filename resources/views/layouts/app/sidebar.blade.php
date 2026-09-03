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

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
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
                    @if (auth()->user()->canViewDefaulters())
                        <flux:sidebar.item icon="bell-alert" :href="route('defaulters.index')" :current="request()->routeIs('defaulters.*')" wire:navigate>
                            {{ __('Defaulters') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->canManageAnnouncements() || auth()->user()->isParent())
                        <flux:sidebar.item icon="megaphone" :href="route('announcements.index')" :current="request()->routeIs('announcements.*')" wire:navigate>
                            {{ __('Announcements') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->canViewAefiReports())
                        <flux:sidebar.item icon="exclamation-triangle" :href="route('aefi-reports.index')" :current="request()->routeIs('aefi-reports.*')" wire:navigate>
                            {{ __('AEFI') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->canViewDuplicates())
                        <flux:sidebar.item icon="squares-2x2" :href="route('duplicates.index')" :current="request()->routeIs('duplicates.*')" wire:navigate>
                            {{ __('Duplicates') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->canManageBarangayStaff())
                        <flux:sidebar.item icon="user-plus" :href="route('nurses.index')" :current="request()->routeIs('nurses.*')" wire:navigate>
                            {{ auth()->user()->canManageBarangayAdmins() ? __('Barangay Admins') : __('Nurses') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->isSuperAdmin() || auth()->user()->isBarangayAdmin() || auth()->user()->isNurse())
                        <flux:sidebar.item icon="arrow-path" :href="route('sync.index')" :current="request()->routeIs('sync.*')" wire:navigate>
                            {{ __('Sync Data') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->canManagePlatform())
                        <flux:sidebar.item icon="calendar-days" :href="route('vaccine-schedules.index')" :current="request()->routeIs('vaccine-schedules.*')" wire:navigate>
                            {{ __('Schedules') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->canManageInventory())
                        <flux:sidebar.item icon="archive-box" :href="route('vaccine-inventory.index')" :current="request()->routeIs('vaccine-inventory.*')" wire:navigate>
                            {{ __('Vaccine Inventory') }}
                        </flux:sidebar.item>
                    @endif
                    @if (auth()->user()->canViewOversight())
                        <flux:sidebar.item icon="chart-bar" :href="route('reports.index')" :current="request()->routeIs('reports.*')" wire:navigate>
                            {{ __('Reports') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="hidden px-3 pb-3 lg:block">
                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="app-appearance-toggle">
                    <flux:radio value="light" icon="sun">Day</flux:radio>
                    <flux:radio value="dark" icon="moon">Night</flux:radio>
                </flux:radio.group>
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="border-b border-slate-200 bg-white/95 dark:border-zinc-800 dark:bg-zinc-950/95 lg:hidden">
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
