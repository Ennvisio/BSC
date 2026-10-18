<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * The "Show entries" half of the server-paginated requisition lists (item and
 * service): which page size was asked for, and paging a query to it. The
 * search half differs per list - each controller knows its own columns.
 */
trait PaginatesRequisitionLists
{
    /** A fixed size, or 'all' for everything that matches; anything else is 15. */
    private function requisitionPerPage(Request $request): string
    {
        $choice = $request->query('per_page');

        return in_array($choice, ['15', '25', '50', '100', '200', 'all'], true) ? $choice : '15';
    }

    private function paginateRequisitions($query, string $choice)
    {
        $size = $choice === 'all' ? max(1, (clone $query)->count()) : (int) $choice;

        return $query->paginate($size)->withQueryString();
    }

    /** Escapes LIKE wildcards so a typed % or _ matches itself. */
    private function likeTerm(string $q): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
    }
}
