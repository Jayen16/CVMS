    <div class="app-page" x-data="{ archiveOpen: false, archiveAction: '', archiveName: '' }">
        <div class="page-heading">
            <div>
                <p class="eyebrow">Registry</p>
                <h1 class="page-title">Child profiles</h1>
                <p class="page-subtitle">Registry of children covered by the clinic barangays.</p>
            </div>
            @if (auth()->user()->canManageChildren())
                <a href="{{ route('children.create') }}" class="app-button-primary">New child</a>
            @endif
        </div>


        @if (! auth()->user()->isParent())
            <form method="GET" action="{{ route('children.index') }}" class="app-panel flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between" x-data="{ loading: false }" @submit="loading = true">
                <label class="grid flex-1 gap-2 text-sm">
                    <span class="font-medium text-slate-800 dark:text-zinc-100">Search child name</span>
                    <input type="search" name="name" value="{{ $nameSearch }}" class="app-input" placeholder="Search first, middle, or last name..." @input.debounce.500ms="loading = true; $el.form.requestSubmit()">
                </label>
                <label class="grid flex-1 gap-2 text-sm">
                    <span class="font-medium text-slate-800 dark:text-zinc-100">Filter by vaccination taken</span>
                    <select name="vaccine_type_id" class="app-input">
                        <option value="">All vaccinations</option>
                        @foreach ($vaccines as $vaccine)
                            <option value="{{ $vaccine->id }}" @selected((int) $selectedVaccineTypeId === $vaccine->id)>{{ $vaccine->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex gap-2">
                    <button class="app-button-primary inline-flex items-center gap-2" :disabled="loading"><span x-show="loading" x-cloak class="size-4 animate-spin rounded-full border-2 border-teal-200 border-t-white"></span><span x-text="loading ? 'Filtering…' : 'Filter'"></span></button>
                    <a href="{{ route('children.index') }}" class="app-button-secondary">Clear</a>
                </div>
            </form>
        @endif
        @if (auth()->user()->isParent())
            <div class="grid gap-3">
                @forelse ($children as $child)
                    <a href="{{ route('children.show', $child) }}" class="app-card flex items-center gap-3 p-4 transition hover:-translate-y-0.5 hover:border-teal-200 hover:bg-teal-50/40 dark:hover:border-teal-800 dark:hover:bg-zinc-800" wire:navigate>
                        <div class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 font-bold text-teal-700 ring-1 ring-teal-200 dark:bg-teal-950 dark:text-teal-300 dark:ring-teal-800">
                            @if ($child->photo_path)
                                <img src="{{ route('children.photo', $child) }}" alt="Photo of {{ $child->full_name }}" class="size-full object-cover">
                            @else
                                {{ str($child->first_name)->substr(0, 1) }}{{ str($child->last_name)->substr(0, 1) }}
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold text-slate-950 dark:text-white">{{ $child->full_name }}</p>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-zinc-400">{{ $child->ageLabel() }} · {{ $child->vaccinations->count() }} records</p>
                            <p class="mt-1 text-xs"><span class="font-medium text-emerald-600">● {{ $child->completed_doses_count }} verified</span><span class="ml-3 font-medium text-amber-600">● {{ $child->vaccinations->where('verification_status', 'pending')->count() }} pending</span></p>
                        </div>
                        <flux:icon.chevron-right class="size-4 shrink-0 text-slate-400" />
                    </a>
                @empty
                    <div class="app-card p-8 text-center text-sm text-zinc-500">No linked child profiles found.</div>
                @endforelse
            </div>
        @else
        <div class="grid gap-3 lg:hidden">
            @forelse ($children as $child)
                <article class="app-card p-4">
                    <div class="flex items-start gap-3">
                        <div class="size-12 shrink-0 overflow-hidden rounded-full bg-teal-100 ring-1 ring-teal-200 dark:bg-teal-950 dark:ring-teal-800">
                            @if ($child->photo_path)
                                <img src="{{ route('children.photo', $child) }}" alt="Photo of {{ $child->full_name }}" class="size-full object-cover">
                            @else
                                <div class="flex size-full items-center justify-center text-sm font-semibold text-teal-700 dark:text-teal-300" aria-label="No photo available for {{ $child->full_name }}">
                                    {{ str($child->first_name)->substr(0, 1) }}{{ str($child->last_name)->substr(0, 1) }}
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('children.show', $child) }}" class="break-words font-semibold text-teal-700 hover:underline dark:text-teal-300" wire:navigate>{{ $child->full_name }}</a>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">{{ ucfirst($child->sex) }} <span aria-hidden="true">·</span> Born {{ $child->birthdate->format('M d, Y') }}</p>
                        </div>
                        @if (auth()->user()->canArchiveChildren())
                            <div class="relative shrink-0" x-data="{ open: false }">
                                <button type="button" class="inline-flex size-8 items-center justify-center rounded-lg text-lg font-bold leading-none text-zinc-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-zinc-800 dark:hover:text-white" @click="open = !open" :aria-expanded="open.toString()" aria-label="Actions for {{ $child->full_name }}">•••</button>
                                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 top-9 z-10 min-w-36 rounded-lg border border-slate-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    <a href="{{ route('children.show', $child) }}" class="block rounded-md px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-zinc-800" wire:navigate>View profile</a>
                                    <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40" @click="archiveAction = @js(route('children.archive', $child->id)); archiveName = @js($child->full_name); archiveOpen = true; open = false">Archive</button>
                                </div>
                            </div>
                        @endif
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-slate-100 pt-3 text-sm dark:border-zinc-800">
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-zinc-400">Age</dt>
                            <dd class="mt-0.5 font-medium text-slate-800 dark:text-zinc-100">{{ $child->ageLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-zinc-400">Barangay</dt>
                            <dd class="mt-0.5 break-words font-medium text-slate-800 dark:text-zinc-100">{{ $child->barangay->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-zinc-400">Records</dt>
                            <dd class="mt-0.5 font-medium text-slate-800 dark:text-zinc-100">{{ $child->vaccinations->count() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500 dark:text-zinc-400">Completed doses</dt>
                            <dd class="mt-0.5 font-medium text-emerald-700 dark:text-emerald-300">{{ $child->completed_doses_count }} of {{ $child->total_doses_count }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <div class="app-card p-6 text-center text-sm text-zinc-500">{{ $nameSearch !== '' ? 'No child profiles match your search.' : ($selectedVaccineTypeId ? 'No child profiles match the selected vaccination filter.' : 'No child profiles found.') }}</div>
            @endforelse
        </div>

        <div class="app-card hidden overflow-x-auto lg:block">
            <table class="app-table">
                <thead>
                    <tr>
                        <th class="px-4 py-3 font-medium">Child</th>
                        <th class="px-4 py-3 font-medium">Age</th>
                        <th class="px-4 py-3 font-medium">Barangay</th>
                        <th class="px-4 py-3 font-medium">Records</th>
                        <th class="px-4 py-3 font-medium">Completed doses</th>
                        @if (auth()->user()->canArchiveChildren())
                            <th class="px-4 py-3 font-medium">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($children as $child)
                        <tr class="app-table-row">
                            <td>
                                <div class="flex items-center gap-3">
                                    <div class="size-11 shrink-0 overflow-hidden rounded-full bg-teal-100 ring-1 ring-teal-200 dark:bg-teal-950 dark:ring-teal-800">
                                        @if ($child->photo_path)
                                            <img src="{{ route('children.photo', $child) }}" alt="Photo of {{ $child->full_name }}" class="size-full object-cover">
                                        @else
                                            <div class="flex size-full items-center justify-center text-sm font-semibold text-teal-700 dark:text-teal-300" aria-label="No photo available for {{ $child->full_name }}">
                                                {{ str($child->first_name)->substr(0, 1) }}{{ str($child->last_name)->substr(0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('children.show', $child) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $child->full_name }}</a>
                                        <div class="text-xs text-zinc-500">{{ ucfirst($child->sex) }} | Born {{ $child->birthdate->format('M d, Y') }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $child->ageLabel() }}</td>
                            <td>{{ $child->barangay->name }}</td>
                            <td>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $child->vaccinations->count() }}</span>
                            </td>
                            <td>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-900">
                                    {{ $child->completed_doses_count }} out of {{ $child->total_doses_count }}
                                </span>
                            </td>
                            @if (auth()->user()->canArchiveChildren())
                                <td class="relative" x-data="{ open: false }">
                                    <button type="button" class="inline-flex size-8 items-center justify-center rounded-lg text-lg font-bold leading-none text-zinc-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-zinc-800 dark:hover:text-white" @click="open = !open" :aria-expanded="open.toString()" aria-label="Actions for {{ $child->full_name }}">•••</button>
                                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-3 top-11 z-10 min-w-36 rounded-lg border border-slate-200 bg-white p-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                        <a href="{{ route('children.show', $child) }}" class="block rounded-md px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-zinc-800">View profile</a>
                                        <button type="button" class="block w-full rounded-md px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40" @click="archiveAction = @js(route('children.archive', $child->id)); archiveName = @js($child->full_name); archiveOpen = true; open = false">Archive</button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->canArchiveChildren() ? 6 : 5 }}" class="px-4 py-8 text-center text-zinc-500">{{ $nameSearch !== '' ? 'No child profiles match your search.' : ($selectedVaccineTypeId ? 'No child profiles match the selected vaccination filter.' : 'No child profiles found.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @endif

        {{ $children->links() }}

        <div x-show="archiveOpen" x-cloak x-on:keydown.escape.window="archiveOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="archive-child-title">
            <div class="app-panel w-full max-w-md" @click.stop>
                <p class="eyebrow">Child Records</p>
                <h2 id="archive-child-title" class="app-card-title mt-1">Archive child record</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">Archive <span class="font-semibold" x-text="archiveName"></span>? Clinical history will be retained.</p>
                <form method="POST" x-bind:action="archiveAction" class="mt-5 grid gap-4">
                    @csrf
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Reason</span><select name="archive_reason" class="app-input" required><option value="">Choose a reason</option><option value="Inactive">Inactive</option><option value="Transferred">Transferred</option><option value="Duplicate">Duplicate</option><option value="Deceased">Deceased</option><option value="Other">Other</option></select></label>
                    <div class="flex justify-end gap-2"><button type="button" class="app-button-secondary" @click="archiveOpen = false">Cancel</button><button class="app-button-danger">Archive record</button></div>
                </form>
            </div>
        </div>
    </div>
