<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UrlStatus;
use Carbon\Carbon;
use Database\Factories\ShortUrlFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $original_url
 * @property string $original_url_hash
 * @property UrlStatus $status
 * @property int $clicks
 * @property Carbon|null $expires_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 */
final class ShortUrl extends Model
{
    /** @use HasFactory<ShortUrlFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'original_url',
        'original_url_hash',
        'status',
        'clicks',
        'expires_at',
    ];

    /**
     * Generate SHA-256 hash for the original URL.
     */
    public static function hashUrl(string $url): string
    {
        return hash('sha256', $url);
    }

    /**
     * Check if the URL is accessible (active and not expired).
     */
    public function isAccessible(): bool
    {
        if (! $this->status->isAccessible()) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the URL has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Increment the click counter.
     */
    public function incrementClicks(): void
    {
        $this->increment('clicks');
    }

    /**
     * Scope to find by code.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ShortUrl>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ShortUrl>
     */
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope to find by URL hash.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ShortUrl>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ShortUrl>
     */
    public function scopeByUrlHash($query, string $hash)
    {
        return $query->where('original_url_hash', $hash);
    }

    /**
     * Scope to get only active URLs.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ShortUrl>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ShortUrl>
     */
    public function scopeActive($query)
    {
        return $query->where('status', UrlStatus::Active);
    }

    /**
     * Scope to get only accessible URLs (active and not expired).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ShortUrl>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ShortUrl>
     */
    public function scopeAccessible($query)
    {
        return $query->active()
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => UrlStatus::class,
            'clicks' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}
