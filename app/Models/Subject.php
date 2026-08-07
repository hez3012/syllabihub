<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subject extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'program_id', 'subject_code', 'title',
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

    public function facultyMembers()
    {
        return $this->belongsToMany(User::class, 'faculty_subjects');
    }
}