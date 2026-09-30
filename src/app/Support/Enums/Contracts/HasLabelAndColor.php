<?php

namespace App\Support\Enums\Contracts;

/**
 * Every status enum exposes a human label and a design-system colour token for the UI (specs/05 §3).
 */
interface HasLabelAndColor
{
    public function label(): string;

    /**
     * Semantic colour token name from specs/18 §3, e.g. `state-success`.
     */
    public function color(): string;
}
