@php
    $editParent = $child->parents->firstWhere('id', session('edit_parent_id'));
    $initialChildViewTab = 'schedule';
    $compactHistory = true;

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
            expandedRecordId: null,
            recordDetails: {},
            recordStep: 'details',
            toggleRecordDetails(id, record) {
                this.expandedRecordId = this.expandedRecordId === id ? null : id;
                this.recordDetails = record;
                this.recordStep = 'details';
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
            <form method="POST" enctype="multipart/form-data" x-bind:action="editParentAction" class="app-panel w-full max-w-2xl" @click.stop>
                @csrf
                @method('PUT')
                <h2 id="edit-parent-title" class="app-card-title">Edit parent information</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Parent name</span><input class="app-input" type="text" name="edit_name" x-model="editParentName" required>@error('edit_name', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Parent email</span><input class="app-input" type="email" name="edit_email" x-model="editParentEmail">@error('edit_email', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Parent cellphone</span><input class="app-input" type="text" name="edit_phone" x-model="editParentPhone">@error('edit_phone', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="grid gap-1.5 text-sm"><span class="font-medium">Relationship</span><select class="app-input" name="edit_relationship" x-model="editParentRelationship" required><option value="mother">Mother</option><option value="father">Father</option><option value="guardian">Guardian</option><option value="aunt">Aunt</option><option value="uncle">Uncle</option><option value="grandmother">Grandmother</option><option value="grandfather">Grandfather</option><option value="other">Other</option></select>@error('edit_relationship', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
                    <label class="grid gap-1.5 text-sm sm:col-span-2"><span class="font-medium">Parent profile photo <span class="font-normal text-slate-500">(optional)</span></span><input class="app-input" type="file" name="edit_photo" accept=".jpg,.jpeg,.png,.webp">@error('edit_photo', 'editParent')<span class="text-xs text-red-600">{{ $message }}</span>@enderror</label>
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
                    <div class="flex flex-wrap items-center gap-2">
                        @if (auth()->user()->isParent() && $suggestion['vaccine_type_id'])
                            <a href="{{ route('vaccine-information.show', $suggestion['vaccine_type_id']) }}" class="app-button-primary !px-3 !py-2 !text-xs" wire:navigate>Learn about this vaccine</a>
                        @endif
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
                        <h2 class="app-card-title">Vaccination timeline</h2>
                        <p class="mt-1 text-sm text-zinc-500">{{ $vaccinations->total() }} dose{{ $vaccinations->total() === 1 ? '' : 's' }} recorded · click a dose to view its vaccination record and verification details.</p>
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
                <div class="border-b border-slate-100 px-5 py-4 dark:border-zinc-800">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Vaccination overview</h3>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">A quick view of recent doses and their current status.</p>
                        </div>
                        <span class="hidden rounded-full bg-teal-50 px-2.5 py-1 text-[11px] font-semibold text-teal-700 sm:inline-flex dark:bg-teal-950 dark:text-teal-300">{{ $vaccinations->total() }} records</span>
                    </div>
                    <div class="vaccination-mini-timeline">
                        @forelse ($vaccinations as $record)
                            @php
                                $timelineStatus = match ($record->verification_status) {
                                    'verified' => 'verified',
                                    'pending' => 'pending',
                                    default => 'rejected',
                                };
                            @endphp
                            <div class="vaccination-mini-item cursor-pointer" role="button" tabindex="0" @click="toggleRecordDetails(@js($record->id), @js(['id' => $record->id]))" @keydown.enter.prevent="toggleRecordDetails(@js($record->id), @js(['id' => $record->id]))" @keydown.space.prevent="toggleRecordDetails(@js($record->id), @js(['id' => $record->id]))">
                                <span class="vaccination-mini-dot vaccination-mini-dot-{{ $timelineStatus }}"><span></span></span>
                                <div class="min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50/80 px-3 py-2.5 dark:border-zinc-800 dark:bg-zinc-950/50">
                                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $record->vaccineType->name }} <span class="font-normal text-slate-500">· Dose {{ $record->dose_number ?: '—' }}</span></p>
                                        <span class="status-pill !px-2 !py-0.5 !text-[10px] @if($timelineStatus === 'verified') status-verified @elseif($timelineStatus === 'pending') status-pending @else status-rejected @endif">{{ $timelineStatus === 'verified' ? 'Verified' : ucfirst($timelineStatus) }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ $record->administered_at->format('M d, Y') }} · {{ str($record->source)->replace('_', ' ')->title() }}</p>
                                    <div x-show="expandedRecordId === @js($record->id)" x-cloak x-transition @click.stop class="mt-3 border-t border-slate-200 pt-3 text-left dark:border-zinc-800">
                                        <div class="record-section-heading"><span>✓</span><strong>Information submitted</strong></div>
                                        <div class="mt-3 grid gap-3 text-xs text-slate-600 dark:text-zinc-300 sm:grid-cols-2 lg:grid-cols-4">
                                            <p><strong class="block text-slate-500">Vaccine</strong><span class="mt-1 block text-sm font-medium text-slate-900 dark:text-white">{{ $record->vaccineType->name }}</span></p>
                                            <p><strong class="block text-slate-500">Dose</strong><span class="mt-1 block text-sm font-medium text-slate-900 dark:text-white">{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }}</span></p>
                                            <p><strong class="block text-slate-500">Date given</strong><span class="mt-1 block text-sm font-medium text-slate-900 dark:text-white">{{ $record->administered_at->format('M d, Y') }}</span></p>
                                            <p><strong class="block text-slate-500">Source</strong><span class="mt-1 block text-sm font-medium text-slate-900 dark:text-white">{{ str($record->source)->replace('_', ' ')->title() }}</span></p>
                                        </div>
                                        <div class="mt-4 grid gap-3 border-t border-slate-200 pt-3 text-xs text-slate-600 dark:border-zinc-800 dark:text-zinc-300 sm:grid-cols-3">
                                            <p><strong class="block text-slate-500">Clinic</strong>{{ $record->clinic_name ?: '—' }}</p>
                                            <p><strong class="block text-slate-500">Location</strong>{{ $record->clinic_location ?: '—' }}</p>
                                            <p><strong class="block text-slate-500">Next due</strong>{{ $record->next_due_at?->format('M d, Y') ?: 'None' }}</p>
                                        </div>
                                        @php
                                            $proofUrls = $this->proofImageUrls($record);
                                        @endphp
                                        @if ($record->parentProofIndexes() !== [])
                                            <div class="mt-4 border-t border-slate-200 pt-3 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Proof attached</strong></div><p class="mt-1 pl-7">Uploaded by {{ $record->parentProofUploaderSummary() ?: 'Parent' }}</p><div class="mt-2 flex flex-wrap gap-2 pl-7">@foreach ($record->parentProofIndexes() as $proofIndex)@if (isset($proofUrls[$proofIndex]))<a href="{{ $proofUrls[$proofIndex] }}" target="_blank" rel="noopener" @click.stop class="size-14 overflow-hidden rounded-lg border border-slate-200 dark:border-zinc-700"><img src="{{ $proofUrls[$proofIndex] }}" alt="Vaccination proof uploaded by parent" class="size-full object-cover"></a>@endif @endforeach</div></div>
                                        @else
                                            <div class="mt-4 border-t border-slate-200 pt-3 text-slate-500 dark:border-zinc-800"><div class="record-section-heading record-section-heading-muted"><span>2</span><strong>No proof photo attached</strong></div></div>
                                        @endif
                                        <div class="mt-4 border-t border-slate-200 pt-3 dark:border-zinc-800"><div class="record-section-heading @if ($record->verification_status === 'pending') record-section-heading-pending @elseif ($record->verification_status === 'rejected') record-section-heading-rejected @endif"><span>@if ($record->verification_status === 'verified')✓ @elseif ($record->verification_status === 'pending')◷ @else! @endif</span><strong>@if ($record->verification_status === 'verified')Verified by Nurse @elseif ($record->verification_status === 'pending')Wait for approval @else Rejected by Nurse @endif</strong></div><p class="mt-1 pl-7 text-xs text-slate-500 dark:text-zinc-400">@if ($record->verification_status === 'verified'){{ $record->verifier?->name ?? $record->recordedByDisplayName() }}@if ($record->verified_at) on {{ $record->verified_at->format('M d, Y g:i A') }}@endif. Your vaccination record is confirmed and included in the timeline.@elseif ($record->verification_status === 'pending')The clinic team is checking your submitted details and proof.@else {{ $record->verifier?->name ?? $record->recordedByDisplayName() }}@if ($record->verified_at) on {{ $record->verified_at->format('M d, Y') }}@endif. This vaccination record was rejected.@endif</p>@if ($record->nurseProofIndexes() !== [])<div class="mt-3 pl-7"><strong class="text-xs text-slate-500">Proof uploaded by {{ $record->nurseProofUploaderSummary() }}</strong><div class="mt-2 flex flex-wrap gap-2">@foreach ($record->nurseProofIndexes() as $proofIndex)@if (isset($proofUrls[$proofIndex]))<a href="{{ $proofUrls[$proofIndex] }}" target="_blank" rel="noopener" @click.stop class="size-14 overflow-hidden rounded-lg border border-slate-200 dark:border-zinc-700"><img src="{{ $proofUrls[$proofIndex] }}" alt="Vaccination proof uploaded by nurse" class="size-full object-cover"></a>@endif @endforeach</div></div>@endif @if ($record->remarks)<p class="mt-3 pl-7 whitespace-pre-line text-sm text-slate-700 dark:text-zinc-200"><strong class="text-slate-500">Remarks:</strong> {{ $record->remarks }}</p>@endif</div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <p class="py-3 text-sm text-slate-500">No vaccination records yet.</p>
                        @endforelse
                    </div>
                </div>
                @if (! $compactHistory)
                <div class="grid gap-3 p-3 lg:hidden">
                    @forelse ($vaccinations as $record)
                        <article class="rounded-xl border border-slate-200 p-4 dark:border-zinc-700">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('children.timeline', ['child' => $child, 'vaccine' => $record->vaccineType->code]) }}" class="font-semibold text-teal-700 hover:underline dark:text-teal-300">{{ $record->vaccineType->name }}</a>
                                    <p class="mt-0.5 text-xs text-zinc-500">{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }} · {{ $record->administered_at->format('M d, Y') }}</p>
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1.5"><span class="status-pill @if ($record->verification_status === 'verified') status-verified @elseif ($record->verification_status === 'pending') status-pending @else status-rejected @endif">{{ ucfirst($record->verification_status) }}</span><span class="text-[11px] text-slate-500 dark:text-zinc-400">{{ $record->administered_at->format('M d, Y') }}</span></span>
                            </div>
                            <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Information submitted</strong></div></div>
                            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-3 pt-1 text-sm">
                                <div><dt class="text-xs text-zinc-500">Source</dt><dd class="mt-0.5">{{ str($record->source)->replace('_', ' ')->title() }}@if ($record->clinic_name)<span class="block text-xs text-zinc-500">{{ $record->clinic_name }}</span>@endif</dd></div>
                                <div><dt class="text-xs text-zinc-500">Review</dt><dd class="mt-0.5 text-xs text-zinc-600 dark:text-zinc-300">@if ($record->verification_status === 'pending')Submitted by {{ $record->submitter?->name ?? 'Unknown parent' }}@elseif ($record->verification_status === 'verified')Approved by {{ $record->verifier?->name ?? $record->recordedByDisplayName() }}@else Rejected by {{ $record->verifier?->name ?? $record->recordedByDisplayName() }}@endif</dd></div>
                                <div><dt class="text-xs text-zinc-500">Next suggestion</dt><dd class="mt-0.5">{{ $record->suggested_vaccine ?? 'None' }}@if ($record->next_due_at)<span class="block text-xs text-zinc-500">{{ $record->next_due_at->format('M d, Y') }}</span>@endif</dd></div>
                                <div><dt class="text-xs text-zinc-500">Remarks</dt><dd class="mt-0.5 whitespace-pre-line">{{ $record->remarks ?? '—' }}</dd></div>
                            </dl>
                            @if ($record->proofPaths() !== [])
                                <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Proof attached</strong></div><a href="#" @click.prevent="openProofModal(@js($this->proofImageUrls($record)))" class="mt-2 inline-block text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300">View submitted {{ count($record->proofPaths()) }} photo{{ count($record->proofPaths()) === 1 ? '' : 's' }}</a></div>
                            @else
                                <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><div class="record-section-heading record-section-heading-muted"><span>2</span><strong>No proof attached</strong></div></div>
                            @endif
                            <div class="mt-4 border-t border-slate-100 pt-3 dark:border-zinc-800"><div class="record-section-heading @if ($record->verification_status === 'pending') record-section-heading-pending @endif"><span>@if ($record->verification_status === 'verified')✓ @elseif ($record->verification_status === 'pending')◷ @else ! @endif</span><strong>@if ($record->verification_status === 'pending')Waiting for approval @elseif ($record->verification_status === 'verified')Verified by nurse @else Review required @endif</strong></div>@if ($record->isPendingVerification() && auth()->user()->canVerifyVaccinations())<div class="mt-3 grid grid-cols-2 gap-2"><form method="POST" action="{{ route('vaccinations.verify', $record) }}">@csrf<button type="button" class="app-button-primary w-full !px-3 !py-2 !text-xs" @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.verify', $record)); verificationActionLabel = 'Verify'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name)">Verify</button></form><form method="POST" action="{{ route('vaccinations.reject', $record) }}">@csrf<button type="button" class="app-button-danger w-full !px-3 !py-2 !text-xs" @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.reject', $record)); verificationActionLabel = 'Reject'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name); verificationRemark = ''">Reject</button></form></div>@elseif ($record->verification_status === 'pending')<p class="mt-1 text-xs text-slate-500">Submitted by {{ $record->submitter?->name ?? 'Parent' }}. Check the details and proof before verifying.</p>@endif</div>
                        </article>
                    @empty
                        <div class="app-card p-6 text-center text-sm text-zinc-500">No vaccination records yet.</div>
                    @endforelse
                </div>
                @endif
                @if (false)
                    <div class="grid gap-4 p-3 sm:p-4">
                        @forelse ($vaccinations as $record)
                            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                                <button type="button" class="flex w-full items-center gap-3 p-4 text-left" aria-label="View vaccination record for {{ $record->vaccineType->name }}" @click="toggleRecordDetails(@js($record->id), @js([
                                    'id' => $record->id,
                                    'vaccine' => $record->vaccineType->name,
                                    'dose' => $record->dose_number ? 'Dose '.$record->dose_number : 'Not set',
                                    'date' => $record->administered_at->format('M d, Y'),
                                    'source' => str($record->source)->replace('_', ' ')->title(),
                                    'clinic' => $record->clinic_name,
                                    'location' => $record->clinic_location,
                                    'next' => $record->next_due_at?->format('M d, Y'),
                                    'proofs' => count($record->proofPaths()),
                                    'remarks' => $record->remarks,
                                    'verifier' => $record->verifier?->name ?? $record->recordedByDisplayName(),
                                    'verifiedAt' => $record->verified_at?->format('M d, Y g:i A'),
                                ]))">
                                    <span class="dose-vaccine-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 4.5 5 5M13 6l5 5M4 20l5.5-5.5M7 13l4 4M5 19l-1 1M15 3l6 6"/><path d="m9 15 6-6"/></svg></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-teal-700 dark:text-teal-300">{{ $record->vaccineType->name }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }} · {{ $record->administered_at->format('M d, Y') }}</span></span>
                                    <span class="status-pill shrink-0 @if ($record->verification_status === 'verified') status-verified @elseif ($record->verification_status === 'pending') status-pending @else status-rejected @endif">{{ ucfirst($record->verification_status) }}</span>
                                    <flux:icon.chevron-down class="size-4 shrink-0 text-slate-400 transition-transform" x-bind:class="expandedRecordId === @js($record->id) ? 'rotate-180' : ''" />
                                </button>
                                <div x-show="expandedRecordId === @js($record->id)" x-cloak x-transition class="border-t border-slate-100 bg-slate-50/70 p-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                                    <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                        <div class="flex items-start justify-between gap-3"><div><p class="eyebrow">Vaccination record</p><h3 class="mt-1 text-base font-semibold text-slate-950 dark:text-white" x-text="recordDetails.vaccine"></h3><p class="mt-1 text-xs text-slate-500" x-text="recordDetails.date + ' · ' + recordDetails.source"></p></div><span class="status-pill @if ($record->verification_status === 'verified') status-verified @elseif ($record->verification_status === 'pending') status-pending @else status-rejected @endif">{{ ucfirst($record->verification_status) }}</span></div>
                                        @if ($record->proofPaths() !== [])<p class="mt-2 text-xs text-slate-500">Proof uploaded by: {{ $record->proofUploaderSummary() }}</p>@endif
                                        <div class="mt-4 border-t border-slate-100 pt-4 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Information submitted</strong></div><dl class="mt-3 grid gap-3 text-xs text-slate-600 dark:text-zinc-300"><div><dt class="font-semibold text-slate-500">Vaccine</dt><dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" x-text="recordDetails.vaccine"></dd></div><div><dt class="font-semibold text-slate-500">Dose</dt><dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" x-text="recordDetails.dose"></dd></div><div><dt class="font-semibold text-slate-500">Date given</dt><dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" x-text="recordDetails.date"></dd></div><div><dt class="font-semibold text-slate-500">Source</dt><dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" x-text="recordDetails.source"></dd></div></dl></div>
                                        <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 text-xs text-slate-600 dark:border-zinc-800 dark:text-zinc-300 sm:grid-cols-3"><p><strong class="block text-slate-500">Clinic</strong><span x-text="recordDetails.clinic || '—'"></span></p><p><strong class="block text-slate-500">Location</strong><span x-text="recordDetails.location || '—'"></span></p><p><strong class="block text-slate-500">Next due</strong><span x-text="recordDetails.next || 'None'"></span></p></div>
                                        @if ($record->proofPaths() !== [])<div class="mt-4 border-t border-slate-100 pt-4 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Proof attached</strong></div><p class="mt-1 pl-7 text-xs text-slate-500 dark:text-zinc-400">Uploaded by {{ $record->proofUploaderSummary() }}</p><div class="mt-2 flex flex-wrap gap-2">@foreach ($this->proofImageUrls($record) as $proofUrl)<a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="block size-20 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800"><img src="{{ $proofUrl }}" alt="Vaccination proof" class="size-full object-cover" loading="lazy"></a>@endforeach</div></div>@else<div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500 dark:border-zinc-800 dark:text-zinc-400"><div class="record-section-heading record-section-heading-muted"><span>2</span><strong>No proof photo attached</strong></div></div>@endif
                                        <div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-600 dark:border-zinc-800 dark:text-zinc-300" x-show="recordDetails.remarks"><div class="record-section-heading"><span>✓</span><strong>Nurse review note</strong></div><p class="mt-2 whitespace-pre-line pl-7" x-text="recordDetails.remarks"></p></div>
                                        <div class="mt-4 border-t border-slate-100 pt-4"><div class="record-section-heading @if ($record->verification_status === 'pending') record-section-heading-pending @endif"><span>@if ($record->verification_status === 'verified')✓ @else◷ @endif</span><strong>@if ($record->verification_status === 'pending')Wait for approval @elseif ($record->verification_status === 'verified')Verified by nurse @else Review required @endif</strong></div><p class="mt-1 pl-7 text-xs text-slate-500 dark:text-zinc-400">@if ($record->verification_status === 'pending')The clinic team is checking your submitted details and proof.@elseif ($record->verification_status === 'verified')Verified by {{ $record->verifier?->name ?? $record->recordedByDisplayName() }}@if ($record->verified_at) on {{ $record->verified_at->format('M d, Y g:i A') }}@endif. Your vaccination record is confirmed and included in the timeline.@else Please review the clinic feedback and update your submission.@endif</p></div>
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="p-6 text-center text-sm text-slate-500">No vaccination records yet.</div>
                        @endforelse
                    </div>
                @endif
                <div class="{{ $compactHistory ? 'hidden' : 'hidden lg:block' }} overflow-x-auto">
                    <table class="app-table">
                        <thead>
                            <tr>
                                @if ($compactHistory)
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
                                    @if ($compactHistory)
                                        <td class="font-semibold text-slate-950 dark:text-white"><button type="button" class="inline-flex w-full items-center justify-between gap-3 text-left" aria-label="View details for {{ $record->vaccineType->name }}" @click="toggleRecordDetails(@js($record->id), @js([
                                            'id' => $record->id,
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
                                            'informationUrl' => auth()->user()->isParent() ? route('vaccine-information.show', $record->vaccineType) : null,
                                        ]))"><span class="min-w-0"><span class="block truncate text-teal-700 dark:text-teal-300">{{ $record->vaccineType->name }}</span><span class="mt-0.5 block text-xs font-normal text-slate-500 dark:text-zinc-400">{{ $record->dose_number ? 'Dose '.$record->dose_number : 'Not set' }}</span></span><flux:icon.chevron-right class="size-4 shrink-0 text-slate-400 transition-transform" x-bind:class="expandedRecordId === @js($record->id) ? 'rotate-90' : ''" /></button></td>
                                        <td><span class="inline-flex items-center gap-1.5"><span class="status-pill @if ($record->verification_status === 'verified') status-verified @elseif ($record->verification_status === 'pending') status-pending @else status-rejected @endif">{{ ucfirst($record->verification_status) }}</span><span class="text-xs text-slate-500 dark:text-zinc-400">{{ $record->administered_at->format('M d, Y') }}</span></span></td>
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
                                @if ($compactHistory)
                                    <tr x-show="expandedRecordId === @js($record->id)" x-cloak x-transition>
                                        <td colspan="2" class="bg-slate-50/70 px-4 py-4 dark:bg-zinc-950/50">
                                            <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                                <div class="flex flex-wrap items-start justify-between gap-3">
                                                    <div><p class="eyebrow">Vaccination record</p><h3 class="mt-1 text-base font-semibold text-slate-950 dark:text-white" x-text="recordDetails.vaccine"></h3><p class="mt-1 text-xs text-slate-500" x-text="recordDetails.date + ' · ' + recordDetails.source"></p></div>
                                                    <span class="status-pill @if ($record->verification_status === 'verified') status-verified @elseif ($record->verification_status === 'pending') status-pending @else status-rejected @endif">{{ ucfirst($record->verification_status) }}</span>
                                                </div>
                                                @if ($record->proofPaths() !== [])<p class="mt-2 text-xs text-slate-500">Proof uploaded by: {{ $record->proofUploaderSummary() }}</p>@endif
                                                <div class="mt-4 border-t border-slate-100 pt-4 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Information submitted</strong></div><dl class="mt-3 grid gap-3 text-xs text-slate-600 dark:text-zinc-300 sm:grid-cols-2 lg:grid-cols-4"><div><dt class="font-semibold text-slate-500">Vaccine</dt><dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" x-text="recordDetails.vaccine"></dd></div><div><dt class="font-semibold text-slate-500">Dose</dt><dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" x-text="recordDetails.dose"></dd></div><div><dt class="font-semibold text-slate-500">Date given</dt><dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" x-text="recordDetails.date"></dd></div><div><dt class="font-semibold text-slate-500">Source</dt><dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white" x-text="recordDetails.source"></dd></div></dl></div>
                                                <div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 text-xs text-slate-600 dark:border-zinc-800 dark:text-zinc-300 sm:grid-cols-3"><p><strong class="block text-slate-500">Clinic</strong><span x-text="recordDetails.clinic || '—'"></span></p><p><strong class="block text-slate-500">Location</strong><span x-text="recordDetails.location || '—'"></span></p><p><strong class="block text-slate-500">Next due</strong><span x-text="recordDetails.next || 'None'"></span></p></div>
                                                @if ($record->proofPaths() !== [])<div class="mt-4 border-t border-slate-100 pt-4 dark:border-zinc-800"><div class="record-section-heading"><span>✓</span><strong>Proof attached</strong></div><p class="mt-1 pl-7 text-xs text-slate-500 dark:text-zinc-400">Uploaded by {{ $record->proofUploaderSummary() }}</p><div class="mt-2 flex flex-wrap gap-2">@foreach ($this->proofImageUrls($record) as $proofUrl)<a href="{{ $proofUrl }}" target="_blank" rel="noopener" class="group relative block size-20 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 dark:border-zinc-700 dark:bg-zinc-800"><img src="{{ $proofUrl }}" alt="Vaccination proof" class="size-full object-cover" loading="lazy"><span class="absolute inset-x-0 bottom-0 bg-slate-950/70 px-1 py-1 text-center text-[10px] font-semibold text-white opacity-0 transition group-hover:opacity-100">Open photo</span></a>@endforeach</div></div>@else<div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500 dark:border-zinc-800 dark:text-zinc-400"><div class="record-section-heading record-section-heading-muted"><span>2</span><strong>No proof photo attached</strong></div></div>@endif
                                                <div class="mt-4 border-t border-slate-100 pt-4"><div class="record-section-heading @if ($record->verification_status === 'pending') record-section-heading-pending @endif"><span>@if ($record->verification_status === 'verified')✓ @else◷ @endif</span><strong>@if ($record->verification_status === 'pending')Wait for approval @elseif ($record->verification_status === 'verified')Verified by nurse @else Review required @endif</strong></div><p class="mt-1 pl-7 text-xs text-slate-500 dark:text-zinc-400">@if ($record->verification_status === 'pending')The clinic team is checking your submitted details and proof.@elseif ($record->verification_status === 'verified')Your vaccination record is confirmed and included in the timeline.@else Please review the clinic feedback and update your submission.@endif</p>@if ($record->isPendingVerification() && auth()->user()->canVerifyVaccinations())<div class="mt-3 flex flex-wrap gap-2 pl-7"><button type="button" class="app-button-primary !px-4 !py-2 !text-xs" @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.verify', $record)); verificationActionLabel = 'Verify'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name)">Verify</button><button type="button" class="app-button-danger !px-4 !py-2 !text-xs" @click="openVerificationModal = true; verificationActionUrl = @js(route('vaccinations.reject', $record)); verificationActionLabel = 'Reject'; verificationSubject = @js($child->full_name.' - '.$record->vaccineType->name); verificationRemark = ''">Reject</button></div>@endif</div>
                                                <div class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-600 dark:border-zinc-800 dark:text-zinc-300" x-show="recordDetails.remarks"><div class="record-section-heading"><span>✓</span><strong>Nurse review note</strong></div><p class="mt-2 whitespace-pre-line pl-7" x-text="recordDetails.remarks"></p></div>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
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
                        class="app-panel order-1 grid content-start gap-4"
                        x-data="{ submitHistoryOpen: @js($editableRecord !== null), submitStep: @js($editableRecord ? 2 : 1), proofReady: @js($editableRecord && $editableRecord->proofPaths() !== []) }"
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
                        <div id="submit-vaccination-history-fields" x-show="submitHistoryOpen" x-cloak class="grid gap-5">
                            <p class="text-sm text-slate-600 dark:text-zinc-300">
                                {{ $editableRecord ? 'You can correct this record until it is synchronized to Central. After synchronization, submit a new request if the facility rejects it.' : 'Records given outside the barangay clinic will stay pending until the clinic verifies them.' }}
                            </p>
                            <div class="vaccination-stepper" aria-label="Vaccination submission steps">
                                @foreach ([1 => ['Fill out details', 'Vaccine and date'], 2 => ['Upload proof', 'Card or document'], 3 => ['Submit record', 'Send to clinic'], 4 => ['Wait for approval', 'Nurse review']] as $step => $stepInfo)
                                    <button type="button" class="vaccination-step" :class="submitStep >= {{ $step }} ? 'vaccination-step-active' : ''" @click="submitStep = {{ $step }}">
                                        <span class="vaccination-step-number"><span x-show="submitStep < {{ $step }}">{{ $step }}</span><span x-show="submitStep >= {{ $step }}" x-cloak>✓</span></span>
                                        <span class="hidden text-left sm:block"><strong>{{ $stepInfo[0] }}</strong><small>{{ $stepInfo[1] }}</small></span>
                                    </button>
                                    @if ($step < 4)<span class="vaccination-step-line" :class="submitStep > {{ $step }} ? 'vaccination-step-line-active' : ''"></span>@endif
                                @endforeach
                            </div>
                            <div x-show="submitStep === 1" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <div class="sm:col-span-2 lg:col-span-3"><p class="text-sm font-semibold text-slate-900 dark:text-white">When and what was given?</p><p class="mt-1 text-xs text-slate-500">Use the information exactly as it appears on the vaccine card.</p></div>
                                <x-form-field label="Vaccine" name="vaccine_type_id" type="select" :options="$vaccines->pluck('name', 'id')" :value="$editableRecord?->vaccine_type_id" />
                                <x-form-field label="Dose number" name="dose_number" type="number" :value="$editableRecord?->dose_number" />
                                <x-form-field label="Date given" name="administered_at" type="date" :value="$editableRecord?->administered_at?->toDateString()" />
                                <x-form-field label="Facility or clinic name" name="clinic_name" :value="$defaultClinicName" />
                                <x-form-field label="Facility or clinic location" name="clinic_location" :value="$defaultClinicLocation" />
                            </div>
                            <div x-show="submitStep === 2" x-cloak class="grid gap-4">
                                <div><p class="text-sm font-semibold text-slate-900 dark:text-white">Add your proof</p><p class="mt-1 text-xs text-slate-500">Take a clear photo of the vaccine card, receipt, or clinic record.</p></div>
                                <label class="vaccination-upload-zone">
                                    <span class="flex size-11 items-center justify-center rounded-2xl bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-300"><flux:icon.arrow-up-tray class="size-5" /></span>
                                    <span><strong>Choose document or photos</strong><small>JPG, PNG · up to 5 files · max 5 MB each</small></span>
                                    <input type="file" name="proof_files[]" accept="image/*" multiple class="sr-only" @change="proofReady = $event.target.files.length > 0 || {{ $editableRecord && $editableRecord->proofPaths() !== [] ? 'true' : 'false' }}">
                                </label>
                                <div class="flex items-center gap-2 text-xs" :class="proofReady ? 'text-emerald-700' : 'text-slate-500'"><span class="size-2 rounded-full" :class="proofReady ? 'bg-emerald-500' : 'bg-slate-300'"></span><span x-text="proofReady ? 'Proof attached and ready' : 'No new proof selected yet'"></span></div>
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
                            </div>
                            <div x-show="submitStep === 3" x-cloak class="grid gap-4">
                                <div><p class="text-sm font-semibold text-slate-900 dark:text-white">Review before sending</p><p class="mt-1 text-xs text-slate-500">Check your details and attached proof. You can still go back to make changes.</p></div>
                                <div class="rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 ring-1 ring-slate-200 dark:bg-zinc-950 dark:text-zinc-300 dark:ring-zinc-800"><div class="grid gap-2 sm:grid-cols-2"><span>Child <strong class="block text-slate-950 dark:text-white">{{ $child->full_name }}</strong></span><span>Clinic <strong class="block text-slate-950 dark:text-white">{{ $defaultClinicName }}</strong></span><span>After submission <strong class="block text-slate-950 dark:text-white">Pending nurse verification</strong></span><span>Proof <strong class="block text-slate-950 dark:text-white" x-text="proofReady ? 'Attached' : 'Required'">Required</strong></span></div></div>
                            </div>
                            <div x-show="submitStep === 4" x-cloak class="grid gap-4">
                                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40"><p class="text-sm font-semibold text-amber-900 dark:text-amber-100">What happens next?</p><p class="mt-1 text-sm leading-6 text-amber-800 dark:text-amber-200">A nurse will compare your details with the uploaded proof. Your record will appear as <strong>Pending</strong> until it is verified.</p></div>
                                <div class="grid gap-3 sm:grid-cols-3"><div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-zinc-950 dark:text-zinc-300"><strong class="block text-slate-900 dark:text-white">Submitted</strong>We received your record.</div><div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-zinc-950 dark:text-zinc-300"><strong class="block text-slate-900 dark:text-white">Under review</strong>Nurse checks your proof.</div><div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-zinc-950 dark:text-zinc-300"><strong class="block text-slate-900 dark:text-white">Verified</strong>Timeline is updated.</div></div>
                            </div>
                            <div>
                                <x-form-field label="Remarks" name="remarks" type="textarea" :value="$editableRecord?->remarks" />
                            </div>
                            <div class="flex flex-wrap justify-between gap-2">
                                <button type="button" class="app-button-secondary" x-show="submitStep > 1" @click="submitStep--">Back</button>
                                <span x-show="submitStep === 1"></span>
                                <button type="button" class="app-button-primary" x-show="submitStep < 3" @click="submitStep++">Continue <span aria-hidden="true">→</span></button>
                                <button type="submit" class="app-button-primary" x-show="submitStep === 3">{{ $editableRecord ? 'Save changes' : 'Submit for clinic verification' }}</button>
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
                            <div>
                                <h2 class="app-card-title">Linked parents</h2>
                                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Manage the family members who can access this child’s vaccination records.</p>
                            </div>
                            <div class="order-2 mt-4 grid gap-3">
                                @forelse ($child->parents as $parent)
                                    <article class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 dark:border-zinc-700 dark:bg-zinc-950/40 sm:p-5">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="flex min-w-0 items-center gap-3"><span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-teal-100 text-sm font-bold text-teal-700 dark:bg-teal-950 dark:text-teal-300">@if ($parent->photo_path)<img src="{{ route('children.parents.photo', [$child, $parent]) }}" alt="Photo of {{ $parent->name }}" class="size-full object-cover">@else{{ str($parent->name)->substr(0, 1)->upper() }}@endif</span><div class="min-w-0"><h3 class="font-semibold text-slate-950 dark:text-white">{{ $parent->name }}</h3><p class="mt-0.5 text-xs capitalize text-zinc-500">{{ $parent->pivot->relationship }}</p></div></div>
                                            @if ($parent->invitation_accepted_at)<span class="status-pill status-verified">Configured</span>@else<span class="status-pill status-pending">Setup pending</span>@endif
                                        </div>
                                        <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-sm dark:border-zinc-800">
                                            <div><dt class="text-xs text-zinc-500">Email</dt><dd class="break-words">{{ $parent->email ?: 'No email' }}</dd></div>
                                            <div><dt class="text-xs text-zinc-500">Cellphone</dt><dd>{{ $parent->phone ?: 'No phone' }}</dd></div>
                                        </dl>
                                        <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-zinc-800">
                                            <button type="button" class="app-button-secondary w-full !px-3 !py-1.5 text-xs sm:w-auto" @click="openParentEditor(@js(route('children.parents.update', ['child' => $child, 'parent' => $parent])), @js($parent->name), @js($parent->email), @js($parent->phone), @js($parent->pivot->relationship))">Edit parent</button>
                                            @if (! $parent->invitation_accepted_at && ($parent->email || $parent->phone))
                                                <form class="w-full sm:w-auto" method="POST" action="{{ route('children.parents.setup-link', ['child' => $child, 'parent' => $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the setup link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">@csrf<button class="app-button-secondary w-full !px-3 !py-1.5 text-xs sm:w-auto">Resend setup</button></form>
                                            @endif
                                            @if ($parent->parentLoginChannel() === 'email')
                                                <form class="w-full sm:w-auto" method="POST" action="{{ route('children.parents.password-link', [$child, $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the reset link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">@csrf<input type="hidden" name="channel" value="email"><button class="app-button-secondary w-full !px-3 !py-1.5 text-xs sm:w-auto">Reset by email</button></form>
                                            @endif
                                            @if ($parent->parentLoginChannel() === 'sms')
                                                <form class="w-full sm:w-auto" method="POST" action="{{ route('children.parents.password-link', [$child, $parent]) }}" @submit.prevent="fetch($event.currentTarget.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: new FormData($event.currentTarget) }).then(response => response.json().then(data => { if (! response.ok) throw new Error(data.message || 'Unable to send the reset link.'); window.Flux?.toast({ variant: 'success', text: data.message }); })).catch(error => window.Flux?.toast({ variant: 'danger', text: error.message }))">@csrf<input type="hidden" name="channel" value="sms"><button class="app-button-secondary w-full !px-3 !py-1.5 text-xs sm:w-auto">Reset by text</button></form>
                                            @endif
                                            <form class="w-full sm:w-auto" method="POST" action="{{ route('children.parents.destroy', ['child' => $child, 'parent' => $parent]) }}" @submit.prevent="showConfirmModal('Unlink parent', 'Unlink this parent from the child profile?', $event.currentTarget)">@csrf @method('DELETE')<button class="app-button-danger w-full !px-3 !py-1.5 text-xs sm:w-auto">Unlink parent</button></form>
                                        </div>
                                    </article>
                                @empty
                                    <div class="app-card p-6 text-center text-sm text-zinc-500">No parent account linked yet.</div>
                                @endforelse
                            </div>
                            <div class="hidden">
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

                            <form method="POST" enctype="multipart/form-data" action="{{ route('children.parents.store', $child) }}" class="app-card order-first overflow-hidden" x-show="childViewTab === 'parents'" x-data="{ inviteParentOpen: @js($errors->hasAny(['name', 'email', 'phone', 'relationship', 'photo'])) }">
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
                                <x-form-field label="Parent profile photo (optional)" name="photo" type="file" accept=".jpg,.jpeg,.png,.webp" />
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
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-zinc-100"><span class="dose-section-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 4.5 5 5M13 6l5 5M4 20l5.5-5.5M7 13l4 4M5 19l-1 1M15 3l6 6"/><path d="m9 15 6-6"/></svg></span>Upcoming and recent doses</h3>
                </div>
                <div class="mt-3 divide-y divide-slate-100 dark:divide-zinc-800">
                    @forelse ($upcomingScheduleItems as $item)
                        <div class="flex min-w-0 flex-wrap items-center gap-3 py-3">
                            <div class="w-24 shrink-0 text-center sm:w-28"><p class="text-xs font-semibold text-teal-700">{{ $item['date']->format('M d, Y') }}</p></div>
                            <div class="dose-vaccine-info"><span class="dose-vaccine-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 4.5 5 5M13 6l5 5M4 20l5.5-5.5M7 13l4 4M5 19l-1 1M15 3l6 6"/><path d="m9 15 6-6"/></svg></span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $item['vaccine'] }}</p><p class="text-xs text-slate-500">Dose {{ $item['dose'] }} · {{ $item['location'] }}</p></div></div>
                            <span class="status-pill flex w-full items-center justify-center gap-1.5 truncate text-[10px] sm:w-auto sm:shrink-0 {{ $item['status'] === 'completed' ? 'status-verified' : ($item['status'] === 'pending' ? 'status-pending' : ($item['status'] === 'overdue' ? 'status-rejected' : 'bg-sky-100 text-sky-700')) }}">
                                @if ($item['status'] === 'completed')<svg class="dose-status-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>@elseif ($item['status'] === 'overdue')<svg class="dose-status-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 4h.01M10.3 3.8 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/></svg>@else<svg class="dose-status-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/></svg>@endif
                                {{ str($item['status'])->replace('_', ' ')->headline() }}
                            </span>
                        </div>
                    @empty
                        <p class="py-4 text-sm text-slate-500">No schedule entries are available for this child.</p>
                    @endforelse
                </div>
                <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3 text-xs dark:border-zinc-800">
                    <button type="button" wire:click="previousUpcomingPage" wire:loading.attr="disabled" @disabled($upcomingPage <= 1) class="inline-flex items-center gap-1 font-semibold text-slate-600 disabled:cursor-not-allowed disabled:opacity-40 dark:text-zinc-300"><span aria-hidden="true">←</span> Previous</button>
                    <span class="text-slate-500 dark:text-zinc-400">Page {{ $upcomingPage }} of {{ $upcomingPages }}</span>
                    <button type="button" wire:click="nextUpcomingPage" wire:loading.attr="disabled" @disabled($upcomingPage >= $upcomingPages) class="inline-flex items-center gap-1 font-semibold text-teal-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-teal-300">Next <span aria-hidden="true">→</span></button>
                </div>
                @if ($overdueScheduleItems->isNotEmpty())
                    <button type="button" class="mt-4 flex w-full items-center justify-between rounded-lg bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 dark:bg-rose-950/30 dark:text-rose-300" @click="showOverdue = !showOverdue">
                        <span class="inline-flex items-center gap-2"><svg class="dose-status-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 4h.01M10.3 3.8 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/></svg><span x-text="showOverdue ? 'Hide Overdue' : 'Show Overdue'">Show Overdue</span></span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs dark:bg-rose-900/50"><span>{{ $overdueScheduleItems->count() }}</span><span aria-hidden="true">›</span></span>
                    </button>
                    <div x-show="showOverdue" x-cloak class="mt-3 rounded-lg border border-rose-100 bg-rose-50/50 px-3 dark:border-rose-900/50 dark:bg-rose-950/20">
                        @foreach ($overdueScheduleItems as $item)
                            <div class="flex min-w-0 flex-wrap items-center gap-3 border-b border-rose-100 py-3 last:border-b-0 dark:border-rose-900/40">
                                <div class="w-24 shrink-0 text-center"><p class="text-xs font-semibold text-rose-700 dark:text-rose-300">{{ $item['date']->format('M d, Y') }}</p></div>
                                <div class="dose-vaccine-info"><span class="dose-vaccine-icon dose-vaccine-icon-overdue"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14.5 4.5 5 5M13 6l5 5M4 20l5.5-5.5M7 13l4 4M5 19l-1 1M15 3l6 6"/><path d="m9 15 6-6"/></svg></span><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $item['vaccine'] }}</p><p class="text-xs text-slate-500">Dose {{ $item['dose'] }} · {{ $item['location'] }}</p></div></div>
                                <span class="status-pill inline-flex shrink-0 items-center gap-1.5 bg-rose-100 text-[10px] text-rose-700"><svg class="dose-status-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5m0 4h.01M10.3 3.8 2.7 18a2 2 0 0 0 1.7 3h15.2a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/></svg>Overdue</span>
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
