<div class="app-page">
    <div wire:loading.flex class="fixed inset-x-0 top-0 z-[60] items-center justify-center gap-2 bg-teal-700 px-4 py-2 text-sm font-medium text-white shadow-lg" role="status" aria-live="polite">
        <span class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span> Filtering data…
    </div>
    <div class="page-heading">
        <div>
            <h1 class="page-title">Clinic announcements</h1>
            <p class="page-subtitle">Post vaccine day schedules, temporary closures, campaigns, and stock advisories.</p>
        </div>
    </div>

    <div class="grid gap-6 {{ auth()->user()->canManageAnnouncements() && ! $viewAll ? 'xl:grid-cols-[1fr_360px]' : '' }}">
        <section class="app-card">
            <div class="app-card-header">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="app-card-title">{{ $viewAll ? 'All announcements' : 'Latest announcements' }}</h2>
                    @unless ($viewAll)
                        <a href="{{ route('announcements.all', ['region_id' => $region_id, 'province_id' => $province_id, 'municipality_id' => $municipality_id, 'barangay_id' => $barangay_id]) }}" class="app-button-secondary !px-3 !py-1.5 !text-xs">View all</a>
                    @endunless
                    @if ($viewAll)
                        <div class="flex flex-wrap items-center gap-3">
                            <label class="flex items-center gap-2 text-sm text-zinc-500">
                                <span>Date</span>
                                <input type="date" wire:model.live.debounce.400ms="dateFilter" class="app-input !w-auto !py-1.5">
                            </label>
                            <label class="flex items-center gap-2 text-sm text-zinc-500">
                                <span>Rows per page</span>
                                <select wire:model.live.debounce.400ms="perPage" class="app-input !w-auto !py-1.5">
                                    @foreach ([10, 15, 25, 50] as $option)
                                        <option value="{{ $option }}">{{ $option }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                    @endif
                </div>
            </div>
            @if (auth()->user()->isSuperAdmin() || auth()->user()->isMunicipalAdmin())
                <div class="px-4">
                    <x-location-filters mode="wire" :regions="$regions" :provinces="$provinces" :municipalities="$municipalities" :barangays="$barangays" :region-value="$region_id" :province-value="$province_id" :municipality-value="$municipality_id" :barangay-value="$barangay_id" region-model="region_id" province-model="province_id" municipality-model="municipality_id" barangay-model="barangay_id" />
                </div>
            @endif
            @if ($viewAll)
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 font-medium">#</th>
                                <th class="px-4 py-3 font-medium">Title</th>
                                <th class="px-4 py-3 font-medium">Category</th>
                                <th class="px-4 py-3 font-medium">Start date</th>
                                <th class="px-4 py-3 font-medium">Location</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($announcements as $announcement)
                                <tr class="app-table-row">
                                    <td>{{ $announcements->firstItem() + $loop->index }}</td>
                                    <td class="font-medium text-slate-950 dark:text-white">{{ $announcement->title }}</td>
                                    <td class="capitalize">{{ $announcement->category }}</td>
                                    <td>{{ $announcement->starts_on->format('M d, Y') }}</td>
                                    <td>{{ $announcement->barangay?->name ?? $announcement->municipality?->name ?? $announcement->province?->name ?? $announcement->region?->name ?? 'All regions' }}</td>
                                    <td>
                                        <span class="status-pill {{ $announcement->active ? 'status-verified' : 'status-rejected' }}">
                                            {{ $announcement->active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-500">No announcements posted yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
            <div class="space-y-4 p-4">
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
                                    @elseif ($announcement->municipality)
                                        | {{ $announcement->municipality->name }}
                                    @elseif ($announcement->province)
                                        | {{ $announcement->province->name }}
                                    @elseif ($announcement->region)
                                        | {{ $announcement->region->name }}
                                    @else
                                        | All regions
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
            @endif
            @if ($viewAll)<div class="mt-4 px-4 pb-4">{{ $announcements->links() }}</div>@endif
        </section>

        @if (auth()->user()->canManageAnnouncements() && ! $viewAll)
            <form wire:submit="save" class="app-panel grid content-start gap-4">
                <h2 class="app-card-title">Post announcement</h2>
                <x-form-field label="Title" name="title" :value="$title" wire:model="title" />
                <x-form-field label="Category" name="category" type="select" :options="['schedule' => 'Schedule', 'closure' => 'Closure', 'campaign' => 'Campaign', 'stock' => 'Stock advisory']" :value="$category" wire:model="category" />
                <x-form-field label="Audience" name="audience" type="select" :options="['all' => 'All users', 'parents' => 'Parents only', 'staff' => 'Staff only']" :value="$audience" wire:model="audience" />
                <x-form-field label="Start date" name="starts_on" type="date" :value="$starts_on" wire:model="starts_on" />
                <x-form-field label="End date" name="ends_on" type="date" :value="$ends_on" wire:model="ends_on" />
                <x-form-field label="Location" name="location" :value="$location" wire:model="location" />
                <x-form-field label="Message" name="message" type="textarea" :value="$message" wire:model="message" />
                @if (auth()->user()->isMunicipalAdmin())
                    <label class="space-y-1.5">
                        <span class="text-sm font-medium">Barangay target</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" wire:click="selectAllBarangays" class="{{ in_array('all', $barangay_ids, true) ? 'app-button-primary' : 'app-button-secondary' }}">
                                All barangays
                            </button>
                            <flux:modal.trigger name="barangay-targets">
                                <flux:button type="button" variant="ghost">Select specific barangays</flux:button>
                            </flux:modal.trigger>
                        </div>
                        <span class="text-xs text-zinc-500">
                            @if (in_array('all', $barangay_ids, true))
                                This announcement will be posted to all barangays in {{ auth()->user()->municipality?->name ?? 'your municipality' }}.
                            @else
                                {{ count($barangay_ids) }} barangay{{ count($barangay_ids) === 1 ? '' : 's' }} selected.
                            @endif
                        </span>
                    </label>
                @endif
                <flux:separator />
                <button class="app-button-primary">Post announcement</button>
            </form>
            @if (auth()->user()->isMunicipalAdmin())
                <flux:modal name="barangay-targets" class="md:w-[34rem]">
                    <div class="space-y-5">
                        <div>
                            <flux:heading size="lg">Select barangays</flux:heading>
                            <flux:text class="mt-1">Choose one or more barangays for this announcement.</flux:text>
                        </div>
                        <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-slate-200 p-3 dark:border-zinc-700">
                            @foreach ($barangays as $barangay)
                                <label class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-2 hover:bg-slate-50 dark:hover:bg-zinc-800">
                                    <input type="checkbox" wire:click="toggleBarangay('{{ $barangay->id }}')" @checked(in_array($barangay->id, $barangay_ids, true))>
                                    <span class="text-sm">{{ $barangay->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="flex justify-end gap-2">
                            <flux:modal.close><flux:button type="button" variant="ghost">Cancel</flux:button></flux:modal.close>
                            <flux:modal.close><flux:button type="button" variant="primary">Done</flux:button></flux:modal.close>
                        </div>
                    </div>
                </flux:modal>
            @endif
        @endif
    </div>
</div>
