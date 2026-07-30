<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'type',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    // Check if a given date falls within this holiday
    public function coversDate(string $date): bool
    {
        return $this->start_date->toDateString() <= $date
            && $this->end_date->toDateString() >= $date;
    }

    // Type label in Chinese
    public function typeLabel(): string
    {
        return match($this->type) {
            'public'  => '國定假日',
            'typhoon' => '颱風假',
            'other'   => '特別假日',
            default   => '假日',
        };
    }
}