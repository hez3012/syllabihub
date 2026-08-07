<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A faculty-submitted request to update or delete a subject they created.
 * Hold-until-approved: the subject itself is untouched until an
 * admin/intern reviews this row (approve applies `payload`/deletes the
 * subject; reject just marks this row rejected and leaves the subject as-is).
 */
class SubjectChangeRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject_id', 'requested_by', 'action', 'payload',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'payload' => AsArrayObject::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
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
