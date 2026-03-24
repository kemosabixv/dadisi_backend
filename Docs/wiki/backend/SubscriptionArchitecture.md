# Subscription & Membership Architecture

This document describes the technical implementation of the Dadisi Community Labs subscription engine, recurring billing system, and member entitlement logic.

## 1. Core Technology Stack

The subscription system is built upon three primary pillars:

*   **[`laravelcm/laravel-subscriptions`](https://github.com/laravelcm/laravel-subscriptions)**: The foundational engine for defining plans, handling subscriptions, and managing usage-based features (quotas).
*   **Pesapal V3 Gateway**: The payment orchestrator for both one-time checkouts and automated recurring billing (subscriptions).
*   **Subscription Enhancement System**: A local Dadisi extension that manages NGO-specific logic like grace periods, gateway account references, and automated downgrades.

## 2. Domain Model Architecture

### Plan (`App\Models\Plan`)
Extends the vendor `Plan` model. Key additions include:
*   **`requires_student_approval`**: A flag that gates certain plans (e.g., Student Tier) behind a manual verification process.
*   **`systemFeatures`**: Link to System Entitlements (e.g., `lab_hours_monthly`, `media_storage_mb`).
*   **Effective Pricing**: Methods like `getEffectiveMonthlyPrice()` and `getEffectiveYearlyPrice()` calculate the final cost including active promotions.

### PlanSubscription (`App\Models\PlanSubscription`)
Extends the vendor `Subscription` model. It serves as the bridge between the user and their active entitlements.
*   **`enhancements`**: One-to-one relationship with `SubscriptionEnhancement`.
*   **`payments`**: Polymorphic relationship to track all transactions related to the subscription lifecycle.

### SubscriptionEnhancement (`App\Models\SubscriptionEnhancement`)
Maintains the extended state needed for recurring billing and compliance:
*   **`pesapal_account_reference`**: The unique ID provided by Pesapal to trigger automated recurring charges.
*   **`grace_period_expires_at`**: Manages the 14-day NGO grace period after a subscription technically expires but before access is revoked.
*   **`last_pesapal_recurring_at`**: Tracks the last time Pesapal successfully processed an automated renewal.

---

## 3. Subscription Lifecycle & Recurring Billing

### Initiation (Checkout)
1. User selects a plan and billing interval (Monthly/Yearly).
2. **Promotion Rule**: If the plan has an active promotion (discount), the system enforces **Manual Monthly** billing only. Yearly or automated recurring billing is blocked to avoid indefinite price lock-in at the gateway level.
3. `SubscriptionCoreService::initiatePayment()` creates a pending `PlanSubscription` and `Payment`.
4. If recurring is selected (non-promotional), `PesapalGateway` includes `subscription_details` in the order metadata.

### Automated Renewal (Recurring)
1. Pesapal triggers the charge on the scheduled date.
2. Pesapal sends a webhook (IPN) with `OrderNotificationType: RECURRING`.
3. `PaymentService::handleWebhook` identifies the transaction and calls `verifyRecurringPayment()`.
4. **Subscription Extension**: The system calculates the new `ends_at` (based on the original plan interval), creates a new `Payment` record, and updates the `PlanSubscription`.

### Grace Period & Downgrades
The `ProcessExpiredSubscriptionsJob` runs daily and handles two phases:
*   **Phase 1 (Entrance)**: When `ends_at` is passed, the system sets `grace_period_expires_at` (14 days from now). The status remains "active" so the member doesn't lose immediate access.
*   **Phase 2 (Exit)**: If `grace_period_expires_at` is passed without a successful renewal, the system:
    1. Marks the enhancement as "expired".
    2. **Automatically downgrades** the user to the default "Free" tier (based on `sort_order`).

---

## 4. Promotion & Trial Logic

### Removal of Trials
Native "Trials" (configured via `trial_period`) have been **deprecated** and removed from the schema.
*   **Alternative**: To offer a trial, create a **100% Discount Promotion** for the first month. This ensures the user enters the standard subscription flow while still receiving the initial period for free.

### Promotion Rules
*   Promotions are applied at the time of checkout.
*   Any promotional discount **locks the subscription to a single month** with manual renewal required. This prevents the system from indefinitely charging a discounted rate that may have been intended as a one-time incentive.

---

## 5. Quotas & Entitlements

The `QuotaService` manages usage-based limits defined in `SystemFeatures`:
*   **Replenishment**: Quotas (like `lab_hours_monthly`) are replenished upon successful renewal.
*   **Grace Period Restriction**: While in the 14-day grace period, users retain access to existing data but **cannot** consume new quota (e.g., they can view media but not upload new files) until the invoice is paid.

---

## 6. Notifications & Reminders

*   **Renewal Reminders**: `SendSubscriptionRemindersJob` sends alerts 7 days before expiry (controlled by the `RenewalPreference` model).
*   **Payment Failures**: Custom notifications are triggered if a recurring charge fails, prompting the user to update their payment method before the grace period ends.

## 7. Administrative Controls

*   **Plan Configuration**: Managed via `adminApi.plans`. Allows setting base prices, intervals, and associated system features.
*   **Audit Logging**: Every transition (activation, cancellation, downgrade, renewal) is logged in the `AuditLog` table for compliance reporting.
