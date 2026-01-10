# Backlog Demo

Backlog APIを使用したデータ同期システム + AI計画生成の技術検証プロジェクト。

## 概要

このプロジェクトは、Backlog APIからタスクデータを取得し、ローカルデータベースに同期する機能と、Gemini AIを活用したタスク計画の自動生成機能の技術検証を目的としている。API制限への対応、差分更新、データベース設計、AI連携を実装・検証しました。

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

### 3. AI計画生成 🆕

- **Gemini AI連携**: タスク情報を分析し、具体的なアドバイスを自動生成
- **日次計画API**: 未完了タスクから優先度・期限を考慮して計画を自動生成
- **エンドポイント**: `POST /api/planning/generate`
- **レート制限対応**: API呼び出し間に5秒の待機時間

### 4. ダミーデータ生成

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

### ダミーデータ投入

テスト用のダミー課題をBacklogに作成：

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

# curlでのテスト
curl -X POST http://localhost/api/planning/generate
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
      "priority": "高"
    }
  ],
  "target_date": "2025-12-29"
}
```

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

### daily_plans テーブル 🆕

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

### GeminiService 🆕

`app/Services/GeminiService.php`

Gemini APIとの通信を担当

**主要メソッド:**
- `generateTaskComment(array $taskData)` - タスク情報からAIコメントを生成
- `buildPrompt(array $taskData)` - プロンプト構築

### Controllers

- `PlanningController` - AI計画生成API

### Models

- `SyncLog` - 同期ログ管理
- `RawBacklogIssue` - Backlog課題データ
- `DailyPlan` - 日次計画データ

## 技術検証結果

### ✅ 検証完了項目

- API認証・接続（Backlog & Gemini）
- レートリミット制御（429対応）
- ページネーション（100件ずつ）
- 差分更新の仕組み
- エラーハンドリング（401, 404）
- データ保存（MySQL、JSON形式）
- ダミーデータ生成
- **AI計画生成機能**
- **Gemini APIによるタスクアドバイス生成**
- **優先度・期限を考慮した自動計画作成**

### 📝 未検証項目

- 2回目以降の差分同期の実動作
- 大量データ（100件以上）の取得
- コメント・添付ファイルの取得
- Webhook連携
- フロント側との連携テスト

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

## セキュリティ

- ✅ `.env`は`.gitignore`に含まれています
- ✅ APIキーはハードコードされていません
- ✅ 環境変数経由で管理

## 参考資料

- [Backlog API Documentation](https://developer.nulab.com/docs/backlog/)
- [Backlog API Rate Limit](https://developer.nulab.com/docs/backlog/rate-limit/)
- [Google Gemini API Documentation](https://ai.google.dev/gemini-api/docs)
- [Laravel Documentation](https://laravel.com/docs/12.x)
