<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A faculty-submitted request to update or delete a course they created.
 * Hold-until-approved: the course itself is untouched until an
 * admin/intern reviews this row (approve applies `payload`/deletes the
 * course; reject just marks this row rejected and leaves the course as-is).
 */
class CourseChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id', 'requested_by', 'action', 'payload',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'payload' => AsArrayObject::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
