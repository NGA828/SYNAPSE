<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A "Level 3A"-style school class. Uses the `classes` table because `Class`
 * is a reserved word in PHP.
 */
class SchoolClass extends Model
{
    use BelongsToSchool, HasFactory;

    protected $table = 'classes';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['school_id', 'name'];

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function teachingAssignments(): HasMany
    {
        return $this->hasMany(TeachingAssignment::class, 'class_id');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'class_id');
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class, 'class_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'class_id');
    }

    /**
     * Students enrolled in this class (across years; pivot carries the year).
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'enrollments', 'class_id', 'student_id')
            ->withPivot('academic_year_id')
            ->withTimestamps();
    }
}
