# Predictive Analytics Basis

This document defines the historical-data basis for the two predictive outputs in the Child Vaccination Management System. The outputs are advisory and support staff follow-up and planning. They do not independently decide whether a child should be vaccinated or whether stock should be procured.

## 1. Missed-dose risk forecasting

### Prediction target

For each child’s next scheduled dose, estimate the likelihood that the dose will be missed or delayed by more than seven days.

### Historical data used

- Previous scheduled dose opportunities for the child
- Whether each previous dose was administered
- Whether each administered dose was more than seven days after its guideline due date
- The child’s current vaccine series and next due date
- Pending vaccination submissions
- Availability of a guardian phone number or email address
- A conservative population baseline when the child has too little history

### Historical features

The system derives:

- Prior dose opportunities
- Prior missed or delayed doses
- The child’s observed missed/delayed rate
- A population missed/delayed baseline
- Current schedule status: upcoming, due, delayed, or overdue
- Whether a guardian contact method is available
- Whether a vaccination submission is awaiting verification

### Basis of the estimate

The child’s historical missed/delayed rate is blended with the population baseline. Child history receives more weight as the number of previous dose opportunities increases. Contact, pending-record, and current follow-up signals then adjust the estimate. The output is capped at 95% and reported as a low, medium, or high follow-up risk.

The model does not use the future vaccination outcome as an input. A child with fewer than three prior opportunities receives more weight from the population baseline, and the system uses a conservative 10% baseline until at least ten population observations are available.

## 2. Vaccine-demand forecasting

### Prediction target

Estimate the number of doses of each vaccine that may be needed during the next three months.

### Historical and operational data used

- Vaccination administrations during the previous twelve months
- Recent three-month vaccination volume
- Previous three-month vaccination volume for trend comparison
- Missing scheduled doses due in the forecast period
- Delayed and overdue doses requiring catch-up
- Registered children and their applicable vaccine schedules
- The child’s assigned schedule version

### Basis of the estimate

For each vaccine, the system calculates:

1. Scheduled doses due during the next three months.
2. The delayed/overdue catch-up backlog.
3. The twelve-month historical monthly average.
4. The recent three-month monthly average.
5. A positive trend adjustment when recent activity is higher than the preceding three-month period.

The estimate is calculated as:

```text
historical baseline = higher of recent or twelve-month monthly average × forecast months
scheduled requirement = scheduled doses + catch-up backlog
estimated demand = higher of historical baseline or scheduled requirement + positive trend adjustment
```

This combines actual local consumption with the registered population’s schedule obligations. It is intended to provide a planning signal, not an automatic purchase quantity.

## Interpretation

These outputs are historical-data hybrid estimates. Their reliability depends on complete vaccination records, correct birthdates and schedules, consistent status recording, and sufficient local history. Staff should review the underlying records and inventory context before acting on either output.
