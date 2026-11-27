@props(['balance'])

@if ($balance < 0)
    Projected shortage: {{ number_format(abs($balance)) }} {{ abs($balance) == 1 ? 'dose' : 'doses' }}
@elseif ($balance > 0)
    Above estimated demand by {{ number_format($balance) }} {{ $balance == 1 ? 'dose' : 'doses' }}
@else
    Meets estimated demand exactly
@endif
