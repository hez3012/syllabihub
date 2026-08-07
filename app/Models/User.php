<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Subjects this user created (admin/intern act freely; faculty own only what they made). */
    public function createdSubjects()
    {
        return $this->hasMany(Subject::class, 'created_by');
    }

    public function uploadedSyllabi()
    {
        return $this->hasMany(Syllabus::class, 'uploaded_by');
    }

    public function subjectChangeRequests()
    {
        return $this->hasMany(SubjectChangeRequest::class, 'requested_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isFaculty(): bool
    {
        return $this->role === 'faculty';
    }

    public function isIntern(): bool
    {
        return $this->role === 'intern';
    }
}