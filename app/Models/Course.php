<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by', 'course_code', 'title',
        'prerequisite', 'corequisite',
        'lecture_hours', 'lab_hours',
        'credited_units', 'tuition_hours',
    ];

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'course_program')
            ->withPivot(['year_level', 'semester'])
            ->withTimestamps();
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

    public function programCodes(): array
    {
        return $this->programs->pluck('code')->all();
    }

    public function programLabel(): string
    {
        return $this->programs->pluck('code')->join(', ');
    }

    public function yearLevelLabels(): array
    {
        return $this->programs->mapWithKeys(
            fn ($program) => [$program->code => $this->pivotYearLabel($program->pivot->year_level)]
        )->all();
    }

    public function semesterLabels(): array
    {
        return $this->programs->mapWithKeys(
            fn ($program) => [$program->code => ucfirst((string) $program->pivot->semester)]
        )->all();
    }

    public function yearLevelLabel(): string
    {
        return $this->combinedLabel($this->yearLevelLabels());
    }

    public function semesterLabel(): string
    {
        return $this->combinedLabel($this->semesterLabels());
    }

    public function yearLevelValue()
    {
        $values = $this->programs->map(fn ($program) => $program->pivot->year_level)->filter()->unique();

        if ($values->isEmpty()) {
            return null;
        }

        return $values->count() === 1 ? (int) $values->first() : $values->implode(' / ');
    }

    public function semesterValue(): ?string
    {
        $values = $this->programs->map(fn ($program) => $program->pivot->semester)->filter()->unique();

        if ($values->isEmpty()) {
            return null;
        }

        return $values->count() === 1 ? (string) $values->first() : $values->implode(' / ');
    }

    private function combinedLabel(array $labels): string
    {
        if ($labels === []) {
            return '—';
        }

        $values = array_unique(array_values($labels));

        if (count($values) === 1) {
            return reset($values);
        }

        return collect($labels)
            ->map(fn ($label, $code) => "{$code}: {$label}")
            ->join(' · ');
    }

    private function pivotYearLabel($yearLevel): string
    {
        if ($yearLevel === null || $yearLevel === '') {
            return '—';
        }

        return match ((int) $yearLevel) {
            1 => '1st Year',
            2 => '2nd Year',
            3 => '3rd Year',
            4 => '4th Year',
            default => (string) $yearLevel,
        };
    }

    public function placementLabel(): string
    {
        return $this->programs
            ->map(fn ($program) => sprintf(
                '%s · %s · %s',
                $program->code,
                $this->pivotYearLabel($program->pivot->year_level),
                ucfirst((string) $program->pivot->semester)
            ))
            ->join(' | ');
    }
}
