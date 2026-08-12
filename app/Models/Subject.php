<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program_id', 'created_by', 'subject_code', 'title',
        'year_level', 'semester',
        'prerequisite', 'corequisite',
        'lecture_hours', 'lab_hours',
        'credited_units', 'tuition_hours',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function syllabi()
    {
        return $this->hasMany(Syllabus::class);
    }

    public function latestSyllabus()
    {
        return $this->hasOne(Syllabus::class)->latestOfMany();
    }

    /** Who created this subject. Null = seeded/legacy — no faculty owner. */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeRequests()
    {
        return $this->hasMany(SubjectChangeRequest::class);
    }

    /** Whether this subject has a not-yet-decided edit/delete request. */
    public function hasPendingChangeRequest(): bool
    {
        return $this->changeRequests()->where('status', 'pending')->exists();
    }

    /** Human-readable "Nth Year" label for year_level (1-4) — matches the Year Level dropdown options. */
    public function yearLevelLabel(): string
    {
        return match ($this->year_level) {
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            default => (string) $this->year_level,
        };
    }

    /** Human-readable label for semester (1st/2nd/summer) — for display only, the raw value is still what's stored/queried. */
    public function semesterLabel(): string
    {
        return ucfirst($this->semester);
    }
}
