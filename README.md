# Backlog Demo

Backlog APIを使用したデータ同期システムの技術検証プロジェクト。

## 概要

このプロジェクトは、Backlog APIからタスクデータを取得し、ローカルデータベースに同期する機能の技術検証を目的としている。API制限への対応、差分更新、データベース設計を実装・検証しました。

## 技術スタック

- **Framework:** Laravel 12
- **Database:** MySQL (Docker/Sail)
- **API:** Backlog API v2
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

### 3. ダミーデータ生成

- Fakerを使用したダミーデータ生成
- Backlog APIでの課題作成

## セットアップ

### 1. 環境変数の設定

`.env` ファイルに以下を追加：

```bash
BACKLOG_SPACE_URL=https://your-space.backlog.jp
BACKLOG_API_KEY=your_api_key_here
```

### 2. データベースマイグレーション

```bash
./vendor/bin/sail artisan migrate
```

### 3. Backlog APIキーの取得

1. Backlogにログイン
2. 右上のアイコン → 個人設定
3. 左メニューの「API」
4. 「APIキーの発行」ボタンをクリック
5. 表示されたAPIキーを`.env`に設定

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

### データ確認

```bash
./vendor/bin/sail artisan tinker

>>> \App\Models\RawBacklogIssue::count()
>>> \App\Models\RawBacklogIssue::first()
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

### Models

- `SyncLog` - 同期ログ管理
- `RawBacklogIssue` - Backlog課題データ

## 技術検証結果

### ✅ 検証完了項目

- API認証・接続
- レートリミット制御（429対応）
- ページネーション（100件ずつ）
- 差分更新の仕組み
- エラーハンドリング（401, 404）
- データ保存（MySQL、JSON形式）
- ダミーデータ生成

### 📝 未検証項目

- 2回目以降の差分同期の実動作
- 大量データ（100件以上）の取得
- コメント・添付ファイルの取得
- Webhook連携

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
- [Laravel Documentation](https://laravel.com/docs/12.x)
