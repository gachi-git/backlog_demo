<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    protected $fillable = [
        'resource_type',
        'last_synced_at',
        'status',
        'error_message',
        'total_fetched',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    /**
     * 特定リソースタイプの最終同期ログを取得
     */
    public static function getLastSync(string $resourceType): ?self
    {
        return self::where('resource_type', $resourceType)
            ->whereNotNull('last_synced_at')
            ->orderBy('last_synced_at', 'desc')
            ->first();
    }
}
