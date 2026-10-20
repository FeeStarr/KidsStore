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

## Size chart measurement rules: unused parameter and misleading comment

- **Status:** Deferred
- **Reason:** Changing measurement uniqueness rules needs the database index and
  every current caller checked first; it is unrelated to the sizing confirmation
  work and was deliberately kept out of it.

`SizeChartController::measurementRules()` takes an `$ignore` parameter that is
never used, and its docblock describes a uniqueness rule the method does not
actually apply:

```php
private function measurementRules(SizeChart $chart, SizeChartEntry $entry, ?SizeChartMeasurement $ignore = null): array
```

The only rule in force is `Rule::exists('custom_order_measurement_fields', 'name')`.
Uniqueness of `(measurement_type, basis)` is enforced entirely by the database
index `size_chart_measurements_entry_type_basis_unique`.

Either the comment should describe what the code does, or the rule should be
applied and the index checked to agree with it.

### Acceptance criteria

- The method signature, the docblock and the applied rules agree with each other.
- The database index and the application-level rule enforce the same thing, so a
  validation failure and a constraint violation cannot disagree.
- No behaviour change for measurements that are currently accepted.
