# Backlog

Deferred work that is intentionally out of the current release scope.

## Refund-aware order balance

- **Status:** Deferred
- **Reason:** Avoid changing `OrderResource.balance` and refund-related behaviour in the current release.

`Order::getBalanceAttribute()` returns `grand_total - amount_paid` and ignores
refunds, so an order with a completed refund shows an inflated outstanding
balance. The intended net is:

```
grand_total - (amount_paid - refunded)
```

using the existing rule in `RefundAmountService::alreadyRefundedAmount()`
(statuses `refunded` and `refund_processing`).

### Acceptance criteria

- Tests covering full, partial, in-processing, and completed refunds.
- `amount_paid` remains gross — `RefundAmountService::forWholeOrder()` uses it
  as the refund ceiling, so it must not be netted.
- Verify the balance exposed through `OrderResource` reflects the intended
  refund semantics.