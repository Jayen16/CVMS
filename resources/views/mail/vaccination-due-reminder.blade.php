<p>Hello,</p>

<p>
    This is a reminder that <strong>{{ $child->full_name }}</strong> is due for
    <strong>{{ $vaccineName }}</strong>@if ($doseNumber) dose {{ $doseNumber }}@endif
    on <strong>{{ $dueAt->format('M d, Y') }}</strong>.
</p>

<p>
    Please visit your barangay clinic or another available clinic and bring the child's vaccination record.
    If the vaccine is given outside the barangay clinic, submit the vaccination history so the nurse can verify it.
</p>

<p>Thank you.</p>
