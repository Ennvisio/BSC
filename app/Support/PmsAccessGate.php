<?php

namespace App\Support;

use App\Http\Controllers\Concerns\PmsAccess;

/**
 * Blade and view composers can't call a controller's protected PmsAccess
 * methods directly - this tiny class uses the same trait so "can the
 * current user see the PMS module" has exactly one rule, shared by the
 * header's PMS button and every PmsAccess-using controller.
 */
class PmsAccessGate
{
    use PmsAccess;

    public function canView(): bool
    {
        return auth()->check() && ($this->pmsIsShip() || in_array($this->pmsRole(), self::$pmsFleetViewers, true));
    }
}
