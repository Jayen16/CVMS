    <div class="app-page">
        <div class="mx-auto w-full max-w-3xl">
            <div class="mb-6">
                <p class="eyebrow">Registry</p>
                <h1 class="page-title">Edit child profile</h1>
                <p class="page-subtitle">Update the child's demographic and guardian information.</p>
            </div>

            @include('children._form', ['child' => $child, 'barangays' => $barangays])
        </div>
    </div>
