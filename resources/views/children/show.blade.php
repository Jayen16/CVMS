    <div
        class="app-page"
        x-data="{
            openVerificationModal: false,
            verificationActionUrl: '',
            verificationActionLabel: 'Verify',
            verificationSubject: '',
            openConfirmModal: false,
            confirmActionLabel: 'Confirm',
            confirmMessage: '',
            confirmForm: null,
            archiveOpen: false,
            archiveAction: '',
            archiveName: @js($child->full_name),
            showConfirmModal(actionLabel, message, form) {
                this.confirmActionLabel = actionLabel;
                this.confirmMessage = message;
                this.confirmForm = form;
                this.openConfirmModal = true;
            },
            submitConfirmedAction() {
                if (this.confirmForm) {
                    this.confirmForm.requestSubmit();
                }

                this.openConfirmModal = false;
                this.confirmForm = null;
            },
            closeConfirmModal() {
                this.openConfirmModal = false;
                this.confirmForm = null;
            },
        }"
    >
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
            <div class="flex flex-wrap gap-2">
                @if (auth()->user()->isParent())
                    <form
                        method="POST"
                        action="{{ route('children.parents.destroy', ['child' => $child, 'parent' => auth()->user()]) }}"
                        @submit.prevent="showConfirmModal('Unlink child', 'Unlink this child from your account?', $event.currentTarget)"
                    >
                        @csrf
                        @method('DELETE')
                        <button class="app-button-danger">Unlink child</button>
                    </form>
                @endif
                @if (auth()->user()->canManageChildren())
                    <a href="{{ route('children.edit', $child) }}" class="app-button-secondary">Edit child info</a>
                @endif
                @if (auth()->user()->canArchiveChildren())
                    <button type="button" class="app-button-danger" @click="archiveAction = @js(route('children.archive', $child->id)); archiveOpen = true">Archive child</button>
                @endif
                <a href="{{ route('children.card', $child) }}" class="app-button-secondary">Digital vaccine card</a>
                <a href="{{ route('children.timeline', $child) }}" class="app-button-secondary">View timeline chart</a>
                <a href="{{ route('children.timeline.pdf', $child) }}" class="app-button-secondary" target="_blank" rel="noopener">Timeline PDF</a>
            </div>
        </div>

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

        <section class="overflow-hidden rounded-lg border border-teal-200 bg-white shadow-sm shadow-teal-900/10 dark:border-teal-900 dark:bg-zinc-900">
            <div class="border-b border-teal-100 bg-teal-50 px-5 py-4 dark:border-teal-900 dark:bg-teal-950">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">{{ auth()->user()->isParent() ? 'Next clinic visit' : 'AI decision support' }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">
                            @if ($suggestion['vaccine_name'])
                                {{ auth()->user()->isParent() ? 'Visit for ' : 'Recommend ' }}{{ $suggestion['vaccine_name'] }} dose {{ $suggestion['dose_number'] }}
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
                                @if (auth()->user()->isParent())
                                    <th class="px-4 py-3 font-medium">Action</th>
                                @endif
                                @if (auth()->user()->canVerifyVaccinations())
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
                                        @foreach ($record->proofPaths() as $proofPath)
                                            <div class="text-xs">
                                                <a href="{{ route('vaccinations.proofs.show', ['record' => $record, 'proofIndex' => $loop->iteration]) }}" target="_blank" class="text-teal-700 hover:underline dark:text-teal-300">
                                                    View proof photo {{ $loop->iteration }}
                                                </a>
                                            </div>
                                        @endforeach
                                    </td>
                                    <td>
                                        <span
                                            class="status-pill
                                            @if ($record->verification_status === 'verified') status-verified
                                            @elseif ($record->verification_status === 'pending') status-pending
                                            @else status-rejected @endif"
                                            @if (auth()->user()->canVerifyVaccinations() && $record->verified_at)
                                                title="{{ ucfirst($record->verification_status) }} by {{ $record->verifier?->name ?? 'Unknown user' }} on {{ $record->verified_at->format('M d, Y g:i A') }}"
                                            @endif
                                        >
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
                                    @if (auth()->user()->isParent())
                                        <td>
                                            @if ($record->submitted_by === auth()->id() && $record->isPendingVerification())
                                                <a href="{{ route('children.show', ['child' => $child, 'edit_record' => $record->id]) }}" class="app-button-secondary !px-3 !py-1.5 !text-xs">
                                                    Edit
                                                </a>
                                            @else
                                                <span class="text-xs text-zinc-500">No action</span>
                                            @endif
                                        </td>
                                    @endif
                                    @if (auth()->user()->canVerifyVaccinations())
                                        <td>
                                            @if ($record->isPendingVerification())
                                                <div class="flex gap-2">
                                                    <form method="POST" action="{{ route('vaccinations.verify', $record) }}">
                                                        @csrf
                                                        <button
                                                            type="button"
                                                            class="app-button-primary !px-3 !py-1.5 !text-xs"
                                                            @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.verify', $record)); verificationActionLabel = 'Verify'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name)"
                                                        >
                                                            Verify
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('vaccinations.reject', $record) }}">
                                                        @csrf
                                                        <button
                                                            type="button"
                                                            class="app-button-danger !px-3 !py-1.5 !text-xs"
                                                            @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.reject', $record)); verificationActionLabel = 'Reject'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name)"
                                                        >
                                                            Reject
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="text-xs text-zinc-500">{{ $record->verifier ? 'By '.$record->verifier->name : 'No action' }}</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ auth()->user()->isParent() || auth()->user()->canVerifyVaccinations() ? 8 : 6 }}" class="px-4 py-8 text-center text-zinc-500">No vaccination records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="grid content-start gap-6">
                @if (auth()->user()->isParent())
                    @php
                        $defaultClinicName = $editableRecord?->clinic_name ?? $child->barangay?->name ?? 'Current clinic barangay';
                        $defaultClinicLocation = $editableRecord?->clinic_location ?? 'Indang, Cavite, Barangay 4 (pob.), Indang, Cavite, 4122';
                    @endphp
                    <form method="POST" action="{{ $editableRecord ? route('vaccinations.update', $editableRecord) : route('children.vaccinations.store', $child) }}" class="app-panel grid content-start gap-4" enctype="multipart/form-data">
                        @csrf
                        @if ($editableRecord)
                            @method('PUT')
                        @endif
                        <div>
                            <h2 class="app-card-title">{{ $editableRecord ? 'Edit pending vaccination history' : 'Submit vaccination history' }}</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">
                                {{ $editableRecord ? 'You can correct this record while it is still pending clinic verification.' : 'Records given outside the barangay clinic will stay pending until the clinic verifies them.' }}
                            </p>
                        </div>
                        <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" :value="$editableRecord?->vaccine_type_id" />
                        <x-form-field label="Dose number" name="dose_number" type="number" :value="$editableRecord?->dose_number" />
                        <x-form-field label="Date given" name="administered_at" type="date" :value="$editableRecord?->administered_at?->toDateString()" />
                        <x-form-field label="Facility or clinic name" name="clinic_name" :value="$defaultClinicName" />
                        <x-form-field label="Facility or clinic location" name="clinic_location" :value="$defaultClinicLocation" />
                        <label class="grid gap-2 text-sm">
                            <span class="font-medium text-slate-800 dark:text-zinc-100">Photo proof of vaccine card or record</span>
                            <input type="file" name="proof_files[]" accept="image/*" multiple class="app-input">
                            <span class="text-xs text-zinc-500">You can upload up to 5 photos.</span>
                            @if ($editableRecord && $editableRecord->proofPaths() !== [])
                                <div class="space-y-1 text-xs">
                                    @foreach ($editableRecord->proofPaths() as $proofPath)
                                        <a href="{{ route('vaccinations.proofs.show', ['record' => $editableRecord, 'proofIndex' => $loop->iteration]) }}" target="_blank" class="block text-teal-700 hover:underline dark:text-teal-300">
                                            Current proof photo {{ $loop->iteration }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                            @error('proof_files')
                                <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                            @enderror
                            @error('proof_files.*')
                                <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                            @enderror
                        </label>
                        <x-form-field label="Remarks" name="remarks" type="textarea" :value="$editableRecord?->remarks" />
                        <div class="flex flex-wrap gap-2">
                            <button class="app-button-primary">{{ $editableRecord ? 'Save changes' : 'Submit for clinic verification' }}</button>
                            @if ($editableRecord)
                                <a href="{{ route('children.show', $child) }}" class="app-button-secondary">Cancel</a>
                            @endif
                        </div>
                    </form>
                @endif

                @if (auth()->user()->canViewAefiReports() || auth()->user()->canManageChildren())
                    @php
                        $availableTabs = auth()->user()->canManageChildren()
                            ? ['vaccination' => 'Record vaccination', 'aefi' => 'AEFI reporting', 'parents' => 'Linked parents']
                            : ['aefi' => 'AEFI reporting'];
                        $activeTab = array_key_first($availableTabs);

                        if ($errors->hasAny(['vaccination_record_id', 'event_date', 'severity', 'outcome', 'symptoms', 'notes'])) {
                            $activeTab = 'aefi';
                        } elseif (auth()->user()->canManageChildren() && $errors->hasAny(['name', 'email', 'phone', 'relationship'])) {
                            $activeTab = 'parents';
                        } elseif (auth()->user()->canManageChildren() && $errors->hasAny(['vaccine_type_id', 'dose_number', 'administered_at', 'remarks'])) {
                            $activeTab = 'vaccination';
                        }
                    @endphp

                    <section class="grid gap-4" data-child-tabs data-active-tab="{{ $activeTab }}">
                        <div class="flex flex-wrap gap-2">
                            @foreach ($availableTabs as $tabKey => $tabLabel)
                                <button
                                    type="button"
                                    class="rounded-full px-4 py-2 text-sm font-semibold transition {{ $activeTab === $tabKey ? 'bg-teal-600 text-white shadow-sm shadow-teal-900/20' : 'bg-white text-slate-700 ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-zinc-900 dark:text-zinc-200 dark:ring-zinc-800 dark:hover:bg-zinc-800' }}"
                                    data-tab-button
                                    data-tab-target="{{ $tabKey }}"
                                >
                                    {{ $tabLabel }}
                                </button>
                            @endforeach
                        </div>

                        @if (auth()->user()->canManageChildren())
                            <section class="app-panel {{ $activeTab === 'vaccination' ? '' : 'hidden' }}" data-tab-panel="vaccination">
                                <form method="POST" action="{{ route('children.vaccinations.store', $child) }}" class="grid content-start gap-4">
                                    @csrf
                                    <h2 class="app-card-title">Record vaccination</h2>
                                    <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" />
                                    <x-form-field label="Dose number" name="dose_number" type="number" />
                                    <x-form-field label="Date given" name="administered_at" type="date" />
                                    <x-form-field label="Remarks" name="remarks" type="textarea" />
                                    <button class="app-button-primary">Save record</button>
                                </form>
                            </section>
                        @endif

                        <section class="app-panel {{ $activeTab === 'aefi' ? '' : 'hidden' }}" data-tab-panel="aefi">
                            <h2 class="app-card-title">AEFI reporting</h2>
                            @if (auth()->user()->canSubmitAefiReports())
                                <form method="POST" action="{{ route('children.aefi-reports.store', $child) }}" class="mt-4 grid gap-4">
                                    @csrf
                                    <x-form-field label="Linked vaccination record" name="vaccination_record_id" type="select" :options="$child->vaccinations->pluck('vaccineType.name', 'id')" />
                                    <x-form-field label="Event date" name="event_date" type="date" />
                                    <x-form-field label="Severity" name="severity" type="select" :options="['mild' => 'Mild', 'moderate' => 'Moderate', 'severe' => 'Severe']" />
                                    <x-form-field label="Outcome" name="outcome" />
                                    <x-form-field label="Symptoms" name="symptoms" type="textarea" />
                                    <x-form-field label="Notes" name="notes" type="textarea" />
                                    <button class="app-button-primary">Save AEFI report</button>
                                </form>
                            @endif

                            <div class="mt-4 space-y-3">
                                @forelse ($child->adverseEventReports->sortByDesc('event_date') as $report)
                                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-zinc-800 dark:bg-zinc-950">
                                        <div class="font-medium text-slate-950 dark:text-white">{{ $report->event_date->format('M d, Y') }} | {{ ucfirst($report->severity) }}</div>
                                        <div class="mt-1 text-sm text-slate-600 dark:text-zinc-300">{{ $report->symptoms }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">Reported by {{ $report->reporter->name }}</div>
                                    </div>
                                @empty
                                    <p class="text-sm text-zinc-500">No AEFI reports for this child yet.</p>
                                @endforelse
                            </div>
                        </section>

                        @if (auth()->user()->canManageChildren())
                        <section class="app-panel {{ $activeTab === 'parents' ? '' : 'hidden' }}" data-tab-panel="parents">
                            <h2 class="app-card-title">Linked parents</h2>
                            <div class="mt-3 divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                                @forelse ($child->parents as $parent)
                                    <div class="flex items-center justify-between gap-3 py-2">
                                        <div>
                                            <div class="font-medium text-slate-950 dark:text-white">{{ $parent->name }}</div>
                                            <div class="text-zinc-500">
                                                {{ $parent->email }}{{ $parent->phone ? ' | '.$parent->phone : '' }} | {{ $parent->pivot->relationship }}
                                            </div>
                                            <div class="mt-1">
                                                @if ($parent->invitation_accepted_at)
                                                    <span class="status-pill status-verified">Configured</span>
                                                @else
                                                    <span class="status-pill status-pending">Pending password setup</span>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            @if (! $parent->invitation_accepted_at)
                                                <form method="POST" action="{{ route('children.parents.setup-link', ['child' => $child, 'parent' => $parent]) }}">
                                                    @csrf
                                                    <button class="app-button-secondary !px-3 !py-1.5 !text-xs">Resend link</button>
                                                </form>
                                            @endif

                                            <form
                                                method="POST"
                                                action="{{ route('children.parents.destroy', ['child' => $child, 'parent' => $parent]) }}"
                                                @submit.prevent="showConfirmModal('Unlink parent', 'Unlink this parent from the child profile?', $event.currentTarget)"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button class="app-button-danger !px-3 !py-1.5 !text-xs">Unlink</button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <p class="py-2 text-zinc-500">No parent account linked yet.</p>
                                @endforelse
                            </div>

                            <form method="POST" action="{{ route('children.parents.store', $child) }}" class="mt-4 grid gap-4">
                                @csrf
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Invite parent</h3>
                                    <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">Link the parent using either an email address or a phone number. Email can receive a password setup link, while phone-only parents can finish sign up using that phone number and a password.</p>
                                </div>
                                <x-form-field label="Parent name" name="name" />
                                <x-form-field label="Parent email" name="email" type="email" />
                                <x-form-field label="Parent cellphone" name="phone" />
                                <x-form-field
                                    label="Relationship"
                                    name="relationship"
                                    type="select"
                                    :options="[
                                        'mother' => 'Mother',
                                        'father' => 'Father',
                                        'guardian' => 'Guardian',
                                        'aunt' => 'Aunt',
                                        'uncle' => 'Uncle',
                                        'grandmother' => 'Grandmother',
                                        'grandfather' => 'Grandfather',
                                        'other' => 'Other',
                                    ]"
                                />
                                <button class="app-button-primary">Link parent account</button>
                            </form>
                        </section>
                        @endif
                    </section>
                @endif

                @if (auth()->user()->isBarangayAdmin() || auth()->user()->isSuperAdmin())
                    <section class="app-panel grid gap-4">
                        <div>
                            <h2 class="app-card-title">Transfer child to another clinic</h2>
                            <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">Use this when the child has relocated and the registry should move to a new clinic.</p>
                        </div>
                        <form method="POST" action="{{ route('children.transfer', $child) }}" class="grid gap-4">
                            @csrf
                            <x-form-field
                                label="New barangay"
                                name="barangay_id"
                                type="select"
                                :options="\App\Models\Barangay::orderBy('name')->pluck('name', 'id')"
                                :value="$child->barangay_id"
                            />
                            <button
                                class="app-button-primary"
                                @click.prevent="showConfirmModal('Transfer child', 'Transfer this child to another barangay?', $event.currentTarget.form)"
                            >
                                Transfer child
                            </button>
                        </form>
                    </section>
                @endif
            </div>
        </div>
        <form method="POST" x-ref="verificationForm" class="hidden">
            @csrf
        </form>

        <div
            x-cloak
            x-show="openVerificationModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4"
            x-transition.opacity
        >
            <div
                @click.outside="openVerificationModal = false"
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900"
                x-transition
            >
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white" x-text="`${verificationActionLabel} vaccination history`"></h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300">
                    Please confirm you want to
                    <span class="font-semibold text-slate-950 dark:text-white" x-text="verificationActionLabel.toLowerCase()"></span>
                    <span x-text="` ${verificationSubject}.`"></span>
                </p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="app-button-secondary" @click="openVerificationModal = false">Cancel</button>
                    <button
                        type="button"
                        class="app-button-primary"
                        @click="$refs.verificationForm.action = verificationActionUrl; $refs.verificationForm.submit();"
                        x-text="`Confirm ${verificationActionLabel.toLowerCase()}`"
                    ></button>
                </div>
            </div>
        </div>

        <div
            x-cloak
            x-show="openConfirmModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4"
            x-transition.opacity
            @keydown.escape.window="closeConfirmModal()"
        >
            <div
                @click.outside="closeConfirmModal()"
                class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900"
                x-transition
            >
                <h2 class="text-lg font-semibold text-slate-950 dark:text-white" x-text="confirmActionLabel"></h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-zinc-300" x-text="confirmMessage"></p>
                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="app-button-secondary" @click="closeConfirmModal()">Cancel</button>
                    <button
                        type="button"
                        class="app-button-primary"
                        @click="submitConfirmedAction()"
                        x-text="confirmActionLabel"
                    ></button>
                </div>
            </div>
        </div>
    </div>

    @if (auth()->user()->canViewAefiReports() || auth()->user()->canManageChildren())
        <script>
            (() => {
                const tabsRoot = document.querySelector('[data-child-tabs]');
                if (!tabsRoot) return;

                const buttons = tabsRoot.querySelectorAll('[data-tab-button]');
                const panels = tabsRoot.querySelectorAll('[data-tab-panel]');
                const activeClasses = ['bg-teal-600', 'text-white', 'shadow-sm', 'shadow-teal-900/20'];
                const inactiveClasses = ['bg-white', 'text-slate-700', 'ring-1', 'ring-slate-200', 'hover:bg-slate-50', 'dark:bg-zinc-900', 'dark:text-zinc-200', 'dark:ring-zinc-800', 'dark:hover:bg-zinc-800'];

                const setActiveTab = (tabName) => {
                    buttons.forEach((button) => {
                        const isActive = button.dataset.tabTarget === tabName;
                        button.classList.toggle('bg-teal-600', isActive);
                        button.classList.toggle('text-white', isActive);
                        button.classList.toggle('shadow-sm', isActive);
                        button.classList.toggle('shadow-teal-900/20', isActive);
                        button.classList.toggle('bg-white', !isActive);
                        button.classList.toggle('text-slate-700', !isActive);
                        button.classList.toggle('ring-1', !isActive);
                        button.classList.toggle('ring-slate-200', !isActive);
                        button.classList.toggle('hover:bg-slate-50', !isActive);
                        button.classList.toggle('dark:bg-zinc-900', !isActive);
                        button.classList.toggle('dark:text-zinc-200', !isActive);
                        button.classList.toggle('dark:ring-zinc-800', !isActive);
                        button.classList.toggle('dark:hover:bg-zinc-800', !isActive);
                    });

                    panels.forEach((panel) => {
                        panel.classList.toggle('hidden', panel.dataset.tabPanel !== tabName);
                    });
                };

                buttons.forEach((button) => {
                    inactiveClasses.forEach((className) => button.classList.add(className));
                    activeClasses.forEach((className) => button.classList.remove(className));
                    button.addEventListener('click', () => setActiveTab(button.dataset.tabTarget));
                });

                setActiveTab(tabsRoot.dataset.activeTab);
            })();
        </script>
    @endif

    @if (auth()->user()->canManageChildren())
        <script>
            (() => {
                const form = document.querySelector('form[action="{{ route('children.vaccinations.store', $child) }}"]');
                if (!form) return;

                const queueKey = 'offline-vaccination-queue-{{ $child->id }}';
                const notice = document.createElement('p');
                notice.className = 'text-sm text-slate-600 dark:text-zinc-300';
                notice.textContent = 'Offline queue ready. If you lose connection, nurse entries are saved in this device and synced when online.';
                form.prepend(notice);

                const syncQueued = async () => {
                    const queued = JSON.parse(localStorage.getItem(queueKey) || '[]');
                    if (!queued.length || !navigator.onLine) return;

                    const response = await fetch('{{ route('api.parent.children.offline-sync', $child) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        },
                        body: JSON.stringify({ records: queued }),
                    });

                    if (response.ok) {
                        localStorage.removeItem(queueKey);
                        window.location.reload();
                    }
                };

                window.addEventListener('online', syncQueued);
                syncQueued();

                form.addEventListener('submit', (event) => {
                    if (navigator.onLine) return;

                    event.preventDefault();
                    const payload = Object.fromEntries(new FormData(form).entries());
                    payload.client_submission_id = crypto.randomUUID();
                    const queued = JSON.parse(localStorage.getItem(queueKey) || '[]');
                    queued.push(payload);
                    localStorage.setItem(queueKey, JSON.stringify(queued));
                    notice.textContent = `Offline. ${queued.length} record(s) queued on this device and will sync automatically.`;
                });
            })();
        </script>
    @endif
