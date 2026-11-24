<?php

namespace App\Support;

use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** A user who raised or signed requisitions is never deleted (UserRecordGuard). */
class UserStillReferenced extends RuntimeException
{
    public function render(Request $request): Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $this->getMessage()], 409);
        }

        abort(409, $this->getMessage());
    }
}
