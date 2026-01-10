<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlan extends Model
{
    protected $fillable = [
        'raw_issue_id',
        'user_id',
        'target_date',
        'lane_status',
        'result_status',
        'planned_minutes',
        'actual_minutes',
        'ai_comment',
    ];

    protected $casts = [
        'target_date' => 'date',
        'planned_minutes' => 'integer',
        'actual_minutes' => 'integer',
    ];

    /**
     * raw_backlog_issuesとのリレーション
     */
    public function rawBacklogIssue(): BelongsTo
    {
        return $this->belongsTo(RawBacklogIssue::class, 'raw_issue_id');
    }

    /**
     * usersとのリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 特定日付の計画を取得するスコープ
     */
    public function scopeDate($query, $date)
    {
        return $query->where('target_date', $date);
    }

    /**
     * 特定ユーザーの計画を取得するスコープ
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 未完了の計画を取得するスコープ
     */
    public function scopePending($query)
    {
        return $query->where('result_status', 'pending');
    }
}
