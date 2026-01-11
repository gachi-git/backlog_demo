<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAnalysis extends Model
{
    protected $fillable = [
        'target_date',
        'summary_json',
        'advice_text',
    ];

    protected $casts = [
        'target_date' => 'date',
        'summary_json' => 'array',
        'advice_text' => 'array',
    ];

    /**
     * 特定日の分析を取得
     */
    public static function findByDate(string $date): ?self
    {
        return self::where('target_date', $date)->first();
    }

    /**
     * 特定日の分析が存在するかチェック
     */
    public static function existsForDate(string $date): bool
    {
        return self::where('target_date', $date)->exists();
    }
}
