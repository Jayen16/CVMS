<x-layouts::app :title="$child->full_name">
    <div class="app-page">
        @if (session('status'))
            <div class="app-alert-success">
                {{ session('status') }}
            </div>
        @endif

        <div class="page-heading">
            <div>
                <a href="{{ route('children.index') }}" class="text-sm text-teal-700 hover:underline dark:text-teal-300">Back to children</a>
                <h1 class="page-title mt-2">{{ $child->full_name }}</h1>
                <div class="mt-2 flex flex-wrap gap-2 text-sm">
                    <span class="rounded-full bg-white px-3 py-1 font-medium text-slate-600 ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">{{ ucfirst($child->sex) }}</span>
                    <span class="rounded-full bg-white px-3 py-1 font-medium text-slate-600 ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">{{ $child->ageLabel() }}</span>
                    <span class="rounded-full bg-white px-3 py-1 font-medium text-slate-600 ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">{{ $child->barangay->name }}</span>
                </div>
            </div>
            <a href="{{ route('children.timeline', $child) }}" class="app-button-secondary">View timeline chart</a>
        </div>

        <section class="overflow-hidden rounded-lg border border-teal-200 bg-white shadow-sm shadow-teal-900/10 dark:border-teal-900 dark:bg-zinc-900">
            <div class="border-b border-teal-100 bg-teal-50 px-5 py-4 dark:border-teal-900 dark:bg-teal-950">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">AI decision support</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">
                            @if ($suggestion['vaccine_name'])
                                Recommend {{ $suggestion['vaccine_name'] }} dose {{ $suggestion['dose_number'] }}
                            @else
                                No routine dose currently pending
                            @endif
                        </h2>
                    </div>

                    <span class="status-pill
                        @if ($suggestion['status'] === 'overdue') status-rejected
                        @elseif ($suggestion['status'] === 'upcoming') bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200
                        @else status-verified @endif">
                        {{ ucfirst($suggestion['status']) }}
                    </span>
                </div>
            </div>

            <div class="grid gap-5 p-5 lg:grid-cols-[1fr_340px]">
                <div>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200 dark:bg-zinc-950 dark:ring-zinc-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Suggested action</div>
                            <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">
                                {{ $suggestion['action_at'] ? $suggestion['action_at']->format('M d, Y') : 'Review only' }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200 dark:bg-zinc-950 dark:ring-zinc-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Guideline due</div>
                            <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">
                                {{ $suggestion['due_at'] ? $suggestion['due_at']->format('M d, Y') : 'None' }}
                            </div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200 dark:bg-zinc-950 dark:ring-zinc-800">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Schedule age</div>
                            <div class="mt-2 text-lg font-semibold text-slate-950 dark:text-white">
                                {{ $suggestion['due_label'] ?? 'N/A' }}
                            </div>
                        </div>
                    </div>

                    <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $suggestion['note'] }}</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                    <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Before giving this dose</h3>
                    <ul class="mt-3 space-y-2 text-sm leading-5 text-slate-600 dark:text-zinc-300">
                        @foreach ($suggestion['checks'] as $check)
                            <li class="flex gap-2">
                                <span class="mt-1 size-1.5 rounded-full bg-teal-600"></span>
                                <span>{{ $check }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <section class="app-card">
                <div class="app-card-header">
                    <h2 class="app-card-title">Vaccination history</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 font-medium">Vaccine</th>
                                <th class="px-4 py-3 font-medium">Dose</th>
                                <th class="px-4 py-3 font-medium">Date given</th>
                                <th class="px-4 py-3 font-medium">Source</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Next suggestion</th>
                                @if (auth()->user()->isAdmin() || auth()->user()->isNurse())
                                    <th class="px-4 py-3 font-medium">Action</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($child->vaccinations->sortByDesc('administered_at') as $record)
                                <tr class="app-table-row">
                                    <td class="font-semibold text-slate-950 dark:text-white">
                                        <a href="{{ route('children.timeline', ['child' => $child, 'vaccine' => $record->vaccineType->code]) }}" class="text-teal-700 hover:underline dark:text-teal-300">
                                            {{ $record->vaccineType->name }}
                                        </a>
                                    </td>
                                    <td>{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }}</td>
                                    <td>{{ $record->administered_at->format('M d, Y') }}</td>
                                    <td>
                                        {{ str($record->source)->replace('_', ' ')->title() }}
                                        @if ($record->clinic_name)
                                            <div class="text-xs text-zinc-500">{{ $record->clinic_name }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="status-pill
                                            @if ($record->verification_status === 'verified') status-verified
                                            @elseif ($record->verification_status === 'pending') status-pending
                                            @else status-rejected @endif">
                                            {{ ucfirst($record->verification_status) }}
                                        </span>
                                        @if ($record->submitter)
                                            <div class="mt-1 text-xs text-zinc-500">Submitted by {{ $record->submitter->name }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $record->suggested_vaccine ?? 'None' }}
                                        @if ($record->next_due_at)
                                            <div class="text-xs text-zinc-500">{{ $record->next_due_at->format('M d, Y') }}</div>
                                        @endif
                                    </td>
                                    @if (auth()->user()->isAdmin() || auth()->user()->isNurse())
                                        <td>
                                            @if ($record->isPendingVerification())
                                                <div class="flex gap-2">
                                                    <form method="POST" action="{{ route('vaccinations.verify', $record) }}">
                                                        @csrf
                                                        <button class="app-button-primary !px-3 !py-1.5 !text-xs">Verify</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('vaccinations.reject', $record) }}">
                                                        @csrf
                                                        <button class="app-button-danger !px-3 !py-1.5 !text-xs">Reject</button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-xs text-zinc-500">{{ $record->verifier ? 'By '.$record->verifier->name : 'No action' }}</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-zinc-500">No vaccination records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid content-start gap-6">
                @if (auth()->user()->isNurse())
                    <form method="POST" action="{{ route('children.vaccinations.store', $child) }}" class="app-panel grid content-start gap-4">
                        @csrf
                        <h2 class="app-card-title">Record vaccination</h2>
                        <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" />
                        <x-form-field label="Dose number" name="dose_number" type="number" />
                        <x-form-field label="Date given" name="administered_at" type="date" />
                        <x-form-field label="Remarks" name="remarks" type="textarea" />
                        <button class="app-button-primary">Save record</button>
                    </form>
                @endif

                @if (auth()->user()->isAdmin() || auth()->user()->isNurse())
                    <section class="app-panel">
                        <h2 class="app-card-title">Linked parents</h2>
                        <div class="mt-3 divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                            @forelse ($child->parents as $parent)
                                <div class="py-2">
                                    <div class="font-medium text-slate-950 dark:text-white">{{ $parent->name }}</div>
                                    <div class="text-zinc-500">{{ $parent->email }}{{ $parent->phone ? ' | '.$parent->phone : '' }} | {{ $parent->pivot->relationship }}</div>
                                </div>
                            @empty
                                <p class="py-2 text-zinc-500">No parent account linked yet.</p>
                            @endforelse
                        </div>

                        <form method="POST" action="{{ route('children.parents.store', $child) }}" class="mt-4 grid gap-4">
                            @csrf
                            <x-form-field label="Parent name" name="name" />
                            <x-form-field label="Parent email" name="email" type="email" />
                            <x-form-field label="Parent cellphone" name="phone" />
                            <x-form-field label="Temporary password" name="password" type="password" />
                            <x-form-field label="Relationship" name="relationship" />
                            <button class="app-button-primary">Link parent</button>
                        </form>
                    </section>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
