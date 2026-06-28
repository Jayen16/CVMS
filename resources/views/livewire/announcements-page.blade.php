<div class="app-page">
    <div class="page-heading">
        <div>
            <h1 class="page-title">Clinic announcements</h1>
            <p class="page-subtitle">Post vaccine day schedules, temporary closures, campaigns, and stock advisories.</p>
        </div>
    </div>

    <div class="grid gap-6 {{ auth()->user()->canManageAnnouncements() ? 'xl:grid-cols-[1fr_360px]' : '' }}">
        <section class="app-card">
            <div class="app-card-header">
                <h2 class="app-card-title">Posted announcements</h2>
            </div>
            <div class="space-y-4">
                @forelse ($announcements as $announcement)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-950 dark:text-white">{{ $announcement->title }}</h3>
                                <div class="mt-1 text-sm text-zinc-500">
                                    {{ ucfirst($announcement->category) }} | {{ $announcement->audience }} | {{ $announcement->starts_on->format('M d, Y') }}
                                    @if ($announcement->ends_on)
                                        to {{ $announcement->ends_on->format('M d, Y') }}
                                    @endif
                                    @if ($announcement->barangay)
                                        | {{ $announcement->barangay->name }}
                                    @endif
                                </div>
                            </div>
                            <span class="status-pill {{ $announcement->active ? 'status-verified' : 'status-rejected' }}">
                                {{ $announcement->active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $announcement->message }}</p>
                        @if (auth()->user()->canManageAnnouncements())
                            <div class="mt-4 flex flex-wrap gap-2">
                                <button wire:click="toggle({{ $announcement->id }})" class="app-button-secondary !px-3 !py-1.5 !text-xs">{{ $announcement->active ? 'Deactivate' : 'Activate' }}</button>
                                <button wire:click="remove({{ $announcement->id }})" wire:confirm="Remove this announcement?" class="app-button-danger !px-3 !py-1.5 !text-xs">Delete</button>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="text-sm text-zinc-500">No announcements posted yet.</p>
                @endforelse
            </div>
            <div class="mt-4">{{ $announcements->links() }}</div>
        </section>

        @if (auth()->user()->canManageAnnouncements())
            <form wire:submit="save" class="app-panel grid content-start gap-4">
                <h2 class="app-card-title">Post announcement</h2>
                <x-form-field label="Title" name="title" :value="$title" wire:model="title" />
                <x-form-field label="Category" name="category" type="select" :options="['schedule' => 'Schedule', 'closure' => 'Closure', 'campaign' => 'Campaign', 'stock' => 'Stock advisory']" :value="$category" wire:model="category" />
                <x-form-field label="Audience" name="audience" type="select" :options="['all' => 'All users', 'parents' => 'Parents only', 'staff' => 'Staff only']" :value="$audience" wire:model="audience" />
                @if (auth()->user()->isSuperAdmin())
                    <x-form-field label="Barangay" name="barangay_id" type="select" :options="$barangays->pluck('name', 'id')" :value="$barangay_id" wire:model="barangay_id" />
                @endif
                <x-form-field label="Start date" name="starts_on" type="date" :value="$starts_on" wire:model="starts_on" />
                <x-form-field label="End date" name="ends_on" type="date" :value="$ends_on" wire:model="ends_on" />
                <x-form-field label="Location" name="location" :value="$location" wire:model="location" />
                <x-form-field label="Message" name="message" type="textarea" :value="$message" wire:model="message" />
                <button class="app-button-primary">Post announcement</button>
            </form>
        @endif
    </div>
</div>
