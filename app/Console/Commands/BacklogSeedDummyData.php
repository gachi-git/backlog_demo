<?php

namespace App\Console\Commands;

use App\Services\BacklogApiService;
use Illuminate\Console\Command;
use Faker\Factory as Faker;
use Exception;

class BacklogSeedDummyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backlog:seed-dummy
                            {--count=10 : 作成するダミー課題の数}
                            {--project= : プロジェクトIDを指定（省略時は選択）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backlogにダミーデータ（課題）を投入します';

    protected BacklogApiService $backlogApi;

    public function __construct(BacklogApiService $backlogApi)
    {
        parent::__construct();
        $this->backlogApi = $backlogApi;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $this->info('Backlogダミーデータ投入を開始します...');

            // プロジェクトを選択
            $project = $this->selectProject();
            if (!$project) {
                $this->error('プロジェクトが選択されませんでした。');
                return Command::FAILURE;
            }

            $this->info("プロジェクト: {$project['name']} (ID: {$project['id']})");

            // 課題タイプと優先度を取得
            $issueTypes = $this->backlogApi->getIssueTypes($project['id']);
            $priorities = $this->backlogApi->getPriorities();

            if (empty($issueTypes) || empty($priorities)) {
                $this->error('課題タイプまたは優先度が取得できませんでした。');
                return Command::FAILURE;
            }

            // ダミーデータを作成
            $count = (int) $this->option('count');
            $this->info("{$count} 件のダミー課題を作成します...");

            // 意味のある日本語タスクリスト（種別付き）
            $tasks = [
                ['title' => 'API設計書作成', 'description' => 'RESTful APIのエンドポイント設計書を詳細に作成する。各エンドポイントのリクエスト・レスポンス形式、エラーハンドリングを定義する。', 'type' => 'タスク'],
                ['title' => 'ユーザー認証機能実装', 'description' => 'JWT認証を使用したユーザー認証機能を実装する。ログイン、ログアウト、トークンリフレッシュ機能を含む。', 'type' => 'タスク'],
                ['title' => 'データベーススキーマ設計', 'description' => 'アプリケーションで使用するデータベースのテーブル構造を設計し、ER図を作成する。正規化と性能を考慮する。', 'type' => 'タスク'],
                ['title' => 'バグ修正：ログイン画面のレイアウト崩れ', 'description' => 'ログイン画面でボタンがずれている問題を修正する。レスポンシブ対応も確認する。', 'type' => 'バグ'],
                ['title' => 'パフォーマンス改善：クエリ最適化', 'description' => 'N+1問題を解決し、データベースクエリを最適化する。Eager Loadingを活用する。', 'type' => 'その他'],
                ['title' => 'フロントエンド：ダッシュボード画面実装', 'description' => 'ユーザーダッシュボード画面をReactで実装する。グラフ表示とリアルタイム更新機能を含む。', 'type' => 'タスク'],
                ['title' => 'バックエンド：決済API連携', 'description' => '外部決済サービスのAPIと連携し、クレジットカード決済機能を実装する。', 'type' => 'タスク'],
                ['title' => 'セキュリティ対策：XSS脆弱性の修正', 'description' => 'ユーザー入力のサニタイジング処理を追加し、XSS攻撃を防ぐ。', 'type' => 'バグ'],
                ['title' => 'ドキュメント更新：開発環境セットアップ手順', 'description' => '新規メンバーが開発環境をセットアップできるよう、手順書を最新化する。', 'type' => 'その他'],
                ['title' => 'テストコード作成：ユーザー管理機能', 'description' => 'ユーザー管理機能（CRUD）のユニットテストとインテグレーションテストを作成する。', 'type' => 'タスク'],
                ['title' => 'インフラ：Docker環境構築', 'description' => 'Docker ComposeでLaravel、MySQL、Redisの開発環境を構築する。', 'type' => 'タスク'],
                ['title' => 'リファクタリング：コントローラーの共通化', 'description' => '複数のコントローラーで重複しているロジックを抽象化し、ベースコントローラーに移動する。', 'type' => 'その他'],
                ['title' => '新機能：メール通知システム', 'description' => 'ユーザーへのメール通知機能を実装する。キュー処理で非同期送信を行う。', 'type' => 'タスク'],
                ['title' => 'バグ修正：画像アップロードエラー', 'description' => '画像アップロード時にファイルサイズが大きいとエラーになる問題を修正する。', 'type' => 'バグ'],
                ['title' => 'UI改善：レスポンシブ対応', 'description' => 'スマートフォンとタブレットでのレイアウトを最適化し、レスポンシブデザインを実装する。', 'type' => '要望'],
                ['title' => 'データ移行：旧システムからのインポート', 'description' => '旧システムのデータベースからデータを抽出し、新システムのフォーマットに変換してインポートする。', 'type' => 'タスク'],
                ['title' => 'API：検索機能エンドポイント追加', 'description' => '全文検索機能を提供するAPIエンドポイントを追加する。ページネーションとフィルタリングをサポート。', 'type' => 'タスク'],
                ['title' => 'セキュリティ：JWT認証の導入', 'description' => 'セッションベース認証からJWT認証に移行し、セキュリティを強化する。', 'type' => 'タスク'],
                ['title' => 'パフォーマンス：キャッシュ機構の実装', 'description' => 'Redisを使用したキャッシュ機構を実装し、頻繁にアクセスされるデータの取得速度を向上させる。', 'type' => 'その他'],
                ['title' => 'テスト：E2Eテストシナリオ作成', 'description' => 'Cypressを使用してE2Eテストシナリオを作成し、主要なユーザーフローをカバーする。', 'type' => 'タスク'],
                ['title' => 'バグ修正：日付フォーマットの不具合', 'description' => '日付表示がタイムゾーンによって異なる問題を修正し、UTCで統一する。', 'type' => 'バグ'],
                ['title' => '機能追加：CSVエクスポート機能', 'description' => 'データをCSVファイルとしてエクスポートする機能を実装する。大量データに対応。', 'type' => 'タスク'],
                ['title' => 'インフラ：CI/CDパイプライン構築', 'description' => 'GitHub ActionsでCI/CDパイプラインを構築し、自動テストとデプロイを実現する。', 'type' => 'タスク'],
                ['title' => 'ドキュメント：API仕様書更新', 'description' => '新しく追加したAPIエンドポイントのドキュメントを作成し、既存の仕様書を更新する。', 'type' => 'その他'],
                ['title' => 'リファクタリング：冗長なコードの削減', 'description' => '重複したコードや使用されていないコードを削除し、コードベースをクリーンに保つ。', 'type' => 'その他'],
                ['title' => '新機能：多言語対応', 'description' => 'i18n対応を実装し、日本語と英語の切り替えができるようにする。', 'type' => 'タスク'],
                ['title' => 'バグ修正：タイムゾーン処理のエラー', 'description' => 'ユーザーのタイムゾーン設定が正しく反映されないエラーを修正する。', 'type' => 'バグ'],
                ['title' => 'UI改善：アクセシビリティ対応', 'description' => 'キーボード操作とスクリーンリーダー対応を追加し、アクセシビリティを向上させる。', 'type' => '要望'],
                ['title' => 'データベース：インデックス最適化', 'description' => '頻繁に検索されるカラムにインデックスを追加し、クエリのパフォーマンスを改善する。', 'type' => 'その他'],
                ['title' => 'セキュリティ：CSRF対策の強化', 'description' => 'CSRFトークンの検証を強化し、クロスサイトリクエストフォージェリ攻撃を防ぐ。', 'type' => 'バグ'],
            ];

            $faker = Faker::create('ja_JP');
            $progressBar = $this->output->createProgressBar($count);
            $progressBar->start();

            $successCount = 0;
            $failCount = 0;

            for ($i = 0; $i < $count; $i++) {
                try {
                    $task = $tasks[array_rand($tasks)];

                    // 適切な種別を検索
                    $issueTypeId = $this->findIssueTypeId($issueTypes, $task['type']);

                    $issueData = [
                        'projectId' => $project['id'],
                        'summary' => $task['title'],
                        'description' => $task['description'],
                        'issueTypeId' => $issueTypeId,
                        'priorityId' => $priorities[array_rand($priorities)]['id'],
                    ];

                    // 期限日を設定（100%）
                    $issueData['dueDate'] = $faker->dateTimeBetween('now', '+3 months')->format('Y-m-d');

                    // 見積時間を設定（100%）
                    $issueData['estimatedHours'] = rand(1, 40);

                    $this->backlogApi->createIssue($issueData);
                    $successCount++;

                    // レートリミット対策: 少し待機
                    usleep(500000); // 0.5秒

                } catch (Exception $e) {
                    $failCount++;
                    $this->newLine();
                    $this->warn("課題作成失敗: {$e->getMessage()}");
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            $this->info("=== 完了 ===");
            $this->line("成功: {$successCount} 件");
            if ($failCount > 0) {
                $this->warn("失敗: {$failCount} 件");
            }

            return Command::SUCCESS;

        } catch (Exception $e) {
            $this->error("エラーが発生しました: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * 課題種別IDを検索
     */
    protected function findIssueTypeId(array $issueTypes, string $preferredType): int
    {
        // 優先する種別名で検索
        foreach ($issueTypes as $type) {
            if ($type['name'] === $preferredType) {
                return $type['id'];
            }
        }

        // 見つからない場合は最初の種別を返す
        return $issueTypes[0]['id'];
    }

    /**
     * プロジェクトを選択
     */
    protected function selectProject(): ?array
    {
        // --projectオプションが指定されている場合
        if ($projectId = $this->option('project')) {
            $projects = $this->backlogApi->getProjects();
            foreach ($projects as $project) {
                if ($project['id'] == $projectId) {
                    return $project;
                }
            }
            $this->error("プロジェクトID {$projectId} が見つかりませんでした。");
            return null;
        }

        // プロジェクト一覧を取得
        $projects = $this->backlogApi->getProjects();

        if (empty($projects)) {
            $this->error('プロジェクトが見つかりませんでした。');
            return null;
        }

        // 選択肢を作成
        $choices = [];
        foreach ($projects as $project) {
            $choices[$project['id']] = "{$project['name']} ({$project['projectKey']})";
        }

        $selectedId = $this->choice(
            'ダミーデータを投入するプロジェクトを選択してください',
            $choices
        );

        // 選択されたプロジェクトを返す
        foreach ($projects as $project) {
            if ("{$project['name']} ({$project['projectKey']})" === $selectedId) {
                return $project;
            }
        }

        return null;
    }
}
