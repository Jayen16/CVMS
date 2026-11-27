# Predictive Modules: Formula Names and Step-by-Step Computation

This guide explains both the **Vaccine Demand Forecast** and **Missed-Dose Risk** calculations currently implemented in CVMS. The worked examples follow the order of operations and rounding behavior in `app/Services/PredictiveAnalyticsService.php`.

## What the calculation estimates

For each vaccine, CVMS estimates doses that may be needed during a selected forecast period. It combines:

1. Doses due for registered children during the forecast period.
2. Those registered childrenâ€™s overdue missing doses (catch-up backlog).
3. Historical vaccination activity.
4. A positive adjustment when recent monthly use is higher than the previous three-month period.
5. Recorded stock balance, for comparison with estimated demand.

**Scope reminder:** the schedule and backlog counts come from children registered in CVMS in the selected area. The forecast does not currently use the admin census/population-background feature to estimate doses for unregistered children. It should not be described as a complete estimate for every eligible child in the barangay unless that scope is addressed separately.

## Variables and notation

| Symbol | Meaning |
|---|---|
| (H) | Forecast horizon, in months (CVMS accepts 1â€“12 months) |
| (R_3) | Number of vaccine administrations in the most recent 3 months |
| (P_3) | Number of vaccine administrations in the preceding 3 months |
| (R_{12}) | Number of vaccine administrations in the past 12 months |
| (D) | Missing doses due from today through the forecast end date |
| (B) | Missing doses with due dates before today (catch-up backlog) |
| (S) | Recorded stock balance: total stock-in minus total stock-out |
| (lceil x ceil) | Ceiling function: round (x) up to the next whole number |
| (max(x,y)) | Maximum function: select the larger of (x) and (y) |
| (operatorname{round}(x,1)) | Round to one decimal place |

## Formula names and equations

### 1. Arithmetic mean (monthly average)

The system converts historical administration counts into monthly averages:

\[
\text{Recent monthly average}=\operatorname{round}(R_3/3,1)
\]

\[
\text{12-month monthly average}=\operatorname{round}(R_{12}/12,1)
\]

\[
\text{Prior monthly average}=\operatorname{round}(P_3/3,1)
\]

The prior monthly average is used for the trend comparison. The first two averages are compared to calculate the historical baseline.

### 2. Maximum function

The system selects the larger of the recent and 12-month monthly averages, then scales it to the forecast horizon and rounds up to a whole dose:

\[
\text{Historical baseline}
=\left\lceil\max(\text{Recent monthly average},\text{12-month monthly average})\times H\right\rceil
\]

This is a project calculation rule. It uses the higher of the two averages; it is not a named standard forecasting model.

### 3. Positive linear trend adjustment

The recent monthly average is compared with the preceding periodâ€™s monthly average. Only a positive increase is added:

\[
\text{Trend adjustment}
=\max\left(0,\left\lceil(\text{Recent monthly average}-\text{Prior monthly average})\times H\right\rceil\right)
\]

If recent use is equal to or lower than prior use, the adjustment is zero. The code rounds the averages to one decimal before this step, multiplies the difference by the horizon, and rounds the result upward.

### 4. Addition and maximum function for estimated demand

First, add the scheduled doses and overdue backlog:

\[
\text{Scheduled requirement}=D+B
\]

Then compare the scheduled requirement with the historical baseline and add the positive trend adjustment:

\[
\text{Estimated demand}
=\max(\text{Scheduled requirement},\text{Historical baseline})+\text{Trend adjustment}
\]

### 5. Inventory balance (subtraction)

The recorded stock balance is compared with estimated demand:

\[
\text{Projected balance}=S-\text{Estimated demand}
\]

- If the result is positive, recorded stock exceeds the estimate.
- If the result is zero, recorded stock equals the estimate.
- If the result is negative, the magnitude is a possible supply gap for staff review.

## Complete worked example

The following numbers are **illustrative only**. They are not actual client or facility records.

Assume a three-month forecast for one vaccine:

| Input | Value |
|---|---:|
| Forecast horizon (H) | 3 months |
| Missing doses due within the forecast period (D) | 24 doses |
| Overdue missing doses (B) | 6 doses |
| Administrations in recent 3 months (R_3) | 30 doses |
| Administrations in preceding 3 months (P_3) | 21 doses |
| Administrations in past 12 months (R_{12}) | 96 doses |
| Recorded stock balance (S) | 35 doses |

### Step 1: Calculate the arithmetic means

Recent three-month monthly average:

\[
R_3/3=30/3=10.0\text{ doses per month}
\]

Prior three-month monthly average:

\[
P_3/3=21/3=7.0\text{ doses per month}
\]

Twelve-month monthly average:

\[
R_{12}/12=96/12=8.0\text{ doses per month}
\]

### Step 2: Calculate the historical baseline using the maximum function

Compare the recent and 12-month averages:

\[
\max(10.0,8.0)=10.0
\]

Multiply by the three-month forecast horizon and round up:

\[
\text{Historical baseline}=\lceil10.0\times3\rceil=30\text{ doses}
\]

### Step 3: Calculate the positive linear trend adjustment

Find the increase in monthly use:

\[
10.0-7.0=3.0\text{ doses per month}
\]

Scale the difference to the forecast horizon, round up, and apply the zero floor:

\[
\text{Trend adjustment}=\max(0,\lceil3.0\times3\rceil)=9\text{ doses}
\]

### Step 4: Calculate scheduled requirement and estimated demand

Add forecast-period scheduled doses to overdue backlog:

\[
\text{Scheduled requirement}=D+B=24+6=30\text{ doses}
\]

Compare that requirement with the historical baseline, then add the trend adjustment:

\[
\begin{aligned}
\text{Estimated demand}
&=\max(30,30)+9\
&=30+9\
&=39\text{ doses}
\end{aligned}
\]

### Step 5: Calculate projected inventory balance

Subtract estimated demand from recorded stock:

\[
\text{Projected balance}=S-\text{Estimated demand}=35-39=-4\text{ doses}
\]

**Interpretation:** CVMS would show estimated demand of **39 doses** and a projected balance of **âˆ’4 doses**, signaling a possible four-dose gap for staff review. It is not an automatic procurement quantity.

## Missed-Dose Risk: formula names and step-by-step computation

### What this score estimates

For a registered child who has a pending next routine dose, CVMS produces a follow-up risk score. It blends the childâ€™s past dose outcomes with an area baseline, then adds fixed points for selected follow-up signals. The score helps prioritize review; it is not a medically validated or calibrated probability that the child will miss the next dose.

The code considers prior dose opportunities whose due dates are before today. A prior opportunity is counted as missed or delayed if no non-rejected vaccination record matches that vaccine and dose, or if the recorded administration date is later than the due date plus the configured threshold (7 days by default). The 7-day threshold is an application setting.

### Variables and notation

| Symbol | Meaning |
|---|---|
| \(n\) | This childâ€™s prior dose opportunities |
| \(m\) | This childâ€™s prior opportunities classified missed or delayed |
| \(N\) | Prior dose opportunities across children in the selected area |
| \(M\) | Missed or delayed opportunities across those children |
| \(p\) | Area baseline rate, as a percentage |
| \(h\) | Weight assigned to this childâ€™s history: \(\min(1,n/3)\) |
| \(a\) | Contact adjustment: 10 points if guardian contact is unavailable; otherwise 0 |
| \(v\) | Pending-record adjustment: 5 points if a vaccination submission is pending; otherwise 0 |
| \(s\) | Current next-dose schedule-status adjustment |

### Formula names and equations

#### 1. Proportion (individual and area observed rates)

If the child has prior opportunities, the childâ€™s observed rate is:

\[
\text{Child prior rate}=(m/n)\times100
\]

The area baseline is calculated from area opportunities when at least 10 exist:

\[
p=(M/N)\times100\quad\text{when }N\ge10
\]

When the area has fewer than 10 prior opportunities, the code uses a fixed fallback baseline:

\[
p=10\%\quad\text{when }N<10
\]

If the child has no prior opportunity \(n=0\), the child prior rate is set to \(p\).

#### 2. History weight (minimum function)

\[
h=\min(1,n/3)
\]

The childâ€™s history weight rises with the number of prior opportunities and reaches 1 when there are at least three.

#### 3. Weighted average (blended rate)

\[
\text{Blended rate}
=(\text{Child prior rate}\times h)+(p\times(1-h))
\]

This combines the childâ€™s observed history with the area baseline. With no individual history, the baseline supplies the rate.

#### 4. Additive rule-based adjustments

The schedule-status adjustment is:

\[
s=\begin{cases}
\min(25,10+\text{days overdue}), & \text{next dose status is overdue}\\
15, & \text{next dose status is delayed}\\
8, & \text{next dose status is due}\\
0, & \text{next dose status is upcoming}
\end{cases}
\]

Then the raw score is:

\[
\text{Raw score}=\text{Blended rate}+a+v+s
\]

#### 5. Rounding and score bounds

\[
\text{Displayed score}=\min(95,\max(1,\operatorname{round}(\text{Raw score})))
\]

The score is rounded to a whole percentage point and bounded from 1% to 95%.

#### 6. Threshold classification

- **Low:** below 30%
- **Medium:** 30% to below 60%
- **High:** 60% or above

These category boundaries are fixed project thresholds.

### Complete worked example

The following values are illustrative only. Suppose the selected area has 20 missed/delayed outcomes among 100 prior opportunities, and a child has two prior opportunities, one missed/delayed. The child has no available guardian phone/email, has a vaccination submission pending verification, and the next dose is currently delayed.

| Input | Value |
|---|---:|
| Area opportunities \(N\) | 100 |
| Area missed/delayed opportunities \(M\) | 20 |
| Child opportunities \(n\) | 2 |
| Child missed/delayed opportunities \(m\) | 1 |
| No guardian contact adjustment \(a\) | 10 points |
| Pending submission adjustment \(v\) | 5 points |
| Delayed status adjustment \(s\) | 15 points |

#### Step 1: Calculate the area baseline using a proportion

There are at least 10 area opportunities, so use the observed area proportion:

\[
p=(M/N)\times100=(20/100)\times100=20\%
\]

#### Step 2: Calculate the childâ€™s prior rate using a proportion

\[
\text{Child prior rate}=(m/n)\times100=(1/2)\times100=50\%
\]

#### Step 3: Calculate the child history weight

\[
h=\min(1,n/3)=\min(1,2/3)=2/3\approx0.667
\]

#### Step 4: Blend the child prior rate and area baseline using a weighted average

Using the exact fraction \(h=2/3\):

\[
\begin{aligned}
\text{Blended rate}
&=(50\times2/3)+(20\times1/3)\\
&=100/3+20/3\\
&=40\%
\end{aligned}
\]

#### Step 5: Add the follow-up adjustments

The child has no guardian contact method, a pending submission, and a delayed next dose:

\[
\text{Raw score}=40+10+5+15=70
\]

#### Step 6: Round, bound, and classify the score

\[
\text{Displayed score}=\min(95,\max(1,\operatorname{round}(70)))=70\%
\]

Since 70% is at least 60%, the result is **High** under the applicationâ€™s threshold rules.

**Interpretation:** staff may prioritize checking this childâ€™s record and arranging appropriate follow-up. The score does not prove the child will miss the next dose and does not make the clinical decision to vaccinate.

### Important interpretation note

Although the interface displays a percentage, it is a **rule-based weighted risk score**, not a statistically calibrated probability. The baseline fallback, `n/3` history weight, point adjustments, 95% cap, and 30%/60% cutoffs are project rules. WHO guidance supports catch-up vaccination and identifying people who missed doses; it does not define this numeric CVMS score.

## What the results do and do not mean

- The equation is the calculation used by the current CVMS implementation.
- The calculation combines averages, a project-defined trend adjustment, scheduled-dose counts, and stock subtraction.
- Its scheduled and backlog counts cover registered children in the selected CVMS area. Unregistered children are not added through census data in the current formula.
- The result is advisory. It is not, by itself, a purchase order or a complete vaccine logistics requirement.
- The current formula does not explicitly add vaccine wastage, doses-per-vial/open-vial policy, buffer stock, expiry, lead time, planned campaigns, or storage capacity.
- Actual ordering should be reviewed by responsible staff against current target population, utilization, wastage, stock, delivery timing, and applicable DOH procedures.

## How to explain the formulas to a panel

> “The system uses arithmetic means, proportions, a weighted average, a positive trend adjustment, and threshold-based scoring. The demand module combines those calculations with scheduled-dose counts and inventory subtraction. The missed-dose module calculates a weighted score from prior outcomes and follow-up signals. The exact combination and assumptions are implemented as project rules, and the outputs are advisory.”

## References and source notes

1. **Exact formula source:** CVMS application code, `app/Services/PredictiveAnalyticsService.php`. This is the source for the equations and order of calculation documented above.
2. **Schedule source:** the application is configured to use the [PIDSP 2026 Childhood Immunization Schedule](https://www.pidsphil.org/home/guidelines-policies/) for dose timing. This is the schedule basis; it is not the source of the forecasting equation.
3. **General vaccine-planning context:** Philippine DOH, [Vaccines, Cold Chain and Logistics Management](https://doh.gov.ph/wp-content/uploads/2023/08/Booklet-10-VACCINES-COLD-CHAIN-AND-LOGISTICS-MANAGEMENT.pdf), which discusses estimating requirements and reviewing them in light of utilization, wastage, existing stock, buffer stock, and storage capacity. This supports planning considerations, not the exact CVMS equation.
4. **General missed-dose follow-up context:** WHO, [Leave no one behind: guidance for planning and implementing catch-up vaccination](https://www.who.int/publications-detail-redirect/9789240016514). This supports catch-up vaccination practice, not the exact CVMS score, weights, or category cutoffs.

*This guide documents software behavior for research discussion and is not clinical or procurement guidance.*

