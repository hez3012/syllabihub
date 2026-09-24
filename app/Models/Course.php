<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program_id', 'created_by', 'course_code', 'title',
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

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

    public function semesterLabel(): string
    {
        return ucfirst($this->semester);
    }
}
