<?php

namespace App\Http\Controllers;

/**
 * Service requisitions cover work that isn't an item pick-list - certificate
 * servicing, class surveys, equipment maintenance, IT support - raised by the
 * same ship officers who raise item requisitions (chief-officer/second-
 * engineer, see the 'member' middleware group in routes/web.php).
 *
 * Unlike an item requisition, this one's approval chain ends at SRD level:
 * there's no SSM/procurement leg, since there's nothing physical for the ship
 * to receive back - it's closed once the work is done and paid for ashore.
 *
 * UI ONLY for now, per the current build step - create() just renders the
 * form. There is no store() yet: the form has nowhere to submit to (see
 * service-requisition-create.blade.php's JS), and nothing here persists.
 */
class ServiceRequisitionController extends Controller
{
    public function create()
    {
        return view('layouts.service-requisition-create');
    }
}
