    <div class="app-page">
        <div class="mx-auto w-full max-w-6xl">
            <div class="page-heading mb-6">
                <div>
                    <p class="eyebrow">Registry</p>
                    <h1 class="page-title">Create child profile</h1>
                    <p class="page-subtitle">Add the child's demographic information. Parents can be linked from the child profile.</p>
                </div>
            </div>

            @include('children._form', ['child' => $child, 'barangays' => $barangays])
        </div>
    </div>
