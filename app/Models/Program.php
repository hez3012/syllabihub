<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Program extends Model
{
    protected $fillable = ['code', 'name'];

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }
}