<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class XeroToken extends Model
{
    protected $fillable = [
        'tenant_id',
        'access_token',
        'refresh_token',
        'expires_at',
        'tenant_name'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Encrypt access token when saving
     */
    public function setAccessTokenAttribute($value)
    {
        $this->attributes['access_token'] = Crypt::encrypt($value);
    }

    /**
     * Decrypt access token when retrieving
     */
    public function getAccessTokenAttribute($value)
    {
        return Crypt::decrypt($value);
    }

    /**
     * Encrypt refresh token when saving
     */
    public function setRefreshTokenAttribute($value)
    {
        $this->attributes['refresh_token'] = Crypt::encrypt($value);
    }

    /**
     * Decrypt refresh token when retrieving
     */
    public function getRefreshTokenAttribute($value)
    {
        return Crypt::decrypt($value);
    }

    /**
     * Check if token is expired
     */
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Find token by tenant ID
     */
    public static function findByTenantId($tenantId)
    {
        return static::where('tenant_id', $tenantId)->first();
    }
}
