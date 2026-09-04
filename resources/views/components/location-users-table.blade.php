@props(['users', 'addRoute', 'locationTree' => [], 'heading' => 'Assigned users'])

<div class="mt-2 border-t border-slate-200 pt-3 dark:border-zinc-700">
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-semibold">{{ $heading }}</h4>
        <flux:modal.trigger name="add-user-{{ md5($addRoute) }}">
            <flux:button size="sm" icon="plus" aria-label="Add user" />
        </flux:modal.trigger>
    </div>

    <flux:modal name="add-user-{{ md5($addRoute) }}" class="md:w-[34rem]">
        <form method="POST" action="{{ $addRoute }}" class="space-y-6">
            @csrf
            <div><flux:heading size="lg">Add user</flux:heading><flux:text class="mt-2">Enter the user’s name and email address.</flux:text></div>
            <flux:input label="Full name" name="name" placeholder="Full name" required />
            <flux:input label="Email address" name="email" type="email" placeholder="name@example.com" required />
            <div class="flex"><flux:spacer /><flux:modal.close><flux:button type="button" variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Add user</flux:button></div>
        </form>
    </flux:modal>

    <div class="mt-3 overflow-x-auto"><table class="app-table"><thead><tr><th class="px-3 py-2">Name</th><th class="px-3 py-2">Email</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Actions</th></tr></thead><tbody>
        @forelse ($users as $user)
            @php($modalName = 'reassign-user-'.md5((string) $user->id))
            <tr class="app-table-row"><td class="px-3 py-2 font-medium">{{ $user->name }}</td><td class="px-3 py-2">{{ $user->email }}</td><td class="px-3 py-2">{{ $user->is_active && $user->invitation_accepted_at ? 'Active' : 'Pending' }}</td><td class="px-3 py-2"><div class="flex gap-2"><form method="POST" action="{{ route('locations.users.remove', $user) }}">@csrf<button class="app-button-danger !px-2 !py-1 !text-xs">Remove</button></form><flux:modal.trigger name="{{ $modalName }}"><flux:button size="sm">Reassign</flux:button></flux:modal.trigger></div></td></tr>
            <flux:modal name="{{ $modalName }}" class="md:w-[34rem]" x-data="{ region: '', province: '', municipality: '', barangay: '', target: 'municipality', locations: @js($locationTree) }">
                <form method="POST" action="{{ route('locations.users.reassign', $user) }}" class="space-y-6">
                    @csrf
                    <div><flux:heading size="lg">Reassign {{ $user->name }}</flux:heading><flux:text class="mt-2">Choose a municipality or barangay for this user.</flux:text></div>
                    <select x-model="region" @change="province = ''; municipality = ''; barangay = ''" class="app-input" required><option value="">Select region</option><template x-for="item in locations" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select>
                    <select x-model="province" @change="municipality = ''; barangay = ''" class="app-input" required><option value="">Select province</option><template x-for="item in (locations.find(item => item.id === region)?.provinces ?? [])" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select>
                    <select x-model="municipality" @change="barangay = ''" class="app-input" required><option value="">Select municipality/city</option><template x-for="item in (locations.flatMap(region => region.provinces).find(item => item.id === province)?.municipalities ?? [])" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select>
                    <div class="flex gap-5"><label class="flex items-center gap-2"><input type="radio" value="municipality" x-model="target"> Municipality</label><label class="flex items-center gap-2"><input type="radio" value="barangay" x-model="target"> Barangay</label></div>
                    <select x-show="target === 'barangay'" name="barangay_id" x-model="barangay" :required="target === 'barangay'" class="app-input"><option value="">Select barangay</option><template x-for="item in (locations.flatMap(region => region.provinces).flatMap(province => province.municipalities).find(item => item.id === municipality)?.barangays ?? [])" :key="item.id"><option :value="item.id" x-text="item.name"></option></template></select>
                    <input type="hidden" name="municipality_id" x-model="municipality">
                    <div class="flex"><flux:spacer /><flux:modal.close><flux:button type="button" variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save reassignment</flux:button></div>
                </form>
            </flux:modal>
        @empty
            <tr><td colspan="4" class="px-3 py-3 text-sm text-zinc-500">No users assigned.</td></tr>
        @endforelse
    </tbody></table></div>
</div>
