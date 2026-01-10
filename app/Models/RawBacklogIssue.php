<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /**
     * daily_plansとのリレーション（1つの課題が複数日の計画に含まれる可能性）
     */
    public function dailyPlans(): HasMany
    {
        return $this->hasMany(DailyPlan::class, 'raw_issue_id');
    }

    /**
     * 課題が完了しているかチェック
     */
    public function isCompleted(): bool
    {
        $statusName = $this->data['status']['name'] ?? null;
        return in_array($statusName, ['完了', '処理済み', 'Done', 'Completed']);
    }

    /**
     * 未完了の課題を取得するスコープ
     */
    public function scopeIncomplete($query)
    {
        return $query->whereRaw("JSON_EXTRACT(data, '$.status.name') NOT IN ('完了', '処理済み', 'Done', 'Completed')");
    }
}
