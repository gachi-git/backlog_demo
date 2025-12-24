<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RawBacklogIssue extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'backlog_id',
        'issue_key',
        'data',
        'synced_at',
        'updated_at_backlog',
    ];

    protected $casts = [
        'data' => 'array',
        'synced_at' => 'datetime',
        'updated_at_backlog' => 'datetime',
    ];
}
