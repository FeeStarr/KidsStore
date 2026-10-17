<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class SizeChart extends Model
{
    public const BASIS_BODY = 'body';

    public const BASIS_FINISHED_GARMENT = 'finished_garment';

    public const BASES = [self::BASIS_BODY, self::BASIS_FINISHED_GARMENT];

    protected $fillable = [
        'name', 'default_basis', 'version', 'is_active',
        'is_published', 'published_at', 'is_locked', 'locked_by_order_id',
    ];

    protected $attributes = [
        'version' => 1,
        'is_active' => true,
        'is_published' => false,
        'is_locked' => false,
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_published' => 'boolean',
        'is_locked' => 'boolean',
        'published_at' => 'datetime',
        'version' => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(SizeChartEntry::class)->orderBy('sort_order');
    }

    public function lockedByOrder(): BelongsTo
    {
        return $this->belongsTo(CustomOrder::class, 'locked_by_order_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Only a published, active chart may be offered to customers.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->where('is_active', true);
    }

    /**
     * Problems preventing publication, as human-readable reasons.
     *
     * A chart is never publishable without entries and measurement rows. Nothing here
     * invents or interpolates figures: if the tailor has not supplied a measurement, the
     * chart simply is not ready, rather than showing a blank or guessed value.
     */
    public function publicationBlockers(): array
    {
        $blockers = [];

        if ($this->entries->isEmpty()) {
            $blockers[] = 'Add at least one size entry.';
        }

        foreach ($this->entries as $entry) {
            if ($entry->measurements->isEmpty()) {
                $blockers[] = "Size \"{$entry->label}\" has no measurements.";

                continue;
            }

            foreach ($entry->measurements as $measurement) {
                if ($measurement->min_value !== null
                    && $measurement->max_value !== null
                    && $measurement->min_value > $measurement->max_value) {
                    $blockers[] = sprintf(
                        'Size "%s" measurement "%s" has a minimum above its maximum.',
                        $entry->label,
                        $measurement->measurement_type
                    );
                }
            }
        }

        return $blockers;
    }

    public function isPublishable(): bool
    {
        return $this->publicationBlockers() === [];
    }

    /**
     * Publication is refused when the chart is incomplete, so a customer can never be
     * shown an empty or internally inconsistent chart.
     */
    public function publish(): void
    {
        $blockers = $this->publicationBlockers();

        if ($blockers !== []) {
            throw new RuntimeException(
                'Chart cannot be published: '.implode(' ', $blockers)
            );
        }

        $this->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * A chart version referenced by an order is frozen: the sizing evidence recorded at
     * approval must keep describing what the customer actually saw. Editing means
     * creating a new version.
     */
    public function lockFor(CustomOrder $order): void
    {
        if ($this->is_locked) {
            return;
        }

        $this->update([
            'is_locked' => true,
            'locked_by_order_id' => $order->id,
        ]);
    }

    public function assertNotLocked(): void
    {
        if ($this->is_locked) {
            throw new RuntimeException(
                'This chart version is locked because an order was sized against it. Create a new version to make changes.'
            );
        }
    }

    /**
     * A copy of this version that can be edited. Keeps the locked original intact.
     *
     * Copying forward is deliberately allowed even when this version is locked: that is
     * the only route to change a chart an order was sized against. The original stays
     * frozen so the recorded evidence keeps describing what the customer actually saw.
     */
    public function newVersion(): self
    {
        $copy = static::create([
            'name' => $this->name,
            'default_basis' => $this->default_basis,
            'version' => $this->version + 1,
            'is_active' => true,
            'is_published' => false,
        ]);

        foreach ($this->entries as $entry) {
            $newEntry = $copy->entries()->create([
                'label' => $entry->label,
                'code' => $entry->code,
                'sort_order' => $entry->sort_order,
            ]);

            foreach ($entry->measurements as $measurement) {
                $newEntry->measurements()->create([
                    'measurement_type' => $measurement->measurement_type,
                    'basis' => $measurement->basis,
                    'min_value' => $measurement->min_value,
                    'max_value' => $measurement->max_value,
                    'unit' => $measurement->unit,
                ]);
            }
        }

        return $copy;
    }
}
