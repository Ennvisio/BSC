<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A section of the fleet's TEC-04 Certificate Checklist - "Registry/Tonnage",
 * "Statutory Certificates", and so on. Super-admin maintains this list; each
 * vessel's Master/Chief Engineer picks one of them when recording a
 * certificate, but never adds to it.
 */
class CertificateCategory extends Model
{
    protected $fillable = ['name', 'status', 'created_by', 'updated_by'];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function vesselCertificates()
    {
        return $this->hasMany(VesselCertificate::class, 'category_id');
    }
}
