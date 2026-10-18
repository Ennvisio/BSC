<?php

namespace App\Http\Controllers;

use App\Port;
use Illuminate\Http\Request;

class PortController extends Controller
{
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
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('name_ascii', 'like', "%{$term}%")
                    ->orWhere('country_name', 'like', "%{$term}%");
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
}
