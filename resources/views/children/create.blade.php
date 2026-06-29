    <div class="app-page">
        <div class="mx-auto w-full max-w-3xl">
            <div class="mb-6">
                <p class="eyebrow">Registry</p>
                <h1 class="page-title">Create child profile</h1>
                <p class="page-subtitle">Add the child's demographic and guardian information before recording vaccinations.</p>
            </div>

            @include('children._form', ['child' => $child, 'barangays' => $barangays])
        </div>
    </div>
