<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'semester_id',
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

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(GradeScore::class);
    }

    /**
     * Term average.
     *
     * When weighted component scores exist, the average is the weighted mean
     * (Σ score×weight ÷ Σ weight). Otherwise it falls back to the mean of the
     * legacy test1/test2/exam columns (0–20 scale).
     */
    protected function average(): Attribute
    {
        return Attribute::get(function (): ?float {
            $scores = $this->relationLoaded('scores') ? $this->scores : $this->scores()->get();

            if ($scores->isNotEmpty()) {
                $weighted = $scores->map(function (GradeScore $score) {
                    return [
                        'score' => $score->score,
                        'weight' => (float) ($score->component?->weight ?? 0),
                    ];
                });

                $totalWeight = $weighted->sum('weight');

                if ($totalWeight > 0) {
                    $numerator = $weighted->sum(function ($row) {
                        return ($row['score'] ?? 0) * $row['weight'];
                    });

                    return round($numerator / $totalWeight, 2);
                }
            }

            $legacy = array_filter(
                [$this->test1, $this->test2, $this->exam],
                fn ($value) => $value !== null,
            );

            if ($legacy === []) {
                return null;
            }

            return round(array_sum($legacy) / count($legacy), 2);
        });
    }
}
