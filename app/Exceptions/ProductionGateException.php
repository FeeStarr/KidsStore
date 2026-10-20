<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an order is asked to start production without the sizing evidence
 * that production requires.
 *
 * The production service is the authoritative enforcement point: this exception is
 * how it refuses. It is deliberately not something an administrator can wave
 * through. If an order is stuck, the missing evidence has to be resolved through
 * the customer-facing workflow, because a silently bypassed gate leaves no record
 * of what was agreed and what the frock is being cut to.
 */
class ProductionGateException extends RuntimeException
{
    /**
     * @param  array<int, string>  $blockers
     */
    public function __construct(private readonly array $blockers)
    {
        parent::__construct(implode(' ', $blockers));
    }

    /**
     * @return array<int, string>
     */
    public function blockers(): array
    {
        return $this->blockers;
    }
}
