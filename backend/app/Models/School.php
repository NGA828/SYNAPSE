<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class School extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIAL = 'trial';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_EXPIRED = 'expired';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'email',
        'phone',
        'address',
        'logo',
        'status',
        'timezone',
        'primary_color',
        'subscription_plan_id',
        'subscription_status',
        'subscription_started_at',
        'subscription_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_started_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function settings(): HasMany
    {
        return $this->hasMany(SchoolSetting::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    /**
     * Read a school setting (arrays are JSON-encoded in storage).
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        $row = $this->settings()->where('key', $key)->first();

        if (! $row) {
            return $default;
        }

        return $this->decodeSetting($row->value);
    }

    /**
     * Persist a school setting.
     */
    public function setSetting(string $key, mixed $value): SchoolSetting
    {
        $encoded = is_string($value) || $value === null
            ? $value
            : json_encode($value);

        return SchoolSetting::updateOrCreate(
            ['school_id' => $this->id, 'key' => $key],
            ['value' => $encoded],
        );
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIAL], true);
    }

    private function decodeSetting(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
