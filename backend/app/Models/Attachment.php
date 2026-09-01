<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use BelongsToSchool, HasFactory;

    /**
     * Visible to every student enrolled in the owning record's class.
     */
    public const VISIBILITY_CLASS = 'class';

    /**
     * Visible only to the uploader and the teacher who owns the record.
     */
    public const VISIBILITY_PRIVATE = 'private';

    public const VISIBILITIES = [
        self::VISIBILITY_CLASS,
        self::VISIBILITY_PRIVATE,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'attachable_type',
        'attachable_id',
        'uploaded_by_role',
        'uploaded_by',
        'file_name',
        'mime_type',
        'size',
        'disk',
        'path',
        'visibility',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isPrivate(): bool
    {
        return $this->visibility === self::VISIBILITY_PRIVATE;
    }

    /**
     * A human-friendly size, for the UI ("1.4 MB").
     */
    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        return match (true) {
            $bytes >= 1048576 => round($bytes / 1048576, 1).' MB',
            $bytes >= 1024 => round($bytes / 1024).' KB',
            default => $bytes.' B',
        };
    }
}
