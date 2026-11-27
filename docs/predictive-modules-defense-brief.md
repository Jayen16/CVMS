# Defense Brief: Vaccine Demand Forecasting and Missed-Dose Risk

**Purpose:** Explain the two CVMS predictive analytics modules to a research panel or health-program client. The examples below are illustrative and are not actual patient or facility data.

## Important description of the current system

The application calls these features predictive analytics, but the current implementation is **not a trained AI or machine-learning model**. It uses explicit arithmetic rules, thresholds, and historical counts. It does not fit a model to labeled training data, estimate model parameters, or report validation metrics such as sensitivity, calibration, or forecast error. For academic accuracy, describe it as a **rule-based, data-informed decision-support prototype** unless the implementation is changed and evaluated as a trained model.

Both outputs are advisory. They can help staff prioritize review and planning; they do not decide clinical eligibility, replace the official schedule, or authorize procurement.

---

## 1. AI Vaccine Demand Forecasting

### What it is for

It estimates the number of doses of each vaccine that may be needed within a selected horizon (1–12 months in the application; the screen defaults to 3 months). It combines registered children’s scheduled doses and overdue backlog with recorded administration volume. It then compares the estimate with the recorded stock balance.

### Inputs used by the implementation

- Non-rejected vaccination records in the selected service area, including records pending verification.
- Administrations during the past 12 months, the most recent 3 months, and the preceding 3 months.
- Missing routine doses scheduled from today through the forecast end date.
- Missing doses whose schedule due date is before today (the catch-up backlog).
- Inventory transactions: stock-in minus stock-out, summed across the selected barangays.
- The child’s configured schedule version and the selected forecast horizon.

### Formula used

Let:

- `H` = forecast horizon in months (bounded by the application to 1–12)
- `D` = missing scheduled doses due from today through the horizon end
- `B` = missing doses already overdue (backlog)
- `R3` = administrations in the most recent 3 months
- `P3` = administrations in the 3 months before that
- `R12` = administrations in the past 12 months
- `S` = current recorded stock balance

The code calculates:

```text
recent monthly average     = R3 / 3
12-month monthly average   = R12 / 12
historical baseline        = ceil(max(recent monthly average,
                                      12-month monthly average) × H)
positive trend adjustment  = max(0, ceil((R3/3 − P3/3) × H))
scheduled requirement      = D + B
estimated demand           = max(scheduled requirement,
                                 historical baseline)
                             + positive trend adjustment
projected balance          = S − estimated demand
```

The program rounds the displayed averages to one decimal before applying the trend calculation. The final historical baseline and trend adjustment are rounded upward to whole doses. A negative projected balance is displayed as a possible shortage.

### Worked example (illustrative)

Assume a 3-month horizon for one vaccine:

| Input | Example value |
|---|---:|
| Scheduled doses due (`D`) | 24 |
| Overdue catch-up doses (`B`) | 6 |
| Past 3 months (`R3`) | 30 doses |
| Prior 3 months (`P3`) | 21 doses |
| Past 12 months (`R12`) | 96 doses |
| Available stock (`S`) | 35 doses |

Calculation:

```text
recent average     = 30 / 3 = 10 doses/month
12-month average   = 96 / 12 = 8 doses/month
historical baseline= ceil(max(10, 8) × 3) = 30 doses
trend adjustment  = ceil((10 − 7) × 3) = 9 doses
scheduled need    = 24 + 6 = 30 doses
estimated demand  = max(30, 30) + 9 = 39 doses
projected balance = 35 − 39 = −4 doses (possible shortage)
```

Interpretation: the dashboard signals that the area may need to review supply for a four-dose gap. It is not an order quantity.

### What is standard, and what is an assumption?

- **Aligned with established planning practice:** using target/scheduled population needs, historical use, and stock on hand as inputs is consistent with vaccine logistics guidance. Philippine DOH logistics material says requirements should be reviewed against utilization, wastage, stock, buffer stock, and storage capacity before ordering.
- **Project-specific assumptions:** taking the *maximum* of scheduled/backlog need and a historical baseline; using the highest of the recent and 12-month monthly averages; adding only a positive linear trend; and selecting a 1–12 month horizon are rules chosen for this application. They are not a published WHO or DOH formula in this exact form.
- **Not yet represented in the displayed estimate:** wastage/open-vial policy, doses per vial and presentation, buffer stock, expiry, supply lead time, campaigns, planned sessions, stock by batch/expiry, and changes in target population or coverage. Because of these omissions, the result should be called a rough planning estimate, not an official requisition calculation.
- **Data caveat:** pending vaccination records are counted unless rejected, which can affect both utilization and whether a scheduled dose is considered recorded. A pending record should therefore be reviewed before acting on a forecast.

### Reference alignment

The configured routine schedule is labeled `PIDSP 2026 Childhood Immunization Schedule`; that schedule supplies the age/dose timing used to identify scheduled and overdue doses. It is a clinical schedule basis, not a validation of the demand formula. WHO and DOH guidance supports forecasting and inventory review generally, but does not endorse this application’s exact hybrid equation.

---

## 2. AI Missed-Dose Risk

### What it is for

For a child with a pending next routine dose, the module generates a **follow-up priority score shown as a percentage**. It uses prior dose outcomes, the local population baseline, current schedule status, guardian contact availability, and pending vaccination submissions.

The code’s operational definition of a prior missed or delayed opportunity is: the dose due date has passed, and either there is no non-rejected matching vaccination record, or the recorded administration was more than the configured overdue threshold after the due date. The default threshold is 7 days. That seven-day cutoff is a **project setting**, not a universal medical definition of a missed dose.

### Formula used

Let:

- `n` = this child’s prior eligible dose opportunities
- `m` = this child’s prior opportunities classified missed or delayed
- `p` = local population baseline percentage
- `h = min(1, n/3)` = weight assigned to the child’s own history
- `a` = adjustment for no guardian contact (10 points, if applicable)
- `v` = adjustment for pending vaccination verification (5 points, if applicable)
- `s` = current schedule-status adjustment: overdue `min(25, 10 + days overdue)`, delayed `15`, due `8`, otherwise `0`

The baseline is calculated across the scoped children’s past dose opportunities. If there are fewer than 10 total opportunities, the system substitutes a conservative **10% baseline**:

```text
p = (total missed/delayed opportunities / total opportunities) × 100, if total opportunities ≥ 10
p = 10%, otherwise

child prior rate = (m / n) × 100, if n > 0; otherwise p
blended rate    = (child prior rate × h) + (p × (1 − h))
raw score       = blended rate + a + v + s
displayed score = round(raw score), bounded from 1% to 95%
```

Risk labels are set by fixed cutoffs: **low <30%**, **medium 30–59%**, and **high ≥60%**. These labels describe the application’s follow-up bands; they are not clinically validated probability categories.

### Worked example (illustrative)

Suppose the scoped population has 20 missed/delayed outcomes among 100 past opportunities, so the baseline is 20%. A child has two previous opportunities, one classified missed/delayed, no available guardian phone/email, a pending vaccination submission, and a currently delayed next dose.

```text
child prior rate = 1 / 2 × 100 = 50%
history weight   = min(1, 2 / 3) = 0.667
blended rate     = (50 × 0.667) + (20 × 0.333)
                 = 33.35 + 6.66 = 40.01%
adjustments      = 10 (no contact) + 5 (pending) + 15 (delayed status)
raw score        = 40.01 + 30 = 70.01%
displayed score  = 70% → High follow-up risk
```

Interpretation: staff could prioritize checking the record, confirming the caregiver’s contact details, and offering follow-up. The score does not establish that the child will miss a dose or that a particular intervention is clinically indicated.

### What is standard, and what is an assumption?

- **Aligned with immunization practice:** identifying children who have missed or delayed doses and arranging catch-up is consistent with WHO guidance. Using a correct national/local schedule and reliable dose records is essential.
- **Project-specific assumptions:** the 7-day missed/delayed cutoff; 10% fallback baseline; minimum of 10 population opportunities before using the observed baseline; blending history with a `n/3` weight; +10/+5/status adjustments; 95% cap; and 30%/60% category thresholds. These choices are not a WHO/PIDSP/DOH validated risk score.
- **Important terminology:** WHO’s “missed opportunity for vaccination” has a specific meaning: an eligible person contacts health services but does not receive a due vaccine despite having no contraindication. This module does not observe that event. It estimates missed/late dose follow-up risk from schedule and record history. In a thesis, call it “predicted risk of a missed or delayed next dose” or “follow-up prioritization score,” not a WHO missed-opportunity measure.
- **Not a clinical decision:** the score does not check contraindications or all catch-up/special-risk rules. Staff must consult the applicable schedule and clinical guidance before vaccination.

### Reference alignment

PIDSP/PPS/PFV schedule guidance informs routine dose timing. WHO catch-up guidance supports identifying and reaching people who missed doses. Neither source defines the module’s numeric score, feature weights, probability interpretation, or risk bands. Those are design assumptions that need local validation before claiming calibrated probabilities or clinical performance.

---

## Defense-ready summary

> “The system provides two rule-based decision-support estimates. Vaccine demand combines the higher of a schedule/backlog requirement and a historical-use baseline, then adds a positive recent-use trend and compares the estimate with recorded stock. Missed-dose risk blends the child’s prior late/missed-dose rate with a local baseline, then adds follow-up signals such as overdue status and missing contact information. The schedule and the general practices of catch-up and stock planning are aligned with immunization guidance. The exact equations, weights, thresholds, and risk bands are project assumptions, so we present outputs as advisory and require validation with local data before treating them as accurate forecasts or probabilities.”

## References

1. Pediatric Infectious Disease Society of the Philippines (PIDSP), [Childhood Immunization Schedule 2026](https://www.pidsphil.org/home/wp-content/uploads/2025/11/2026-PIDSP-Immunization-Calendar.pdf). Consult the current revised July 2026 calendar/correction listed on the [PIDSP Guidelines & Policies page](https://www.pidsphil.org/home/guidelines-policies/) when discussing the project’s configured schedule.
2. WHO, [Leave no one behind: guidance for planning and implementing catch-up vaccination](https://www.who.int/publications-detail-redirect/9789240016514), 2021.
3. WHO, [Reducing missed opportunities for vaccination](https://www.who.int/teams/immunization-vaccines-and-biologicals/essential-programme-on-immunization/implementation/reducing-missed-opportunities-for-vaccination), definition and strategy for missed opportunities.
4. Philippines Department of Health, [Vaccines, Cold Chain and Logistics Management](https://doh.gov.ph/wp-content/uploads/2023/08/Booklet-10-VACCINES-COLD-CHAIN-AND-LOGISTICS-MANAGEMENT.pdf), including vaccine requirement calculations and review against utilization, wastage, stock, buffer stock, and storage capacity.
5. CVMS implementation basis: `docs/predictive-analytics-basis.md`, `config/immunization.php`, and `app/Services/PredictiveAnalyticsService.php`.

*This brief explains software behavior and research framing; it is not clinical guidance. Confirm current schedule and catch-up decisions with the responsible Philippine health authority/qualified clinician.*
