# Inventory migration plan — move inventory to `variant_sizes`

Goal: Ensure inventory is tracked per-size (`variant_sizes`) instead of only per `product_variants` so orders decrement the correct per-size stock.

Summary of steps

1. Schema changes
   - Add nullable `variant_size_id` to `inventories` and keep `product_variant_id` for compatibility during rollout.
   - Add unique constraint on (`variant_size_id`) if you want one inventory row per variant-size.

2. Backfill script
   - For each `variant_size` row, create or update an `inventories` row referencing that `variant_size_id`.
   - Logic: Prefer existing inventory rows that match `product_variant_id` and SKU; otherwise create new inventory rows with `quantity = variant_size.quantity`.
   - Mark migrated rows with a `migrated_to_variant_size_at` timestamp (optional) or create an audit table.

3. Application code updates
   - Update `Inventory` model: add `variantSize()` relation and optional `productVariant()` relation.
   - Update services that modify inventory (checkout, order placement, purchase receiving, admin inventory adjustments) to:
     - Prefer reducing/increasing inventory by `variant_size_id` when provided.
     - Fall back to `product_variant_id` only when `variant_size_id` is null.

4. Tests
   - Add/modify unit and feature tests for cart/order flows asserting per-size decrements.

5. Migration rollout strategy
   - Deploy code that supports both `variant_size_id` and `product_variant_id` (backwards compatible).
   - Run backfill to populate `variant_size_id` in `inventories`.
   - After validation, add DB constraint to make `variant_size_id` non-nullable (if desired) and remove references to `product_variant_id` in code and DB.

6. Example migration skeleton (Laravel)

```php
public function up()
{
    Schema::table('inventories', function (Blueprint $table) {
        $table->unsignedBigInteger('variant_size_id')->nullable()->after('product_variant_id');
        $table->foreign('variant_size_id')->references('id')->on('variant_sizes')->onDelete('cascade');
    });
}
```

7. Backfill pseudocode

```php
foreach (VariantSize::with('productVariant')->cursor() as $vs) {
    // Try to find existing inventory by product_variant_id and sku
    $inv = Inventory::firstOrNew([
        'product_variant_id' => $vs->product_variant_id,
        'sku' => $vs->sku,
    ]);
    $inv->variant_size_id = $vs->id;
    $inv->quantity = $vs->quantity ?? $inv->quantity ?? 0;
    $inv->save();
}
```

8. Post-migration checks
   - Run reports comparing sum(inventories.quantity) vs sum(variant_sizes.quantity) per product.
   - Spot-check orders placed during migration window.

Notes and risks
- Orders placed between backfill and final cut-over may use either path; keep both code paths active until fully validated.
- Decide whether `inventories` remains the canonical source of quantity or whether `variant_sizes.quantity` becomes canonical. Choose one and ensure downstream reports and logic are updated accordingly.

If you want, I can:
- Implement the Laravel migration and backfill script automatically, run it locally (if you allow), and add tests. 
- Or generate the migration file and a safe backfill script that prints what it would change (dry-run), then apply on confirmation.
