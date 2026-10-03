@php
    $editParent = $child->parents->firstWhere('id', session('edit_parent_id'));
    $initialChildViewTab = 'schedule';

    if (auth()->user()->canManageChildren()) {
        if (request()->string('tab')->toString() === 'parents' || $errors->hasAny(['name', 'email', 'phone', 'relationship']) || session('edit_parent_id') !== null) {
            $initialChildViewTab = 'parents';
        } elseif ($errors->hasAny(['vaccine_type_id', 'dose_number', 'administered_at', 'vaccine_inventory_item_id', 'remarks'])) {
            $initialChildViewTab = 'vaccination';
        }
    }
@endphp

<div
        class="app-page"
        x-data="{
            openVerificationModal: false,
            verificationActionUrl: '',
            verificationActionLabel: 'Verify',
            verificationSubject: '',
            verificationRemark: '',
            profilePhotoOpen: false,
            proofModalOpen: false,
            proofModalImages: [],
            proofModalIndex: 0,
            recordDetailsOpen: false,
            recordDetails: {},
            openRecordDetails(record) {
                this.recordDetails = record;
                this.recordDetailsOpen = true;
            },
            childViewTab: @js($initialChildViewTab),
            openProofModal(images) {
                this.proofModalImages = images;
                this.proofModalIndex = 0;
                this.proofModalOpen = true;
            },
            closeProofModal() {
                this.proofModalOpen = false;
                this.proofModalImages = [];
                this.proofModalIndex = 0;
            },
            openConfirmModal: false,
            confirmActionLabel: 'Confirm',
            confirmMessage: '',
            confirmForm: null,
            actionStatus: '',
            editParentOpen: @js(session('edit_parent_id') !== null && $editParent !== null),
            editParentAction: @js($editParent ? route('children.parents.update', ['child' => $child, 'parent' => $editParent]) : ''),
            editParentName: @js(old('edit_name', $editParent?->name ?? '')),
            editParentEmail: @js(old('edit_email', $editParent?->email ?? '')),
            editParentPhone: @js(old('edit_phone', $editParent?->phone ?? '')),
            editParentRelationship: @js(old('edit_relationship', $editParent?->pivot?->relationship ?? 'guardian')),
            openParentEditor(action, name, email, phone, relationship) {
                this.editParentAction = action;
                this.editParentName = name;
                this.editParentEmail = email;
                this.editParentPhone = phone;
                this.editParentRelationship = relationship;
                this.editParentOpen = true;
            },
            archiveOpen: false,
            archiveAction: '',
            archiveName: @js($child->full_name),
            photoEditorOpen: false,
            showConfirmModal(actionLabel, message, form) {
                this.confirmActionLabel = actionLabel;
                this.confirmMessage = message;
                this.confirmForm = form;
                this.openConfirmModal = true;
            },
            submitConfirmedAction() {
                if (this.confirmForm) {
                    // The form's submit handler opens this confirmation modal.
                    // Use the native submit method after confirmation so that
                    // it does not trigger that handler a second time.
                    HTMLFormElement.prototype.submit.call(this.confirmForm);
                }

                this.openConfirmModal = false;
                this.confirmForm = null;
            },
            closeConfirmModal() {
                this.openConfirmModal = false;
                this.confirmForm = null;
            },
            init() {
                const message = @js(session('toast_error'));
                if (message) window.Flux?.toast({ variant: 'danger', text: message });
            },
        }"
    >
        @if (session('status'))
            <div class="app-alert-success">
                {{ session('status') }}
            </div>
        @endif
        <div x-show="actionStatus" x-cloak x-text="actionStatus" class="app-alert-success" @parent-action-status.window="actionStatus = $event.detail"></div>

        <div x-show="editParentOpen" x-cloak x-on:keydown.escape.window="editParentOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="edit-parent-title">
            <form method="POST" x-bind:action="editParentAction" class="app-panel w-full max-w-2xl" @click.stop>
                @csrf
                @method('PUT')
                <h2 id="edit-parent-title" class="app-card-title">Edit parent information</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Parent name</span><input class="app-input" type="text" name="edit_name" x-model="editParentName" required>@error('edit_name', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Parent email</span><input class="app-input" type="email" name="edit_email" x-model="editParentEmail">@error('edit_email', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Parent cellphone</span><input class="app-input" type="text" name="edit_phone" x-model="editParentPhone">@error('edit_phone', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Relationship</span><select class="app-input" name="edit_relationship" x-model="editParentRelationship" required><option value="mother">Mother</option><option value="father">Father</option><option value="guardian">Guardian</option><option value="aunt">Aunt</option><option value="uncle">Uncle</option><option value="grandmother">Grandmother</option><option value="grandfather">Grandfather</option><option value="other">Other</option></select>@error('edit_relationship', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                </div>
                <p class="mt-3 text-xs text-slate-500 dark:text-zinc-400">Provide at least an email address or cellphone number so the parent can recover access.</p>
                <div class="mt-5 flex justify-end gap-2"><button type="button" class="app-button-secondary" @click="editParentOpen = false">Cancel</button><button type="submit" class="app-button-primary">Save parent</button></div>
            </form>
        </div>

        <div class="page-heading">
            <a href="{{ route('children.index') }}" class="text-sm text-teal-700 hover:underline dark:text-teal-300">Back to children</a>
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

        <section class="app-panel relative flex flex-col gap-5 p-4 sm:p-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="absolute right-3 top-3 z-30" x-data="{ open: false }">
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-xl text-lg font-bold tracking-widest text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-600 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-white" @click="open = !open" :aria-expanded="open.toString()" aria-label="Child profile actions">•••</button>
                <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 top-11 z-20 min-w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                    @if (auth()->user()->canManageChildren())
                        <a href="{{ route('children.edit', $child) }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-zinc-200 dark:hover:bg-zinc-800" wire:navigate>Edit child info</a>
                    @endif
                    @if (auth()->user()->canArchiveChildren())
                        <button type="button" class="block w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40" @click="archiveAction = @js(route('children.archive', $child->id)); archiveOpen = true; open = false">Archive child</button>
                    @endif
                    <a href="{{ route('children.card', $child) }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-zinc-200 dark:hover:bg-zinc-800" wire:navigate>Digital vaccine card</a>
                    <a href="{{ route('children.timeline.pdf', $child) }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 dark:text-zinc-200 dark:hover:bg-zinc-800" target="_blank" rel="noopener">Timeline PDF</a>
                    @if (auth()->user()->isParent())
                        <form method="POST" action="{{ route('children.parents.destroy', ['child' => $child, 'parent' => auth()->user()]) }}" class="mt-1 border-t border-slate-100 pt-1 dark:border-zinc-800" @submit.prevent="showConfirmModal('Unlink child', 'Unlink this child from your account?', $event.currentTarget)">
                            @csrf
                            @method('DELETE')
                            <button class="block w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40">Unlink child</button>
                        </form>
                    @endif
                </div>
            </div>
            <div class="flex min-w-0 items-center gap-4">
                @if ($child->photo_path)
                    <button type="button" class="size-24 shrink-0 overflow-hidden rounded-full bg-teal-100 ring-2 ring-teal-200 transition hover:ring-4 focus:outline-none focus:ring-4 focus:ring-teal-300 dark:bg-teal-950 dark:ring-teal-800 dark:focus:ring-teal-700" @click="profilePhotoOpen = true" aria-label="View larger photo of {{ $child->full_name }}">
                        <img src="{{ route('children.photo', $child) }}" alt="Photo of {{ $child->full_name }}" class="size-full object-cover">
                    </button>
                @else
                    <div class="size-24 shrink-0 overflow-hidden rounded-full bg-teal-100 ring-2 ring-teal-200 dark:bg-teal-950 dark:ring-teal-800">
                        <div class="flex size-full items-center justify-center text-3xl font-semibold text-teal-700 dark:text-teal-300">{{ str($child->first_name)->substr(0, 1) }}{{ str($child->last_name)->substr(0, 1) }}</div>
                    </div>
                @endif
                <div class="min-w-0 flex-1 pr-10 sm:pr-12">
                    <h1 class="page-title mt-1 break-words text-xl sm:text-2xl">{{ $child->full_name }}</h1>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm">
                        <span class="rounded-full bg-white px-3 py-1 font-medium text-slate-600 ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">{{ ucfirst($child->sex) }}</span>
                        <span class="rounded-full bg-white px-3 py-1 font-medium text-slate-600 ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">{{ $child->ageLabel() }} old</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 font-medium text-slate-600 ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">
                            <svg aria-hidden="true" viewBox="0 0 24 24" class="size-4 text-teal-600 dark:text-teal-300" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 10h14v9H5zM3.5 10h17M7 7v3m5-3v3m5-3v3M8 7a2 2 0 1 1 4 0v0a2 2 0 1 1 4 0v0" />
                                <path stroke-linecap="round" d="M8 14h.01M12 14h.01M16 14h.01M8 17h.01M12 17h.01M16 17h.01" />
                            </svg>
                            <span>Born {{ $child->birthdate->format('M d, Y') }}</span>
                        </span>
                        <span class="rounded-full bg-white px-3 py-1 font-medium text-slate-600 ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">{{ $child->barangay->name }}</span>
                        <span class="rounded-full bg-white px-3 py-1 font-medium text-slate-600 ring-1 ring-slate-200 dark:bg-zinc-900 dark:text-zinc-300 dark:ring-zinc-800">Profile created {{ $child->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
            @if (auth()->user()->isNurse() || auth()->user()->isParent())
                <div class="w-full text-sm lg:w-auto">
                    <button type="button" class="font-medium text-teal-700 underline-offset-4 hover:underline dark:text-teal-300" @click="photoEditorOpen = true">
                        Change photo
                    </button>
                    <form x-show="photoEditorOpen" x-cloak method="POST" action="{{ route('children.photo.upload', $child) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
                        @csrf
                        <input x-ref="photoInput" type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" required class="app-input w-full !py-1.5 text-sm lg:w-auto">
                        <button class="font-semibold text-teal-700 underline-offset-4 hover:underline dark:text-teal-300">Save</button>
                        <button type="button" class="font-semibold text-slate-500 underline-offset-4 hover:text-slate-700 hover:underline dark:text-zinc-400 dark:hover:text-zinc-200" @click="photoEditorOpen = false; $refs.photoInput.value = ''">Remove</button>
                    </form>
                </div>
            @endif
        </section>

        <nav class="app-card flex gap-1 overflow-x-auto p-1.5" aria-label="Child profile sections">
            <button type="button" class="min-w-max flex-1 rounded-xl px-3 py-2.5 text-sm font-semibold transition" :class="childViewTab === 'schedule' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-teal-50 dark:text-zinc-300 dark:hover:bg-zinc-800'" @click="childViewTab = 'schedule'">Schedule</button>
            <button type="button" class="min-w-max flex-1 rounded-xl px-3 py-2.5 text-sm font-semibold transition" :class="childViewTab === 'vaccination' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-teal-50 dark:text-zinc-300 dark:hover:bg-zinc-800'" @click="childViewTab = 'vaccination'">Vaccination History</button>
            @if (auth()->user()->canManageChildren())
                <button type="button" class="min-w-max flex-1 rounded-xl px-3 py-2.5 text-sm font-semibold transition" :class="childViewTab === 'parents' ? 'bg-teal-600 text-white shadow-sm' : 'text-slate-600 hover:bg-teal-50 dark:text-zinc-300 dark:hover:bg-zinc-800'" @click="childViewTab = 'parents'">Parent</button>
            @endif
        </nav>

        <section x-cloak x-show="childViewTab === 'schedule'" class="overflow-hidden rounded-lg border border-teal-200 bg-white shadow-sm shadow-teal-900/10 dark:border-teal-900 dark:bg-zinc-900">
            <div class="border-b border-teal-100 bg-teal-50 px-5 py-4 dark:border-teal-900 dark:bg-teal-950">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-700 dark:text-teal-300">{{ auth()->user()->isParent() ? 'Next clinic visit' : 'Decision support' }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-slate-950 dark:text-white">
                            @if ($suggestion['vaccine_name'])
                                {{ auth()->user()->isParent() ? 'Visit for ' : 'Recommend ' }}{{ $suggestion['vaccine_name'] }} dose {{ $suggestion['dose_number'] }}
                            @elseif ($suggestion['status'] === 'catch_up_review')
                                Catch-up review required
                            @else
                                No routine dose currently pending
                            @endif
                        </h2>
                    </div>

                    <span class="status-pill
                        @if ($suggestion['status'] === 'overdue') status-rejected
                        @elseif ($suggestion['status'] === 'delayed') bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-200
                        @elseif ($suggestion['status'] === 'due') bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-200
                        @elseif ($suggestion['status'] === 'upcoming') bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-200
                        @elseif ($suggestion['status'] === 'catch_up_review') bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200
                        @else status-verified @endif">
                        {{ $suggestion['status'] === 'catch_up_review' ? 'Catch-up review' : ucfirst($suggestion['status']) }}
                    </span>
                </div>
            </div>

            <div class="grid gap-3 p-3 sm:gap-5 sm:p-5 lg:grid-cols-[1fr_340px]">
                <div>
                    <div class="grid grid-cols-3 gap-2 sm:gap-3">
                        <div class="min-w-0 rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-200 dark:bg-zinc-950 dark:ring-zinc-800 sm:p-4">
                            <div class="truncate text-[9px] font-semibold uppercase tracking-wide text-slate-500 sm:text-xs">Suggested action</div>
                            <div class="mt-1 text-xs font-semibold leading-4 text-slate-950 dark:text-white sm:mt-2 sm:text-lg sm:leading-normal">
                                {{ $suggestion['action_at'] ? $suggestion['action_at']->format('M d, Y') : 'Review only' }}
                            </div>
                        </div>
                        <div class="min-w-0 rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-200 dark:bg-zinc-950 dark:ring-zinc-800 sm:p-4">
                            <div class="truncate text-[9px] font-semibold uppercase tracking-wide text-slate-500 sm:text-xs">Guideline due</div>
                            <div class="mt-1 text-xs font-semibold leading-4 text-slate-950 dark:text-white sm:mt-2 sm:text-lg sm:leading-normal">
                                {{ $suggestion['due_at'] ? $suggestion['due_at']->format('M d, Y') : 'None' }}
                            </div>
                        </div>
                        <div class="min-w-0 rounded-lg bg-slate-50 p-2.5 ring-1 ring-slate-200 dark:bg-zinc-950 dark:ring-zinc-800 sm:p-4">
                            <div class="truncate text-[9px] font-semibold uppercase tracking-wide text-slate-500 sm:text-xs">Schedule age</div>
                            <div class="mt-1 text-xs font-semibold leading-4 text-slate-950 dark:text-white sm:mt-2 sm:text-lg sm:leading-normal">
                                {{ $suggestion['due_label'] ?? 'N/A' }}
                            </div>
                        </div>
                    </div>

                    <p class="mt-3 text-xs leading-5 text-slate-600 dark:text-zinc-300 sm:mt-4 sm:text-sm sm:leading-6">
                        @if (auth()->user()->isParent())
                            This clinic visit is recommended based on the child’s immunization schedule. Please bring your child and vaccine card or any outside-clinic records so the clinic team can confirm the dose and update the vaccination history.
                        @else
                            {{ $suggestion['note'] }}
                        @endif
                    </p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-zinc-800 dark:bg-zinc-950 sm:p-4">
                    <h3 class="text-xs font-semibold text-slate-950 dark:text-white sm:text-sm">{{ auth()->user()->isParent() ? 'Before your clinic visit' : 'Before giving this dose' }}</h3>
                    <ul class="mt-2 space-y-2 text-xs leading-5 text-slate-600 dark:text-zinc-300 sm:mt-3 sm:text-sm">
                        @if (auth()->user()->isParent())
                            @foreach ([
                                'Bring your child and vaccine card or outside-clinic proof to the visit.',
                                'Tell the clinic team about allergies, previous reactions, or current illness.',
                                'Ask the clinic to record the dose and your child’s next due date.',
                            ] as $check)
                                <li class="flex gap-2">
                                    <span class="mt-1 size-1.5 rounded-full bg-teal-600"></span>
                                    <span>{{ $check }}</span>
                                </li>
                            @endforeach
                        @else
                            @foreach ($suggestion['checks'] as $check)
                                <li class="flex gap-2">
                                    <span class="mt-1 size-1.5 rounded-full bg-teal-600"></span>
                                    <span>{{ $check }}</span>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                </div>
            </div>
        </section>

        <div x-cloak x-show="childViewTab === 'vaccination' || childViewTab === 'parents'" class="grid gap-6">
        <section
                x-show="childViewTab === 'vaccination'"
                class="app-card {{ auth()->user()->isParent() || auth()->user()->canManageChildren() ? 'order-2' : '' }}"
            >
                <div class="app-card-header flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="app-card-title">Vaccination history</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ $vaccinations->total() }} record{{ $vaccinations->total() === 1 ? '' : 's' }}</p>
                    </div>
                    <div class="flex w-full flex-col items-stretch gap-2 sm:w-auto sm:flex-row sm:items-center sm:gap-3">
                        <a href="{{ route('children.timeline', $child) }}" class="app-button-secondary w-full !px-3 !py-2 text-sm sm:w-auto" wire:navigate>View timeline chart</a>
                        <label class="flex items-center justify-between gap-2 text-sm font-medium sm:justify-start">
                            Rows per page
                            <select wire:model.live="perPage" class="app-input !w-auto">
                                <option value="10">10</option>
                                <option value="15">15</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                            </select>
                        </label>
                    </div>
                </div>
                @unless (auth()->user()->isParent())
                <div class="grid gap-3 p-3 lg:hidden">
                    @forelse ($vaccinations as $record)
                        <article class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('children.timeline', ['child' => $child, 'vaccine' => $record->vaccineType->code]) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $record->vaccineType->name }}</a>
                                    <p class="mt-0.5 text-xs text-zinc-500">{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }} · {{ $record->administered_at->format('M d, Y') }}</p>
                                </div>
                                <span class="status-pill shrink-0 @if ($record->verification_status === 'verified') status-verified @elseif ($record->verification_status === 'pending') status-pending @else status-rejected @endif">{{ ucfirst($record->verification_status) }}</span>
                            </div>
                            <dl class="mt-4 grid grid-cols-2 gap-x-3 gap-y-3 border-t border-slate-100 pt-3 text-sm dark:border-zinc-800">
                                <div><dt class="text-xs text-zinc-500">Source</dt><dd class="mt-0.5">{{ str($record->source)->replace('_', ' ')->title() }}@if ($record->clinic_name)<span class="block text-xs text-zinc-500">{{ $record->clinic_name }}</span>@endif</dd></div>
                                <div><dt class="text-xs text-zinc-500">Review</dt><dd class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-300">@if ($record->verification_status === 'pending')Submitted by {{ $record->submitter?->name ?? 'Unknown parent' }}@elseif ($record->verification_status === 'verified')Approved by {{ $record->verifier?->name ?? $record->recordedByDisplayName() }}@else Rejected by {{ $record->verifier?->name ?? $record->recordedByDisplayName() }}@endif</dd></div>
                                <div><dt class="text-xs text-zinc-500">Next suggestion</dt><dd class="mt-0.5">{{ $record->suggested_vaccine ?? 'None' }}@if ($record->next_due_at)<span class="block text-xs text-zinc-500">{{ $record->next_due_at->format('M d, Y') }}</span>@endif</dd></div>
                                <div><dt class="text-xs text-zinc-500">Remarks</dt><dd class="mt-0.5 whitespace-pre-line">{{ $record->remarks ?? '—' }}</dd></div>
                            </dl>
                            @if ($record->proofPaths() !== [])
                                <a href="#" @click.prevent="openProofModal(@js($this->proofImageUrls($record)))" class="mt-3 inline-block text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300">View submitted {{ count($record->proofPaths()) }} photo{{ count($record->proofPaths()) === 1 ? '' : 's' }}</a>
                            @endif
                            @if (auth()->user()->canVerifyVaccinations())
                                <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3 dark:border-zinc-800">
                                    @if ($record->isPendingVerification())
                                        <form method="POST" action="{{ route('vaccinations.verify', $record) }}" class="flex-1">@csrf<button type="button" class="app-button-primary w-full !px-3 !py-2 !text-xs" @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.verify', $record)); verificationActionLabel = 'Verify'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name)">Verify</button></form>
                                        <form method="POST" action="{{ route('vaccinations.reject', $record) }}" class="flex-1">@csrf<button type="button" class="app-button-danger w-full !px-3 !py-2 !text-xs" @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.reject', $record)); verificationActionLabel = 'Reject'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name); verificationRemark = ''">Reject</button></form>
                                    @else
                                        <span class="text-xs text-zinc-500">{{ $record->verifier ? 'Reviewed by '.$record->verifier->name : 'No action' }}</span>
                                    @endif
                                </div>
                            @endif
                        </article>
                    @empty
                        <div class="app-card p-6 text-center text-sm text-zinc-500">No vaccination records yet.</div>
                    @endforelse
                </div>
                @endunless
                <div class="{{ auth()->user()->isParent() ? '' : 'hidden lg:block' }} overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                @if (auth()->user()->isParent())
                                    <th class="px-4 py-3 font-medium">Vaccine</th>
                                    <th class="px-4 py-3 font-medium">Status</th>
                                @else
                                <th class="px-4 py-3 font-medium">Vaccine</th>
                                <th class="px-4 py-3 font-medium">Dose</th>
                                <th class="px-4 py-3 font-medium">Date given</th>
                                <th class="px-4 py-3 font-medium">Source</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">Next suggestion</th>
                                <th class="px-4 py-3 font-medium">Remarks</th>
                                @if (auth()->user()->isParent())
                                    <th class="px-4 py-3 font-medium">Action</th>
                                @endif
                                @if (auth()->user()->canVerifyVaccinations())
                                    <th class="px-4 py-3 font-medium">Action</th>
                                @endif
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vaccinations as $record)
                                <tr class="app-table-row">
                                    @if (auth()->user()->isParent())
                                        <td class="font-semibold text-slate-950 dark:text-white"><button type="button" class="inline-flex w-full items-center justify-between gap-3 text-left" aria-label="View details for {{ $record->vaccineType->name }}" @click="openRecordDetails(@js([
                                            'vaccine' => $record->vaccineType->name,
                                            'dose' => $record->dose_number ? 'Dose '.$record->dose_number : 'Not set',
                                            'date' => $record->administered_at->format('M d, Y'),
                                            'source' => str($record->source)->replace('_', ' ')->title(),
                                            'clinic' => $record->clinic_name,
                                            'location' => $record->clinic_location,
                                            'status' => ucfirst($record->verification_status),
                                            'submitter' => $record->submitter?->name ?? 'Unknown parent',
                                            'verifier' => $record->verifier?->name ?? $record->recordedByDisplayName(),
                                            'next' => $record->next_due_at?->format('M d, Y'),
                                            'suggestion' => $record->suggested_vaccine,
                                            'remarks' => $record->remarks,
                                            'proofs' => count($record->proofPaths()),
                                            'proofImages' => $this->proofImageUrls($record),
                                        ]))"><span class="min-w-0"><span class="block truncate text-teal-700 dark:text-teal-300">{{ $record->vaccineType->name }}</span><span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-zinc-400">{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }}</span></span><flux:icon.chevron-right class="size-4 shrink-0 text-slate-400" /></button></td>
                                        <td><span class="status-pill @if ($record->verification_status === 'verified') status-verified @elseif ($record->verification_status === 'pending') status-pending @else status-rejected @endif">{{ ucfirst($record->verification_status) }}</span></td>
                                    @else
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
                                        @if ($record->proofPaths() !== [])
                                            <div class="text-xs">
                                                <a href="#" @click.prevent="openProofModal(@js($this->proofImageUrls($record)))" class="text-teal-700 hover:underline dark:text-teal-300">
                                                    View submitted {{ count($record->proofPaths()) }} photo{{ count($record->proofPaths()) === 1 ? '' : 's' }}
                                                </a>
                                            </div>
                                        @endif
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
                                        <div class="mt-1 text-xs text-zinc-500">
                                            @if ($record->verification_status === 'pending')
                                                Submitted by {{ $record->submitter?->name ?? 'Unknown parent' }}
                                            @elseif ($record->verification_status === 'verified')
                                                Approved by {{ $record->verifier?->name ?? $record->recordedByDisplayName() }}
                                            @else
                                                Rejected by {{ $record->verifier?->name ?? $record->recordedByDisplayName() }}
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        {{ $record->suggested_vaccine ?? 'None' }}
                                        @if ($record->next_due_at)
                                            <div class="text-xs text-zinc-500">{{ $record->next_due_at->format('M d, Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="max-w-xs whitespace-pre-line text-sm">{{ $record->remarks ?? '—' }}</td>
                                    @if (auth()->user()->isParent())
                                        <td>
                                            @if ($record->submitted_by === auth()->id() && $record->isParentEditable())
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
                                                            @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.reject', $record)); verificationActionLabel = 'Reject'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name); verificationRemark = ''"
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
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="{{ auth()->user()->isParent() ? 2 : (auth()->user()->canVerifyVaccinations() ? 8 : 7) }}" class="px-4 py-8 text-center text-zinc-500">No vaccination records yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($vaccinations->hasPages())
                    <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                        {{ $vaccinations->links() }}
                    </div>
                @endif
            </section>

            <div class="contents">
                @if (auth()->user()->isParent())
                    @php
                        $defaultClinicName = $editableRecord?->clinic_name ?? $child->barangay?->name ?? 'Current clinic barangay';
                        $defaultClinicLocation = $editableRecord?->clinic_location ?? 'Indang, Cavite, Barangay 4 (pob.), Indang, Cavite, 4122';
                    @endphp
                    <form
                        method="POST"
                        action="{{ $editableRecord ? route('vaccinations.update', $editableRecord) : route('children.vaccinations.store', $child) }}"
                        class="app-panel order-1 grid content-start gap-4 sm:grid-cols-2 lg:grid-cols-3"
                        x-data="{ submitHistoryOpen: @js($editableRecord !== null) }"
                        enctype="multipart/form-data"
                    >
                        @csrf
                        @if ($editableRecord)
                            @method('PUT')
                        @endif
                        <div class="sm:col-span-2 lg:col-span-3">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 text-left"
                                @click="submitHistoryOpen = !submitHistoryOpen"
                                :aria-expanded="submitHistoryOpen.toString()"
                                aria-controls="submit-vaccination-history-fields"
                            >
                                <span class="app-card-title">{{ $editableRecord ? 'Edit pending vaccination history' : 'Submit vaccination history' }}</span>
                                <span x-show="submitHistoryOpen" x-cloak class="text-sm font-medium text-teal-700 dark:text-teal-300">Hide</span>
                                <span x-show="!submitHistoryOpen" x-cloak class="text-sm font-semibold leading-none text-teal-700 dark:text-teal-300">+ Create</span>
                            </button>
                        </div>
                        <div id="submit-vaccination-history-fields" x-show="submitHistoryOpen" x-cloak class="contents">
                            <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300 sm:col-span-2 lg:col-span-3">
                                {{ $editableRecord ? 'You can correct this record until it is synchronized to Central. After synchronization, submit a new request if the facility rejects it.' : 'Records given outside the barangay clinic will stay pending until the clinic verifies them.' }}
                            </p>
                            <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" :value="$editableRecord?->vaccine_type_id" />
                            <x-form-field label="Dose number" name="dose_number" type="number" :value="$editableRecord?->dose_number" />
                            <x-form-field label="Date given" name="administered_at" type="date" :value="$editableRecord?->administered_at?->toDateString()" />
                            <x-form-field label="Facility or clinic name" name="clinic_name" :value="$defaultClinicName" />
                            <x-form-field label="Facility or clinic location" name="clinic_location" :value="$defaultClinicLocation" />
                            <label class="grid gap-2 text-sm sm:col-span-2 lg:col-span-3">
                                <span class="font-medium text-slate-800 dark:text-zinc-100">Photo proof of vaccine card or record</span>
                                <input type="file" name="proof_files[]" accept="image/*" multiple class="app-input" {{ ! $editableRecord || $editableRecord->proofPaths() === [] ? 'required' : '' }}>
                                <span class="text-xs text-zinc-500">At least 1 photo is required. You can upload up to 5 photos.</span>
                                @if ($editableRecord && $editableRecord->proofPaths() !== [])
                                    <div class="text-xs">
                                        <a href="#" @click.prevent="openProofModal(@js($this->proofImageUrls($editableRecord)))" class="text-teal-700 hover:underline dark:text-teal-300">
                                            View submitted {{ count($editableRecord->proofPaths()) }} photo{{ count($editableRecord->proofPaths()) === 1 ? '' : 's' }}
                                        </a>
                                    </div>
                                @endif
                                @error('proof_files')
                                    <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                                @enderror
                                @error('proof_files.*')
                                    <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                                @enderror
                            </label>
                            <div class="sm:col-span-2 lg:col-span-3">
                                <x-form-field label="Remarks" name="remarks" type="textarea" :value="$editableRecord?->remarks" />
                            </div>
                            <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
                                <button class="app-button-primary">{{ $editableRecord ? 'Save changes' : 'Submit for clinic verification' }}</button>
                                @if ($editableRecord)
                                    <a href="{{ route('children.show', $child) }}" class="app-button-secondary">Cancel</a>
                                @endif
                            </div>
                        </div>
                    </form>
                @endif

                @if (auth()->user()->canManageChildren())
                    <section
                                class="app-panel order-first"
                                x-show="childViewTab === 'vaccination'"
                                x-data="{
                                     recordVaccinationOpen: @js($errors->hasAny(['vaccine_type_id', 'dose_number', 'administered_at', 'vaccine_inventory_item_id', 'remarks'])),
                                    vaccineId: @js((string) old('vaccine_type_id', '')),
                                    inventoryVaccineIds: @js($inventoryItems->mapWithKeys(fn ($item) => [(string) $item->id => (string) $item->vaccine_type_id])),
                                    inventorySelect: null,
                                    filterInventoryOptions() {
                                        [...this.$refs.inventorySelect.options].forEach((option, index) => {
                                            option.hidden = index > 0 && this.vaccineId !== '' && this.inventoryVaccineIds[option.value] !== this.vaccineId;
                                            if (option.hidden && option.selected) {
                                                this.$refs.inventorySelect.value = '';
                                            }
                                        });
                                    },
                                    changeVaccine() {
                                        this.filterInventoryOptions();
                                    }
                                }"
                                x-init="$nextTick(() => filterInventoryOptions())"
                            >
                                <form method="POST" action="{{ route('children.vaccinations.store', $child) }}" class="grid content-start gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    @csrf
                                    <div class="flex items-center justify-between sm:col-span-2 lg:col-span-4">
                                        <h2 class="app-card-title">Record vaccination</h2>
                                        <button
                                            type="button"
                                            class="app-button-secondary"
                                            :aria-expanded="recordVaccinationOpen.toString()"
                                            aria-controls="record-vaccination-fields"
                                            @click="recordVaccinationOpen = !recordVaccinationOpen"
                                        >
                                            <span x-text="recordVaccinationOpen ? '− Close' : '+ Create'"></span>
                                        </button>
                                    </div>
                                    <div id="record-vaccination-fields" x-show="recordVaccinationOpen" x-cloak class="contents">
                                    <label class="grid gap-2 text-sm">
                                        <span class="font-medium text-slate-800 dark:text-zinc-100">Vaccine</span>
                                        <select name="vaccine_type_id" class="app-input" x-model="vaccineId" @change="changeVaccine()">
                                            <option value="">Select vaccine</option>
                                            @foreach ($vaccines as $vaccine)
                                                <option value="{{ $vaccine->id }}">{{ $vaccine->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('vaccine_type_id')
                                            <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ $message }}</span>
                                        @enderror
                                    </label>
                                    <x-form-field label="Dose number" name="dose_number" type="number" />
                                    <x-form-field label="Date given" name="administered_at" type="date" />
                                    <x-form-field
                                        label="Inventory stock (optional)"
                                        name="vaccine_inventory_item_id"
                                        type="select"
                                        x-ref="inventorySelect"
                                        :options="$inventoryItems->mapWithKeys(fn ($item) => [$item->id => $item->vaccineType->name.' · '.($item->batch_number ?: $item->item_code).' · '.$item->availableStock().' doses'])->all()"
                                    />
                                    <div class="sm:col-span-2 lg:col-span-4">
                                        <x-form-field label="Remarks" name="remarks" type="textarea" />
                                    </div>
                                    <button class="app-button-primary sm:col-span-2 lg:col-span-4">Save record</button>
                                    </div>
                                </form>
                            </section>
                        <section class="app-card p-5" x-show="childViewTab === 'parents'">
                            <h2 class="app-card-title">Linked parents</h2>
                            <div class="order-2 mt-4 grid gap-3 lg:hidden">
                                @forelse ($child->parents as $parent)
                                    <article class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0"><h3 class="font-semibold text-slate-950 dark:text-white">{{ $parent->name }}</h3><p class="mt-0.5 text-xs capitalize text-zinc-500">{{ $parent->pivot->relationship }}</p></div>
                                            @if ($parent->invitation_accepted_at)<span class="status-pill status-verified">Configured</span>@else<span class="status-pill status-pending">Setup pending</span>@endif
                                        </div>
                                        <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-sm dark:border-zinc-800">
                                            <div><dt class="text-xs text-zinc-500">Email</dt><dd class="break-words">{{ $parent->email ?: 'No email' }}</dd></div>
                                            <div><dt class="text-xs text-zinc-500">Cellphone</dt><dd>{{ $parent->phone ?: 'No phone' }}</dd></div>
                                        </dl>
                                        <div class="mt-4 grid grid-cols-2 gap-2 border-t border-slate-100 pt-3 dark:border-zinc-800">
                                            <button type="button" class="app-button-secondary !px-2 !py-2 text-xs" @click="openParentEditor(@js(route('children.parents.update', ['child' => $child, 'parent' => $parent])), @js($parent->name), @js($parent->email), @js($parent->phone), @js($parent->pivot->relationship))">Edit parent</button>
                                            @if (! $parent->invitation_accepted_at && ($parent->email || $parent->phone))
                                                <form method="POST" action="{{ route('children.parents.setup-link', ['child' => $child, 'parent' => $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the setup link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">@csrf<button class="app-button-secondary w-full !px-2 !py-2 text-xs">Resend setup</button></form>
                                            @endif
                                            @if ($parent->parentLoginChannel() === 'email')
                                                <form method="POST" action="{{ route('children.parents.password-link', [$child, $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the reset link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">@csrf<input type="hidden" name="channel" value="email"><button class="app-button-secondary w-full !px-2 !py-2 text-xs">Reset by email</button></form>
                                            @endif
                                            @if ($parent->parentLoginChannel() === 'sms')
                                                <form method="POST" action="{{ route('children.parents.password-link', [$child, $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the reset link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">@csrf<input type="hidden" name="channel" value="sms"><button class="app-button-secondary w-full !px-2 !py-2 text-xs">Reset by text</button></form>
                                            @endif
                                            <form method="POST" action="{{ route('children.parents.destroy', ['child' => $child, 'parent' => $parent]) }}" @submit.prevent="showConfirmModal('Unlink parent', 'Unlink this parent from the child profile?', $event.currentTarget)">@csrf @method('DELETE')<button class="app-button-danger w-full !px-2 !py-2 text-xs">Unlink parent</button></form>
                                        </div>
                                    </article>
                                @empty
                                    <div class="app-card p-6 text-center text-sm text-zinc-500">No parent account linked yet.</div>
                                @endforelse
                            </div>
                            <div class="order-2 mt-4 hidden overflow-x-auto rounded-lg border border-slate-200 dark:border-zinc-800 lg:block">
                                <table class="app-table w-full table-fixed">
                                    <thead>
                                        <tr>
                                            <th class="w-[19%] px-2 py-3 font-medium sm:px-4">Parent</th>
                                            <th class="w-[25%] px-2 py-3 font-medium sm:px-4">Contact</th>
                                            <th class="w-[18%] px-2 py-3 font-medium sm:px-4">Relationship</th>
                                            <th class="w-[26%] px-2 py-3 font-medium sm:px-4">Status</th>
                                            <th class="w-[12%] px-2 py-3 font-medium sm:px-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                @forelse ($child->parents as $parent)
                                    <tr class="app-table-row align-top">
                                        <td class="break-words px-2 font-medium text-slate-950 dark:text-white sm:px-4">{{ $parent->name }}</td>
                                        <td class="break-words px-2 sm:px-4">
                                            {{ $parent->email ?: 'No email' }}
                                            @if ($parent->phone)
                                                <div class="text-zinc-500">{{ $parent->phone }}</div>
                                            @endif
                                        </td>
                                        <td class="break-words px-2 capitalize sm:px-4">{{ $parent->pivot->relationship }}</td>
                                        <td class="break-words px-2 sm:px-4">
                                            @if ($parent->invitation_accepted_at)
                                                <span class="status-pill status-verified">Configured</span>
                                            @else
                                                <span class="status-pill status-pending">Pending password setup</span>
                                            @endif
                                        </td>
                                        <td class="px-2 sm:px-4">
                                            <div
                                                class="relative"
                                                x-data="{
                                                    open: false,
                                                    menuTop: 0,
                                                    menuLeft: 0,
                                                    toggleMenu() {
                                                        this.open = ! this.open;

                                                        if (this.open) {
                                                            this.$nextTick(() => {
                                                                const button = this.$refs.trigger.getBoundingClientRect();
                                                                const menu = this.$refs.menu;
                                                                const menuHeight = menu.offsetHeight;
                                                                const menuWidth = menu.offsetWidth;

                                                                this.menuLeft = Math.min(window.innerWidth - menuWidth - 8, Math.max(8, button.right - menuWidth));
                                                                this.menuTop = button.bottom + menuHeight + 8 <= window.innerHeight
                                                                    ? button.bottom + 8
                                                                    : Math.max(8, button.top - menuHeight - 8);
                                                            });
                                                        }
                                                    },
                                                }"
                                                @keydown.escape.window="open = false"
                                            >
                                                <button
                                                    x-ref="trigger"
                                                    type="button"
                                                    class="app-button-secondary !px-3 !py-1.5 !text-lg !leading-none"
                                                    aria-label="Parent actions"
                                                    :aria-expanded="open.toString()"
                                                    @click="toggleMenu()"
                                                >
                                                    &hellip;
                                                </button>
                                                <template x-teleport="body">
                                                    <div
                                                        x-ref="menu"
                                                        x-cloak
                                                        x-show="open"
                                                        @click.outside="open = false"
                                                        :style="`top: ${menuTop}px; left: ${menuLeft}px;`"
                                                        class="fixed z-[100] grid min-w-44 gap-1 rounded-lg border border-slate-200 bg-white p-2 shadow-xl dark:border-zinc-700 dark:bg-zinc-900"
                                                    >
                                                    <button type="button" class="w-full cursor-pointer rounded px-3 py-2 text-left text-sm font-medium text-teal-700 hover:bg-teal-50 dark:text-teal-300 dark:hover:bg-zinc-800" @click="openParentEditor(@js(route('children.parents.update', ['child' => $child, 'parent' => $parent])), @js($parent->name), @js($parent->email), @js($parent->phone), @js($parent->pivot->relationship)); open = false">Edit parent</button>
                                                    @if (! $parent->invitation_accepted_at && ($parent->email || $parent->phone))
                                                        <form method="POST" action="{{ route('children.parents.setup-link', ['child' => $child, 'parent' => $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the setup link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">
                                                            @csrf
                                                            <button type="submit" class="w-full cursor-pointer rounded px-3 py-2 text-left text-sm font-medium text-teal-700 hover:bg-teal-50 dark:text-teal-300 dark:hover:bg-zinc-800">Resend activation instructions</button>
                                                        </form>
                                                    @endif
                                                    @if ($parent->parentLoginChannel() === 'email')
                                                        <form method="POST" action="{{ route('children.parents.password-link', [$child, $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the reset link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">@csrf<input type="hidden" name="channel" value="email"><button type="submit" class="w-full cursor-pointer rounded px-3 py-2 text-left text-sm font-medium text-teal-700 hover:bg-teal-50 dark:text-teal-300 dark:hover:bg-zinc-800">Reset by email</button></form>
                                                    @endif
                                                        @if ($parent->parentLoginChannel() === 'sms')
                                                        <form method="POST" action="{{ route('children.parents.password-link', [$child, $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the reset link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">@csrf<input type="hidden" name="channel" value="sms"><button type="submit" class="w-full cursor-pointer rounded px-3 py-2 text-left text-sm font-medium text-teal-700 hover:bg-teal-50 dark:text-teal-300 dark:hover:bg-zinc-800">Reset by text</button></form>
                                                    @endif
                                                    <form
                                                        method="POST"
                                                        action="{{ route('children.parents.destroy', ['child' => $child, 'parent' => $parent]) }}"
                                                        @submit.prevent="showConfirmModal('Unlink parent', 'Unlink this parent from the child profile?', $event.currentTarget)"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="w-full rounded px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30">Unlink</button>
                                                    </form>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-500">No parent account linked yet.</td></tr>
                                @endforelse
                                    </tbody>
                                </table>
                            </div>

                            </section>

                            <form method="POST" action="{{ route('children.parents.store', $child) }}" class="app-card order-first overflow-hidden" x-data="{ inviteParentOpen: @js($errors->hasAny(['name', 'email', 'phone', 'relationship'])) }">
                                @csrf
                                <div class="app-card-header flex items-center justify-between gap-3">
                                    <h2 class="app-card-title">Invite Parent</h2>
                                    <button type="button" class="app-button-secondary shrink-0" @click="inviteParentOpen = !inviteParentOpen" :aria-expanded="inviteParentOpen.toString()" aria-controls="invite-parent-fields" x-text="inviteParentOpen ? '− Hide' : '+ Invite Parent'">+ Invite Parent</button>
                                </div>
                                <div id="invite-parent-fields" x-show="inviteParentOpen" x-cloak class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
                                <p class="text-sm text-slate-600 dark:text-zinc-300 sm:col-span-2 lg:col-span-4">Link the parent using either an email address or a phone number. Email parents receive activation instructions by email, while phone-only parents can finish sign up using that phone number and a password.</p>
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
                                <button class="app-button-primary sm:col-span-2 lg:col-span-4">Link parent account</button>
                                </div>
                            </form>
                @endif
            </div>
        </div>

        @php
            $calendarStart = $scheduleMonth->copy()->startOfWeek(\Illuminate\Support\Carbon::SUNDAY);
            $calendarItemsByDate = collect($scheduleItems)->groupBy(fn (array $item): string => $item['date']->toDateString());
        @endphp
        <section x-cloak x-show="childViewTab === 'schedule'" class="app-card min-w-0 overflow-hidden">
            <div class="app-card-header flex min-w-0 flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="eyebrow">Child schedule</p>
                    <h2 class="app-card-title mt-1">Immunization Schedule</h2>
                </div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-600 dark:text-zinc-300">
                    <span class="sr-only">Select month and year</span>
                    <span class="relative block">
                        <input type="month" wire:model.live="calendarMonth" wire:loading.attr="disabled" wire:target="calendarMonth" class="app-input !w-auto !py-1.5 disabled:cursor-wait disabled:opacity-60">
                        <span wire:loading wire:target="calendarMonth" class="pointer-events-none absolute right-2 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-teal-200 border-t-teal-600"></span>
                    </span>
                </label>
            </div>
            <div class="grid min-w-0 lg:grid-cols-[1.35fr_0.65fr]">
            <div class="min-w-0 p-3 sm:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Showing schedule for {{ $scheduleMonth->format('F Y') }}</p>
                    <span class="hidden text-xs text-slate-500 dark:text-zinc-400 sm:inline">Selected month</span>
                </div>
                <div class="grid w-full min-w-0 grid-cols-7 auto-cols-fr text-center text-[10px] font-semibold uppercase tracking-wide text-slate-400 sm:text-xs">
                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                        <div class="min-w-0 py-2">{{ $weekday }}</div>
                    @endforeach
                </div>
                <div class="grid w-full min-w-0 grid-cols-7 auto-cols-fr overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-800">
                    @for ($dayIndex = 0; $dayIndex < 42; $dayIndex++)
                        @php
                            $calendarDay = $calendarStart->copy()->addDays($dayIndex);
                            $dayItems = $calendarItemsByDate->get($calendarDay->toDateString(), collect());
                            $isCurrentMonth = $calendarDay->month === $scheduleMonth->month;
                            $isToday = $calendarDay->isToday();
                        @endphp
                        <button type="button" wire:click="selectScheduleDate('{{ $calendarDay->toDateString() }}')" wire:loading.attr="disabled" wire:target="selectScheduleDate" class="min-h-16 min-w-0 overflow-hidden border-b border-r border-slate-100 p-1.5 text-left transition hover:bg-teal-50 disabled:cursor-wait disabled:opacity-60 last:border-r-0 dark:border-zinc-800 dark:hover:bg-zinc-800 sm:min-h-24 sm:p-2 {{ $isCurrentMonth ? 'bg-white dark:bg-zinc-900' : 'bg-slate-50/70 text-slate-300 dark:bg-zinc-950/60 dark:text-zinc-700' }} {{ $selectedScheduleDateValue?->isSameDay($calendarDay) ? 'bg-teal-50 ring-2 ring-inset ring-teal-500 dark:bg-teal-950/40' : '' }}" aria-label="Show doses for {{ $calendarDay->format('F j, Y') }}">
                            <div class="flex justify-end"><span class="{{ $isToday ? 'flex size-6 items-center justify-center rounded-full bg-teal-600 font-bold text-white' : 'text-xs font-medium text-slate-500 dark:text-zinc-400' }}">{{ $calendarDay->day }}</span></div>
                            <div class="mt-1 space-y-1">
                                @foreach ($dayItems->take(2) as $item)
                                    @php
                                        $eventClass = match ($item['status']) {
                                            'completed' => 'bg-emerald-100 text-emerald-700',
                                            'pending' => 'bg-amber-100 text-amber-700',
                                            'overdue', 'delayed' => 'bg-rose-100 text-rose-700',
                                            default => 'bg-sky-100 text-sky-700',
                                        };
                                    @endphp
                                    <div class="min-w-0 truncate rounded px-1 py-0.5 text-[9px] font-semibold {{ $eventClass }}" title="{{ $item['vaccine'] }} dose {{ $item['dose'] }}">{{ str($item['vaccine'])->limit(10) }}</div>
                                @endforeach
                                @if ($dayItems->count() > 2)
                                    <div class="text-[9px] font-semibold text-slate-400">+{{ $dayItems->count() - 2 }} more</div>
                                @endif
                            </div>
                        </button>
                    @endfor
                </div>
                <div class="mt-4 flex flex-wrap gap-3 text-xs text-slate-600 dark:text-zinc-300">
                    <span><i class="mr-1 inline-block size-2 rounded-full bg-sky-500"></i>Due</span>
                    <span><i class="mr-1 inline-block size-2 rounded-full bg-emerald-500"></i>Completed</span>
                    <span><i class="mr-1 inline-block size-2 rounded-full bg-amber-500"></i>Pending</span>
                    <span><i class="mr-1 inline-block size-2 rounded-full bg-rose-500"></i>Overdue</span>
                </div>
            </div>
            <div x-data="{ showOverdue: false }" class="flex min-w-0 flex-col border-t border-slate-100 p-4 dark:border-zinc-800 sm:p-5 lg:border-l lg:border-t-0">
                <div class="order-2">
                <div class="rounded-lg bg-slate-100 px-3 py-2 dark:bg-zinc-800">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-zinc-100">Upcoming and recent doses</h3>
                </div>
                <div class="mt-3 divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($upcomingScheduleItems as $item)
                        <div class="flex min-w-0 flex-wrap items-center gap-3 py-3">
                            <div class="w-24 shrink-0 text-center sm:w-28"><p class="text-xs font-semibold text-teal-700">{{ $item['date']->format('M d, Y') }}</p></div>
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $item['vaccine'] }}</p><p class="text-xs text-slate-500">Dose {{ $item['dose'] }} · {{ $item['location'] }}</p></div>
                            <span class="status-pill w-full justify-center truncate text-[10px] sm:w-auto sm:shrink-0 {{ $item['status'] === 'completed' ? 'status-verified' : ($item['status'] === 'pending' ? 'status-pending' : ($item['status'] === 'overdue' ? 'status-rejected' : 'bg-sky-100 text-sky-700')) }}">{{ str($item['status'])->replace('_', ' ')->headline() }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No schedule entries are available for this child.</p>
                    @endforelse
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs dark:border-zinc-800">
                    <button type="button" wire:click="previousUpcomingPage" wire:loading.attr="disabled" @disabled($upcomingPage <= 1) class="font-semibold text-slate-600 disabled:cursor-not-allowed disabled:opacity-40 dark:text-zinc-300">Previous</button>
                    <span class="text-slate-500 dark:text-zinc-400">Page {{ $upcomingPage }} of {{ $upcomingPages }}</span>
                    <button type="button" wire:click="nextUpcomingPage" wire:loading.attr="disabled" @disabled($upcomingPage >= $upcomingPages) class="font-semibold text-teal-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-teal-300">Next</button>
                </div>
                @if ($overdueScheduleItems->isNotEmpty())
                    <button type="button" class="mt-4 flex w-full items-center justify-between rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-950/30 dark:text-rose-300" @click="showOverdue = !showOverdue">
                        <span x-text="showOverdue ? 'Hide Overdue' : 'Show Overdue'">Show Overdue</span>
                        <span class="rounded-full bg-rose-100 px-2 py-0.5 text-xs dark:bg-rose-900/50">{{ $overdueScheduleItems->count() }}</span>
                    </button>
                    <div x-show="showOverdue" x-cloak class="mt-3 rounded-lg border border-rose-100 bg-rose-50/50 px-3 dark:border-rose-900/50 dark:bg-rose-950/20">
                        @foreach ($overdueScheduleItems as $item)
                            <div class="flex min-w-0 flex-wrap items-center gap-3 border-b border-rose-100 py-3 last:border-b-0 dark:border-rose-900/40">
                                <div class="w-24 shrink-0 text-center"><p class="text-xs font-semibold text-rose-700 dark:text-rose-300">{{ $item['date']->format('M d, Y') }}</p></div>
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $item['vaccine'] }}</p><p class="text-xs text-slate-500">Dose {{ $item['dose'] }} · {{ $item['location'] }}</p></div>
                                <span class="status-pill shrink-0 bg-rose-100 text-[10px] text-rose-700">Overdue</span>
                            </div>
                        @endforeach
                        @if ($overduePages > 1)
                            <div class="flex items-center justify-between gap-3 border-t border-rose-100 py-3 text-xs dark:border-rose-900/40">
                                <button type="button" wire:click="previousOverduePage" wire:loading.attr="disabled" @disabled($overduePage <= 1) class="font-semibold text-rose-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-rose-300">Previous</button>
                                <span class="text-rose-600 dark:text-rose-300">Page {{ $overduePage }} of {{ $overduePages }}</span>
                                <button type="button" wire:click="nextOverduePage" wire:loading.attr="disabled" @disabled($overduePage >= $overduePages) class="font-semibold text-rose-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-rose-300">Next</button>
                            </div>
                        @endif
                    </div>
                @endif
                </div>
                @if ($selectedScheduleDateValue)
                    <div class="order-1 mb-5 border-b border-slate-100 pb-4 dark:border-zinc-800">
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-100 px-3 py-2 dark:bg-zinc-800">
                            <h3 class="text-sm font-semibold text-slate-800 dark:text-zinc-100">Recent doses in this {{ $selectedScheduleDateValue->format('F j, Y') }}</h3>
                            <button type="button" wire:click="clearScheduleDate" class="text-xs font-semibold text-slate-600 hover:text-slate-800 dark:text-zinc-300 dark:hover:text-white">Clear date</button>
                        </div>
                        <div class="mt-3 divide-y divide-slate-100 dark:divide-zinc-800">
                            @forelse ($selectedScheduleItems as $item)
                                <div class="flex min-w-0 flex-wrap items-center gap-3 py-3">
                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $item['vaccine'] }}</p><p class="text-xs text-slate-500">Dose {{ $item['dose'] }} · {{ $item['location'] }}</p></div>
                                    <span class="status-pill shrink-0 text-[10px] {{ $item['status'] === 'completed' ? 'status-verified' : ($item['status'] === 'pending' ? 'status-pending' : ($item['status'] === 'overdue' ? 'status-rejected' : 'bg-sky-100 text-sky-700')) }}">{{ str($item['status'])->replace('_', ' ')->headline() }}</span>
                                </div>
                            @empty
                                <p class="py-4 text-sm text-slate-500">No doses are scheduled for this date.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
            </div>
        </section>

        <div x-show="recordDetailsOpen" x-cloak x-on:keydown.escape.window="recordDetailsOpen = false" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true" aria-labelledby="record-details-title">
            <div class="app-panel w-full max-w-lg" @click.stop>
                <div class="flex items-start justify-between gap-4">
                    <div><p class="eyebrow">Vaccination record</p><h2 id="record-details-title" class="app-card-title mt-1" x-text="recordDetails.vaccine"></h2></div>
                    <button type="button" class="app-button-secondary !px-3 !py-1.5" @click="recordDetailsOpen = false">Close</button>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950"><p class="text-xs font-semibold uppercase text-slate-500">Dose</p><p class="mt-1 text-sm font-semibold" x-text="recordDetails.dose"></p></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950"><p class="text-xs font-semibold uppercase text-slate-500">Status</p><p class="mt-1 text-sm font-semibold" x-text="recordDetails.status"></p></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950"><p class="text-xs font-semibold uppercase text-slate-500">Date given</p><p class="mt-1 text-sm font-semibold" x-text="recordDetails.date"></p></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950"><p class="text-xs font-semibold uppercase text-slate-500">Source</p><p class="mt-1 text-sm font-semibold" x-text="recordDetails.source"></p></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950"><p class="text-xs font-semibold uppercase text-slate-500">Clinic</p><p class="mt-1 text-sm" x-text="recordDetails.clinic || '—'"></p></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950"><p class="text-xs font-semibold uppercase text-slate-500">Location</p><p class="mt-1 text-sm" x-text="recordDetails.location || '—'"></p></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950"><p class="text-xs font-semibold uppercase text-slate-500">Next due</p><p class="mt-1 text-sm" x-text="recordDetails.next || 'None'"></p></div>
                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-zinc-950"><p class="text-xs font-semibold uppercase text-slate-500">Suggested vaccine</p><p class="mt-1 text-sm" x-text="recordDetails.suggestion || 'None'"></p></div>
                </div>
                <div x-show="recordDetails.proofImages && recordDetails.proofImages.length" class="mt-4"><p class="text-xs font-semibold uppercase text-slate-500">Uploaded proof</p><div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3"><template x-for="image in recordDetails.proofImages" :key="image"><a :href="image" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-zinc-800 dark:bg-zinc-950"><img :src="image" alt="Uploaded vaccination proof" class="aspect-square size-full object-cover"></a></template></div></div>
                <div class="mt-4 space-y-2 text-sm text-slate-600 dark:text-zinc-300"><p><span class="font-semibold">Submitted by:</span> <span x-text="recordDetails.submitter"></span></p><p><span class="font-semibold">Recorded or verified by:</span> <span x-text="recordDetails.verifier"></span></p><p x-show="recordDetails.remarks"><span class="font-semibold">Remarks:</span> <span x-text="recordDetails.remarks"></span></p><p x-show="recordDetails.proofs > 0"><span class="font-semibold">Proof photos:</span> <span x-text="recordDetails.proofs"></span></p></div>
            </div>
        </div>

        <form method="POST" x-ref="verificationForm" class="hidden">
            @csrf
            <input type="hidden" name="remarks" x-model="verificationRemark">
        </form>

        <div
            x-cloak
            x-show="profilePhotoOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
            x-transition.opacity
            @keydown.escape.window="profilePhotoOpen = false"
        >
            <div
                @click.outside="profilePhotoOpen = false"
                class="w-full max-w-3xl rounded-2xl bg-white p-4 shadow-xl dark:bg-zinc-900 sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="profile-photo-modal-title"
            >
                <div class="flex items-center justify-between gap-3">
                    <h2 id="profile-photo-modal-title" class="text-lg font-semibold text-slate-950 dark:text-white">{{ $child->full_name }} photo</h2>
                    <button type="button" class="app-button-secondary" @click="profilePhotoOpen = false">Close</button>
                </div>
                <div class="mt-4 flex min-h-96 items-center justify-center rounded-lg bg-zinc-950 p-3">
                    <img src="{{ route('children.photo', $child) }}" alt="Photo of {{ $child->full_name }}" class="max-h-[70vh] max-w-full object-contain">
                </div>
            </div>
        </div>

        <div
            x-cloak
            x-show="proofModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
            x-transition.opacity
            @keydown.escape.window="closeProofModal()"
        >
            <div
                @click.outside="closeProofModal()"
                class="w-full max-w-4xl rounded-2xl bg-white p-4 shadow-xl dark:bg-zinc-900 sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="proof-modal-title"
            >
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 id="proof-modal-title" class="text-lg font-semibold text-slate-950 dark:text-white">Submitted vaccine proof</h2>
                        <p class="text-sm text-zinc-500" x-text="`Photo ${proofModalIndex + 1} of ${proofModalImages.length}`"></p>
                    </div>
                    <button type="button" class="app-button-secondary" @click="closeProofModal()">Close</button>
                </div>
                <div class="mt-4 flex min-h-96 items-center justify-center rounded-lg bg-zinc-950 p-3">
                    <img :src="proofModalImages[proofModalIndex]" :alt="`Submitted vaccine proof photo ${proofModalIndex + 1}`" class="max-h-[70vh] max-w-full object-contain">
                </div>
                <div x-show="proofModalImages.length > 1" class="mt-4 flex items-center justify-between gap-3">
                    <button type="button" class="app-button-secondary" :disabled="proofModalIndex === 0" @click="proofModalIndex = Math.max(0, proofModalIndex - 1)">Previous</button>
                    <button type="button" class="app-button-primary" :disabled="proofModalIndex === proofModalImages.length - 1" @click="proofModalIndex = Math.min(proofModalImages.length - 1, proofModalIndex + 1)">Next</button>
                </div>
            </div>
        </div>

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
                <div x-show="verificationActionLabel === 'Reject'" class="mt-5">
                    <label for="verification-remark" class="mb-2 block text-sm font-medium text-slate-800 dark:text-zinc-100">Rejection remark <span class="text-red-600">*</span></label>
                    <textarea id="verification-remark" x-model="verificationRemark" rows="4" maxlength="1000" class="app-input w-full" placeholder="Explain why this vaccination record was rejected so the parent can correct it."></textarea>
                </div>
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

    @if (auth()->user()->canManageChildren())
        <script>
            (() => {
                const form = document.querySelector('form[action="{{ route('children.vaccinations.store', $child) }}"]');
                if (!form) return;

                const queueKey = 'offline-vaccination-queue-{{ $child->id }}';

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
