<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delegation extends Model
{
    protected $fillable = [
        'delegator_id',
        'delegate_id',
        'start_date',
        'end_date',
        'is_active',
        'reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
    ];

    public function delegator()
    {
        return $this->belongsTo(Employee::class, 'delegator_id');
    }

    public function delegate()
    {
        return $this->belongsTo(Employee::class, 'delegate_id');
    }
}