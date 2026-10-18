<?php

namespace App;

use App\User;
use App\Vessel;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    /**
     * Which department each role belongs to. user_type is that same fact
     * stored a second time, picked by hand with a radio when an account is
     * created - and an account saved without picking one has a blank type,
     * which the dashboard, sidebar and requisition lists all branch on, so
     * the user landed on a blank page. The role already says what the type
     * is, so it is derived from here whenever it is missing.
     */
    const TYPE_BY_ROLE = [
        'master' => 'ship', 'chief-engineer' => 'ship', 'chief-officer' => 'ship',
        'second-engineer' => 'ship', 'operator' => 'ship',
        'dgm-ssm' => 'ssm', 'agm-ssm' => 'ssm', 'am-ssm' => 'ssm', 'superintendent-ssm' => 'ssm',
        'gm-srd' => 'srd', 'dgm-srd' => 'srd', 'agm-srd' => 'srd', 'am-srd' => 'srd',
        'superintendent-srd' => 'srd', 'technical-superintendent' => 'srd', 'marine-superintendent' => 'srd',
        'super-admin' => 'admin',
    ];

    /** The stored type, or the one the role implies when none was recorded. */
    public function getUserTypeAttribute($value)
    {
        return $value ?: (self::TYPE_BY_ROLE[$this->attributes['role'] ?? null] ?? $value);
    }

     public function user()
    {
       return $this->belongsTo(User::class);
    }
    public function vessel(){
       return $this->belongsTo(Vessel::class);
    }
}
