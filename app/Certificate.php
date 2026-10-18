<?php

namespace App;

use App\VesselCertificate;
use Illuminate\Database\Eloquent\Model;

/**
 * A certificate NAME - "IOPP Certificate", "Certificate of Registry" - shared
 * by every vessel that holds it. Category is the only thing fixed here:
 * which section of the fleet's TEC-04 Certificate Checklist it belongs to
 * (A-L), since that's genuinely the same for every vessel.
 *
 * How long it's valid for and whether it's permanent are NOT here - they
 * vary by what's actually printed on each vessel's own copy of the
 * certificate, so they're recorded per vessel on VesselCertificate instead,
 * the same way issue_date/exp_date already are.
 */
class Certificate extends Model
{
    protected $fillable = ['name', 'category', 'cert_code', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'status' => 'boolean',
    ];

    const CATEGORIES = [
        'Registry/Tonnage',
        'Statutory Certificates',
        'Environmental Certificates',
        'ISM/ISPS/MLC',
        'Insurance',
        'Safety',
        'Crewing/Health',
        'Navigation and Communications',
        'Ship Inspections',
        'Cargo Installation & Equipment',
        'Mooring Lines and Equipment Certificates',
        'Other Cert',
    ];

    public function vesselCertificates()
    {
        return $this->hasMany(VesselCertificate::class);
    }
}
