<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Vessel;
use App\CertificateCategory;

/**
 * One vessel's certificate, recorded in full by that vessel's own Master or
 * Chief Engineer: its category (chosen from super-admin's list), its title,
 * who issued it, when, how long it runs for, and whether it's permanent (no
 * expiry at all).
 *
 * Everything here is per vessel. There's no shared certificate-name catalog -
 * the title is typed against this record, so two vessels can hold the same
 * certificate on different terms without one overwriting the other.
 */
class VesselCertificate extends Model
{
    protected $casts = [
        'is_permanent' => 'boolean',
    ];

     public function vessel(){
       return $this->belongsTo(Vessel::class);
    }

    public function category()
    {
        return $this->belongsTo(CertificateCategory::class, 'category_id');
    }

    /**
     * Roll this certificate forward after a Renewal service requisition has
     * been fulfilled: re-issued on $issuedOn, expiring validity_years later.
     *
     * Both dates move together on purpose. The certificate form derives the
     * expiry from issue date + validity years, so advancing only the expiry
     * would leave the pair contradicting each other on screen.
     *
     * Returns the new expiry date, or null when there is nothing to roll - a
     * permanent certificate has no expiry to move, and one with no
     * validity_years recorded gives nothing to count from. Either way the
     * record is left exactly as it was.
     */
    public function renewFrom(\DateTimeInterface|string $issuedOn, ?string $by = null): ?string
    {
        if ($this->is_permanent || empty($this->validity_years)) {
            return null;
        }

        $issue = \Carbon\Carbon::parse($issuedOn)->startOfDay();

        $this->issue_date = $issue->toDateString();
        $this->exp_date = $issue->copy()->addYears((int) $this->validity_years)->toDateString();
        $this->updated_by = $by ?? $this->updated_by;
        $this->save();

        return $this->exp_date;
    }

    /** "5 years", "Permanent", or "—" when neither was recorded. */
    public function renewalLabel(): string
    {
        if ($this->is_permanent) {
            return 'Permanent';
        }

        if ($this->validity_years) {
            return $this->validity_years.' '.($this->validity_years == 1 ? 'year' : 'years');
        }

        return '—';
    }
}
