<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErplyToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_code',
        'username',
        'session_key',
        'jwt_token',
        'expires_at',
        'last_used_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Check if token is valid and not expired
     */
    public function isValid(): bool
    {
        return $this->session_key && 
               $this->expires_at && 
               $this->expires_at->isFuture() &&
               $this->expires_at->diffInMinutes(\Carbon\Carbon::now()) > 5;
    }

    /**
     * Check if token needs refresh
     */
    public function needsRefresh(): bool
    {
        return !$this->isValid() || 
               $this->expires_at->diffInMinutes(\Carbon\Carbon::now()) <= 30;
    }

    /**
     * Mark token as used
     */
    public function markAsUsed(): void
    {
        $this->last_used_at = now();
        $this->save();
    }

    /**
     * Get active token for user
     */
    public static function getActiveToken(string $username, string $clientCode): ?self
    {
        return self::where('username', $username)
                   ->where('client_code', $clientCode)
                   ->where('expires_at', '>', now())
                   ->orderBy('expires_at', 'desc')
                   ->first();
    }
}
