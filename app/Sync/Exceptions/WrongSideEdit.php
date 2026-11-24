<?php

namespace App\Sync\Exceptions;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * PD-34, edits (decided 2026-11-24): a change to data the other side owns
 * (App\Sync\WrongSideEdits). Thrown before anything is saved.
 */
class WrongSideEdit extends RuntimeException
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
