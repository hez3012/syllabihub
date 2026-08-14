<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Syllabus extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'syllabi';

    protected $fillable = [
        'course_id', 'file_path', 'file_type', 'original_filename',
        'raw_text', 'curriculum_year',
        'status', 'uploaded_by',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Human-readable label for status (pending/processed/failed) — for display only, the raw value is still what's stored/queried. */
    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}