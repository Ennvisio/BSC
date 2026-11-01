<?php

namespace App\Http\Controllers;

use App\Port;
use Illuminate\Http\Request;

class PortController extends Controller
{
    /**
     * Former/common names still used in everyday speech that don't appear
     * anywhere in the port's own record, so a plain substring match would
     * never find it - Bangladesh renamed Chittagong to Chattogram in 2018,
     * but the paperwork still says "Chittagong" more often than not. Add a
     * port here as this comes up again elsewhere.
     */
    private const ALIASES = [
        'chittagong' => 'Chattogram',
    ];

    /** Same guard HomeController applies: auth is wired per-controller in
     * this app, not on the route groups, so a controller without this is
     * reachable by a guest and fatals on auth()->user()->role. */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * AJAX: port suggestions for the requisition "Port" picker. With no
     * search term, returns the first page alphabetically (matches the
     * picker showing a browsable list before the user types anything);
     * with a term, matches it against the name (diacritic or plain) or
     * the country name, so "korea" surfaces Korean ports too.
     */
    public function search(Request $request)
    {
        $term = trim((string) $request->query('q'));

        $query = Port::query();

        if ($term !== '') {
            $alias = $this->aliasTarget($term);

            $query->where(function ($q) use ($term, $alias) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('name_ascii', 'like', "%{$term}%")
                    ->orWhere('country_name', 'like', "%{$term}%");

                if ($alias !== null) {
                    $q->orWhere('name', 'like', "%{$alias}%")
                        ->orWhere('name_ascii', 'like', "%{$alias}%");
                }
            });
        }

        $ports = $query->orderBy('name')->limit(50)->get(['id', 'name', 'country_name']);

        return response()->json($ports->map(fn ($port) => [
            'id' => $port->id,
            'name' => $port->name,
            'country_name' => $port->country_name,
            'label' => "{$port->name} ({$port->country_name})",
        ]));
    }

    /** The port name a typed alias (or the start of one, e.g. "chitta") stands in for, if any. */
    private function aliasTarget(string $term): ?string
    {
        $term = mb_strtolower($term);

        foreach (self::ALIASES as $alias => $target) {
            if (str_contains($alias, $term)) {
                return $target;
            }
        }

        return null;
    }
}
