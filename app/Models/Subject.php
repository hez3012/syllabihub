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
}
