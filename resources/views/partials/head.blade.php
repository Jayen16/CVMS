<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="{{ asset('images/child_vacc_icon.png') }}" type="image/png">
<link rel="apple-touch-icon" href="{{ asset('images/child_vacc_icon.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@if ($includeAppearance ?? true)
    @fluxAppearance
    <script>
        if (!window.localStorage.getItem('flux.appearance')) {
            window.Flux.applyAppearance('light')
        }
    </script>
@endif
