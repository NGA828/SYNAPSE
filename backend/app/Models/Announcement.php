<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use BelongsToSchool, HasFactory;

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_STUDENTS = 'students';

    public const AUDIENCE_TEACHERS = 'teachers';

    /**
     * The closed set of audiences. Kept next to the individual constants so a
     * new audience cannot be added in one place and forgotten in the validators
     * that read this list.
     *
     * @var list<string>
     */
    public const AUDIENCES = [
        self::AUDIENCE_ALL,
        self::AUDIENCE_STUDENTS,
        self::AUDIENCE_TEACHERS,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'user_id',
        'title',
        'body',
        'audience',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
