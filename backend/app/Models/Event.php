<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use BelongsToSchool, HasFactory;

    public const TYPE_ASSEMBLY = 'assembly';

    public const TYPE_EXAM = 'exam';

    public const TYPE_HOLIDAY = 'holiday';

    public const TYPE_SPORTS = 'sports';

    public const TYPE_MEETING = 'meeting';

    public const TYPE_DEADLINE = 'deadline';

    public const TYPE_OTHER = 'other';

    /**
     * @var list<string>
     */
    public const TYPES = [
        self::TYPE_ASSEMBLY,
        self::TYPE_EXAM,
        self::TYPE_HOLIDAY,
        self::TYPE_SPORTS,
        self::TYPE_MEETING,
        self::TYPE_DEADLINE,
        self::TYPE_OTHER,
    ];

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_STUDENTS = 'students';

    public const AUDIENCE_TEACHERS = 'teachers';

    /**
     * @var list<string>
     */
    public const AUDIENCES = [
        self::AUDIENCE_ALL,
        self::AUDIENCE_STUDENTS,
        self::AUDIENCE_TEACHERS,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'user_id',
        'title',
        'description',
        'type',
        'starts_at',
        'ends_at',
        'all_day',
        'location',
        'audience',
        'is_published',
        'published_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'all_day' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Events a given role should see: `all`, or its own audience.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeVisibleToRole(Builder $query, string $role): Builder
    {
        $audience = match ($role) {
            User::ROLE_STUDENT => self::AUDIENCE_STUDENTS,
            User::ROLE_TEACHER => self::AUDIENCE_TEACHERS,
            // Admins run the school, so they see everything.
            default => null,
        };

        if ($audience === null) {
            return $query;
        }

        return $query->where(
            fn (Builder $inner) => $inner
                ->where('audience', self::AUDIENCE_ALL)
                ->orWhere('audience', $audience),
        );
    }

    /**
     * Events overlapping a window. An event with no `ends_at` is a point in
     * time, so it overlaps when its start falls inside the window.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeBetween(Builder $query, string $from, string $to): Builder
    {
        return $query
            ->where('starts_at', '<=', $to)
            ->where(
                fn (Builder $inner) => $inner
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $from),
            );
    }
}
