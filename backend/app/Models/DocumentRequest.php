<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's document/service request (e.g. Certificate of Enrollment).
 *
 * Uses the `requests` table; the model is named DocumentRequest to avoid
 * colliding with the HTTP request class in controllers.
 */
class DocumentRequest extends Model
{
    use BelongsToSchool, HasFactory;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_READY = 'ready';

    public const STATUS_REJECTED = 'rejected';

    public const OPEN_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_APPROVED,
    ];

    protected $table = 'requests';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'reference',
        'type',
        'reason',
        'status',
        'admin_note',
        'resolved_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
