# NextLog - Backend

**NextLog**のバックエンドAPI
Backlog APIを使用したデータ同期システム + AI計画生成・分析機能を備えたタスク管理アプリケーション

## 概要

**NextLog**は、Backlog APIからタスクデータを取得し、ローカルデータベースに同期する機能と、Gemini AIを活用したタスク計画の自動生成・分析機能を提供するタスク管理アプリケーションです。このリポジトリはNextLogのバックエンドであり、API制限への対応、差分更新、データベース設計、AI連携を実装しています。

## 技術スタック

- **Framework:** Laravel 12
- **Database:** MySQL (Docker/Sail)
- **API:** Backlog API v2, Google Gemini API
- **言語:** PHP 8.2

## 主要機能

### 1. Backlog API連携

- API認証管理（API Key）
- レートリミット制御（429エラー時の自動リトライ）
- ページネーション処理（100件ずつ自動取得）
- エラーハンドリング

### 2. データ同期

- Backlogから課題データを取得
- 差分更新対応（`updatedSince`パラメータ）
- MySQLへの保存（JSON形式）

### 3. AI計画生成 

- **Gemini AI連携**: タスク情報を分析し、具体的なアドバイスを自動生成
- **日次計画API**: 未完了タスクから優先度・期限を考慮して計画を自動生成
- **エンドポイント**: `POST /api/planning/generate`
- **レート制限対応**: API呼び出し間に5秒の待機時間

### 4. AI分析アドバイス 

- **作業パターン分析**: 過去7日間のタスクデータを分析
- **構造化アドバイス**: 必ず3個のアドバイスを生成（推奨/緊急/参考タグ）
- **エンドポイント**: `POST /api/analysis/advice`
- **キャッシュ機能**: 同じ日付のリクエストはキャッシュから高速応答
- **フォールバック機能**: Gemini APIエラー時も適切なアドバイスを提供

### 5. ダミーデータ生成

- Fakerを使用したダミーデータ生成
- Backlog APIでの課題作成

## セットアップ

### 1. 環境変数の設定

`.env` ファイルに以下を追加：

```bash
BACKLOG_SPACE_URL=https://your-space.backlog.jp
BACKLOG_API_KEY=your_api_key_here
GEMINI_API_KEY=your_gemini_api_key_here
```

### 2. データベースマイグレーション

```bash
./vendor/bin/sail artisan migrate
```

### 3. APIキーの取得

**Backlog APIキー:**
1. Backlogにログイン
2. 右上のアイコン → 個人設定
3. 左メニューの「API」
4. 「APIキーの発行」ボタンをクリック
5. 表示されたAPIキーを`.env`に設定

**Gemini APIキー:**
1. [Google AI Studio](https://ai.google.dev/) にアクセス
2. 「Get API key」をクリック
3. 新しいAPIキーを作成
4. 表示されたAPIキーを`.env`に設定

## 使い方

### データ同期

Backlogから課題を取得してDBに保存：

```bash
# 差分同期（前回同期以降の更新分のみ）
./vendor/bin/sail artisan backlog:sync

# 全件取得
./vendor/bin/sail artisan backlog:sync --full

# 指定日時以降を取得
./vendor/bin/sail artisan backlog:sync --since=2025-12-01
```

### ダミーデータ投入（開発・テスト用）

開発環境でのテスト用にダミー課題をBacklogに作成：

```bash
# 10件作成（デフォルト）
./vendor/bin/sail artisan backlog:seed-dummy

# 件数指定
./vendor/bin/sail artisan backlog:seed-dummy --count=5

# プロジェクト指定
./vendor/bin/sail artisan backlog:seed-dummy --project=12345
```

### AI計画生成

未完了タスクから自動で今日の計画を生成：

```bash
# APIエンドポイント
POST /api/planning/generate

# DBから自動取得する場合
curl -X POST http://localhost/api/planning/generate

# フロントから選択したタスクを送信する場合
curl -X POST http://localhost/api/planning/generate \
  -H "Content-Type: application/json" \
  -d '{
    "issues": [
      {
        "title": "課題タイトル",
        "description": "課題の説明",
        "priority": "高",
        "dueDate": "2026-01-15",
        "estimatedHours": 2,
        "issue_key": "PROJ-123"
      }
    ]
  }'
```

**レスポンス例:**
```json
{
  "success": true,
  "message": "3件の計画を生成しました",
  "plans": [
    {
      "id": 1,
      "issue_key": "PROJ-123",
      "title": "N+1問題解決とEager Loading",
      "planned_minutes": 1260,
      "priority": "高",
      "ai_comment": "まずは既存のクエリを確認し..."
    }
  ],
  "target_date": "2025-12-29"
}
```

### AI分析アドバイス

過去7日間のタスクを分析してアドバイスを生成：

```bash
# APIエンドポイント
POST /api/analysis/advice

# curlでのテスト（基本）
curl -X POST http://localhost/api/analysis/advice \
  -H "Content-Type: application/json" \
  -d '{}'

# キャッシュを無視して再生成
curl -X POST http://localhost/api/analysis/advice \
  -H "Content-Type: application/json" \
  -d '{"refresh": true}'
```

**レスポンス例:**
```json
{
  "success": true,
  "cached": false,
  "data": {
    "target_date": "2026-01-11",
    "advice": [
      {
        "title": "タスク記録の徹底をお願いします",
        "description": "日別データが全て0件のため...",
        "tag": "緊急",
        "type": "warning"
      },
      {
        "title": "完了率向上と失敗原因の分析",
        "description": "完了率55%、失敗率18%は...",
        "tag": "推奨",
        "type": "recommend"
      },
      {
        "title": "タスクの細分化と進捗管理",
        "description": "実行中タスクや失敗タスクが多いことから...",
        "tag": "推奨",
        "type": "recommend"
      }
    ]
  }
}
```

### 統計API

#### 1. サマリー統計

```bash
# APIエンドポイント
GET /api/analysis/summary

# curlでのテスト
curl http://localhost/api/analysis/summary
```

**レスポンス例:**
```json
{
  "total_tasks": 13,
  "completion_rate": 8,
  "in_progress": 0,
  "failure_rate": 8,
  "period": "2026-01-15"
}
```

#### 2. 週次進捗（棒グラフ用）

```bash
# APIエンドポイント
GET /api/analysis/weekly-progress

# curlでのテスト
curl http://localhost/api/analysis/weekly-progress
```

**レスポンス例:**
```json
[
  {
    "date": "2026-01-09",
    "day_of_week": "Fri",
    "total": 0,
    "completed": 0,
    "failed": 0
  },
  {
    "date": "2026-01-10",
    "day_of_week": "Sat",
    "total": 2,
    "completed": 1,
    "failed": 1
  }
]
```

#### 3. カテゴリ別完了率

```bash
# APIエンドポイント
GET /api/analysis/categories

# curlでのテスト
curl http://localhost/api/analysis/categories
```

**レスポンス例:**
```json
[
  {
    "name": "学校",
    "total": 2,
    "completed": 1,
    "completion_rate": 50
  },
  {
    "name": "その他",
    "total": 12,
    "completed": 0,
    "completion_rate": 0
  }
]
```

### ステータス更新

カンバンでタスクを移動した際のステータス更新：

```bash
# APIエンドポイント
PATCH /api/planning/tasks/{id}/status

# タスクを完了にする
curl -X PATCH http://localhost/api/planning/tasks/141/status \
  -H "Content-Type: application/json" \
  -d '{"status": "completed"}'

# タスクをスキップする
curl -X PATCH http://localhost/api/planning/tasks/141/status \
  -H "Content-Type: application/json" \
  -d '{"status": "skipped"}'
```

**レスポンス例:**
```json
{
  "message": "Status updated successfully",
  "id": "141",
  "new_lane_status": "completed",
  "new_result_status": "completed"
}
```

**自動設定ロジック:**
- `completed` → `result_status: completed`（成功）
- `skipped` → `result_status: failed`（失敗）
- `planned`/`in_progress` → `result_status: pending`（未確定）

### データ確認

```bash
./vendor/bin/sail artisan tinker

>>> \App\Models\RawBacklogIssue::count()
>>> \App\Models\RawBacklogIssue::first()
>>> \App\Models\DailyPlan::with('rawIssue')->get()
```

## データベース構造

### sync_logs テーブル

同期状態を管理

| カラム | 型 | 説明 |
|--------|-----|------|
| resource_type | string | リソース種別 |
| last_synced_at | timestamp | 最終同期日時 |
| status | string | 実行状態 |
| total_fetched | integer | 取得件数 |

### raw_backlog_issues テーブル

Backlog課題の生データを保存

| カラム | 型 | 説明 |
|--------|-----|------|
| backlog_id | bigint | BacklogのID（Unique） |
| issue_key | string | 課題キー（例: PROJ-123） |
| data | json | APIレスポンスの全データ |
| synced_at | timestamp | 同期日時 |
| updated_at_backlog | timestamp | Backlog最終更新日時 |

### daily_plans テーブル 

日次タスク計画を管理

| カラム | 型 | 説明 |
|--------|-----|------|
| raw_issue_id | bigint | raw_backlog_issuesへの外部キー |
| user_id | bigint | ユーザーID（nullable） |
| target_date | date | 計画日 |
| lane_status | string | カンバンのレーン状態 |
| result_status | string | 結果状態 |
| planned_minutes | integer | 予定時間（分） |
| actual_minutes | integer | 実績時間（分） |
| ai_comment | text | AIからのアドバイスコメント |

### ai_analyses テーブル 

AI分析結果とアドバイスを保存（キャッシュ）

| カラム | 型 | 説明 |
|--------|-----|------|
| target_date | date | 分析対象日 |
| summary_json | json | 集計データ（完了率、件数など） |
| advice_text | json | AIアドバイス（3個の構造化データ） |

## 実装クラス

### BacklogApiService

`app/Services/BacklogApiService.php`

Backlog APIとの通信を担当

**主要メソッド:**
- `getIssues(?string $updatedSince)` - 課題一覧取得
- `createIssue(array $data)` - 課題作成
- `getProjects()` - プロジェクト一覧
- `getIssueTypes(int $projectId)` - 課題タイプ一覧
- `getPriorities()` - 優先度一覧

### GeminiService 

`app/Services/GeminiService.php`

Gemini APIとの通信を担当

**主要メソッド:**
- `generateTaskComment(array $taskData)` - タスク情報からAIコメントを生成
- `generateAnalysisAdvice(array $summary)` - 統計データから3個のアドバイスを生成
- `buildPrompt(array $taskData)` - タスクコメント用プロンプト構築
- `buildAnalysisPrompt(array $summary)` - 分析アドバイス用プロンプト構築

### Controllers

**PlanningController** (`app/Http/Controllers/Api/PlanningController.php`)
- `generate()` - AI計画生成（issuesパラメータ対応）
- `updateStatus()` - タスクステータス更新（result_status自動設定）
- `updateDates()` - タスク日付更新
- `getDailyTasks()` - 今日のタスク取得
- `getUnscheduled()` - 未消化の課題取得

**AnalysisController** (`app/Http/Controllers/Api/AnalysisController.php`)
- `generateAdvice()` - AIアドバイス生成（3個固定）
- `getSummary()` - 統計サマリー取得
- `getWeeklyProgress()` - 週次進捗取得
- `getCategories()` - カテゴリ別完了率取得

### Models

- `SyncLog` - 同期ログ管理
- `RawBacklogIssue` - Backlog課題データ
- `DailyPlan` - 日次計画データ
- `AiAnalysis` - AI分析結果のキャッシュ

## 実装機能一覧

###  実装済み機能

#### Backlog連携
- API認証・接続（Backlog API）
- レートリミット制御（429対応）
- ページネーション（100件ずつ）
- 差分更新の仕組み
- エラーハンドリング（401, 404）
- データ保存（MySQL、JSON形式）
- ダミーデータ生成

#### AI計画生成
- Gemini APIによるタスクアドバイス生成
- 優先度・期限を考慮した自動計画作成
- フロントからのissuesパラメータ対応
- DBからの自動取得（後方互換性）

#### AI分析
- AIアドバイス生成（構造化、必ず3個）
- 過去7日間のタスク統計分析
- キャッシュ機能とフォールバック機能
- 統計サマリーAPI（全タスク、完了率等）
- 週次進捗API（棒グラフ用）
- カテゴリ別完了率API

#### タスク管理
- ステータス更新API（result_status自動設定）
- カンバンボード連携
- 完了・失敗ステータスの自動判定

## トラブルシューティング

### 401 Unauthorized エラー

APIキーが間違っている、または送信方法が間違っています。

**解決方法:**
1. Backlogで正しいAPIキーを取得
2. `.env`の`BACKLOG_API_KEY`を確認
3. APIキーはURLクエリパラメータとして送信される

### 404 Not Found エラー

`BACKLOG_SPACE_URL`が間違っています。

**解決方法:**
```bash
# 正しい形式
BACKLOG_SPACE_URL=https://your-space.backlog.jp
```

## 参考資料

- [Backlog API Documentation](https://developer.nulab.com/docs/backlog/)
- [Backlog API Rate Limit](https://developer.nulab.com/docs/backlog/rate-limit/)
- [Google Gemini API Documentation](https://ai.google.dev/gemini-api/docs)
- [Laravel Documentation](https://laravel.com/docs/12.x)
