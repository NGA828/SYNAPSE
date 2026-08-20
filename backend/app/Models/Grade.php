<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Grade extends Model
{
    use BelongsToSchool, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'subject_id',
        'class_id',
        'academic_year_id',
        'teacher_id',
        'test1',
        'test2',
        'exam',
    ];

    /**
     * Computed attributes serialised alongside the model.
     *
     * @var list<string>
     */
    protected $appends = ['average'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'test1' => 'float',
            'test2' => 'float',
            'exam' => 'float',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * Term average: the mean of every entered component (0–20 scale).
     */
    protected function average(): Attribute
    {
        return Attribute::get(function (): ?float {
            $scores = array_filter(
                [$this->test1, $this->test2, $this->exam],
                fn ($value) => $value !== null,
            );

            if ($scores === []) {
                return null;
            }

            return round(array_sum($scores) / count($scores), 2);
        });
    }
}
