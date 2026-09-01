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

    /*
    | The documents a student can ask for. `type` stores the canonical label
    | rather than a slug so existing rows stay valid; `typeSlug()` derives a
    | stable machine identity for the API. Anything outside this list cannot be
    | issued by a template and needs a person.
    */
    public const TYPE_ENROLLMENT = 'Certificate of Enrollment';

    public const TYPE_TRANSCRIPT = 'Transcript Request';

    public const TYPE_RECOMMENDATION = 'Recommendation Letter';

    public const TYPE_GOOD_CONDUCT = 'Certificate of Good Conduct';

    public const TYPE_TRANSFER = 'Transfer Certificate';

    public const TYPE_LEAVING = 'School Leaving Certificate';

    public const TYPE_OTHER = 'Other';

    public const TYPES = [
        self::TYPE_ENROLLMENT,
        self::TYPE_TRANSCRIPT,
        self::TYPE_RECOMMENDATION,
        self::TYPE_GOOD_CONDUCT,
        self::TYPE_TRANSFER,
        self::TYPE_LEAVING,
        self::TYPE_OTHER,
    ];

    /** @var array<string, string> Canonical label => machine slug. */
    public const TYPE_SLUGS = [
        self::TYPE_ENROLLMENT => 'enrollment_certificate',
        self::TYPE_TRANSCRIPT => 'academic_transcript',
        self::TYPE_RECOMMENDATION => 'recommendation_letter',
        self::TYPE_GOOD_CONDUCT => 'good_conduct_certificate',
        self::TYPE_TRANSFER => 'transfer_certificate',
        self::TYPE_LEAVING => 'school_leaving_certificate',
        self::TYPE_OTHER => 'other',
    ];

    /**
     * Documents the certificate template can produce without a human. A
     * recommendation letter needs an author who knows the student, and "Other"
     * is by definition unspecified — issuing either automatically would mean
     * handing the student a document that is not what they asked for.
     *
     * @var list<string>
     */
    public const AUTO_GENERATABLE_TYPES = [
        self::TYPE_ENROLLMENT,
        self::TYPE_TRANSCRIPT,
        self::TYPE_GOOD_CONDUCT,
        self::TYPE_TRANSFER,
        self::TYPE_LEAVING,
    ];

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

    /**
     * A stable machine identity for the type, for clients that should not have
     * to match on prose.
     */
    public function typeSlug(): string
    {
        return self::TYPE_SLUGS[$this->type] ?? 'unrecognised';
    }

    public function isAutoGeneratable(): bool
    {
        return in_array($this->type, self::AUTO_GENERATABLE_TYPES, true);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'request_id');
    }
}
