<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function updateStatus(Request $request, $id)
    {
        // 1. バリデーション
        // フロントエンドから送られてくる値(planned, in_progress...)をチェック
        $validated = $request->validate([
            'status' => 'required|string|in:planned,in_progress,completed,skipped',
        ]);

        // 2. データベース更新
        // フロントでは 'status' と呼んでいるが、DBのカラムは 'lane_status' なので変換して保存
        DB::table('daily_plans')
            ->where('id', $id)
            ->update(['lane_status' => $validated['status']]);

        // 3. 成功レスポンス
        return response()->json([
            'message' => 'Updated successfully',
            'id' => $id,
            'new_status' => $validated['status']
        ]);
    }
}