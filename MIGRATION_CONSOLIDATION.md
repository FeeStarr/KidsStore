# Migration Consolidation Notes

## Status: IN PROGRESS

### Conflict Identified

**Conflicting Migrations:**
1. `2026_06_14_000001_add_size_and_age_group_to_product_variants.php` — Adds free-text columns
2. `2026_06_15_000001_drop_variant_sub_tables_and_free_text_columns.php` — Immediately removes them

### Why This Is a Problem

- The June 14 migration adds `size` and `age_group` columns to `product_variants`
- The June 15 migration drops those exact columns (plus old sub-tables)
- This creates unnecessary schema churn
- Any fresh migrations run the add+drop sequence, causing unnecessary table modifications

### Final Schema Decision (CONFIRMED)

The correct schema design is represented in **June 15 migration**:

```
ProductVariant structure:
  - FK: product_id → products(id)
  - FK: color_id → colors(id) [represents color]
  - FK: size_id → sizes(id) [represents size]
  - FK: age_range_id → age_ranges(id) [represents age group]
  - sku (unique identifier)
  - selling_price, discount, etc.

EACH ProductVariant row = ONE specific combination (color + size + age)
Stock tracked via: Inventory → product_variant_id
```

### What To Do

**Option 1: Delete the conflicting migration (RECOMMENDED)**
```bash
# Remove the conflicting June 14 migration
rm database/migrations/2026_06_14_000001_add_size_and_age_group_to_product_variants.php
```

Why: The June 15 migration achieves the correct final state without the extra adds.

**Option 2: Merge migrations**
- Delete June 14 migration
- Update June 15 migration comments to reference "consolidation"
- Same result, cleaner history

### Tests Updated ✅

Both feature tests in `tests/Feature/AdminProductVariantsTest.php` have been updated to:
- Create Color, Size, AgeRange records (FKs)
- Create flat ProductVariant rows with proper FK references
- Verify inventory tracking per variant

### Verification Steps

```bash
# After deleting conflicting migration:

# 1. Run fresh migrations
php artisan migrate:fresh

# 2. Verify schema matches June 15 final state
php artisan tinker
>>> Schema::getColumns('product_variants')

# 3. Run tests
php artisan test tests/Feature/AdminProductVariantsTest.php

# 4. Should see: 2 tests, 0 failures
```

### Migration Timeline (After Consolidation)

```
2026_06_05_000001 → Create product_variants (initial flat design)
2026_06_10_000000 → Add variant_sizes table (old nested approach)
2026_06_11_000001 → Add variant_size_id to inventories
2026_06_15_000001 → Drop nested approach, consolidate to final flat design ✓
  ↑ This is now the latest schema version
```

### Next Steps

1. [ ] Delete `2026_06_14_000001_add_size_and_age_group_to_product_variants.php`
2. [ ] Run `php artisan migrate:fresh` to verify schema
3. [ ] Run test suite: `php artisan test`
4. [ ] Commit clean migration history

---

**Last Updated:** June 16, 2026
