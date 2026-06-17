<?php

namespace App\Http\Requests\Concerns;

trait NormalizesDutyTimes
{
    protected function normalizeTimeForValidation(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return strlen($time) === 5 ? $time : substr($time, 0, 5);
    }

    protected function normalizeTimeForStorage(?string $time): ?string
    {
        $normalized = $this->normalizeTimeForValidation($time);

        return $normalized ? $normalized.':00' : null;
    }
}
