<?php

namespace App\Sync\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * PD-34 (decided 2026-11-23, confirmed 2026-11-24): a delete on the cloud of
 * a vessel's own record, for a vessel that runs its own server. Thrown
 * before anything is deleted, so the record is untouched.
 */
class VesselRecordProtected extends RuntimeException
{
    /** Shown as a refusal (403) rather than a server error. */
    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $this->getMessage()], 403);
        }

        abort(403, $this->getMessage());
    }
}
