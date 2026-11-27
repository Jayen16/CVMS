# Important Scope Note: Population Data and Prediction Coverage

## The key point to remember

> **The current vaccine-demand forecast does not represent every child in the barangay. It represents the registered children and recorded vaccination activity available in CVMS for the selected area.**

If someone says the system “forecasts demand for the entire community,” clarify what that means in this implementation: **it estimates demand from the children currently registered in CVMS, plus their recorded vaccination history and the system’s schedule/backlog calculations.** It does not currently use the admin census/population-background feature to estimate unregistered children.

## Is census population required?

It depends on the intended scope:

- **Forecasting for registered CVMS children:** census data is not required for the current calculation. The forecast uses their schedules, prior recorded doses, recent vaccine use, and recorded stock.
- **Estimating need for all eligible children in a barangay:** census-derived population data (or another defensible estimate of the target population) is important because the registry may not include every eligible child. Census totals alone are not enough; the estimate also needs the relevant age groups, schedule and dose requirements, coverage assumptions, and supply-planning factors.

The admin population-background feature currently provides population context for reports and dashboards. It is **not an input** to the present vaccine-demand or missed-dose-risk formulas.

## What the two outputs currently cover

### Vaccine-demand forecast

It uses registered children visible in the selected area to count missing doses due within the forecast window and overdue doses. It also uses recorded non-rejected vaccination administrations from the area, recent and 12-month usage, and stock transactions. Consequently, children who are not registered in CVMS are not included in the schedule-based counts. The demand estimate can understate total community need when registry coverage is incomplete.

### Missed-dose risk

This score is calculated for an individual registered child with a pending next routine dose. Its population baseline is derived from past dose opportunities among children in the selected CVMS data scope (or a 10% fallback when there are fewer than ten opportunities). It does not use census population counts and does not estimate risk for unregistered children.

## Defense-ready wording

> “In the current implementation, ‘community’ refers to the selected service area’s CVMS records. The demand forecast is based on registered children and recorded vaccine activity; it is not a complete estimate for all children living in the barangay because census-based population data is not currently an input. The admin census feature is used for population context in reporting. To forecast total eligible community need, we would need to incorporate a defensible age-specific target-population estimate and document coverage and supply assumptions.”

## Important distinction

The current calculation is what the application does; it is not, by itself, proof that it meets a standard forecasting methodology. Official guidance supports considering target population, historical use, and stock during vaccine planning. The specific CVMS equation and its assumptions should be presented as project design choices unless separately validated.

## Basis checked

- Application implementation: `app/Services/PredictiveAnalyticsService.php`
- Population-background feature and reporting use: `app/Models/PopulationBackground.php` and the relevant reports/dashboard code
- Application’s explanation of the predictive calculations: `docs/predictive-analytics-basis.md`
- Philippines Department of Health, [Vaccines, Cold Chain and Logistics Management](https://doh.gov.ph/wp-content/uploads/2023/08/Booklet-10-VACCINES-COLD-CHAIN-AND-LOGISTICS-MANAGEMENT.pdf)

*This note describes application behavior and research scope. It is not clinical guidance.*
