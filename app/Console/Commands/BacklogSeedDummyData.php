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

            // 意味のある日本語タスクリスト
            $taskTitles = [
                'API設計書作成',
                'ユーザー認証機能実装',
                'データベーススキーマ設計',
                'バグ修正：ログイン画面のレイアウト崩れ',
                'パフォーマンス改善：クエリ最適化',
                'フロントエンド：ダッシュボード画面実装',
                'バックエンド：決済API連携',
                'セキュリティ対策：XSS脆弱性の修正',
                'ドキュメント更新：開発環境セットアップ手順',
                'テストコード作成：ユーザー管理機能',
                'インフラ：Docker環境構築',
                'リファクタリング：コントローラーの共通化',
                '新機能：メール通知システム',
                'バグ修正：画像アップロードエラー',
                'UI改善：レスポンシブ対応',
                'データ移行：旧システムからのインポート',
                'API：検索機能エンドポイント追加',
                'セキュリティ：JWT認証の導入',
                'パフォーマンス：キャッシュ機構の実装',
                'テスト：E2Eテストシナリオ作成',
                'バグ修正：日付フォーマットの不具合',
                '機能追加：CSVエクスポート機能',
                'インフラ：CI/CDパイプライン構築',
                'ドキュメント：API仕様書更新',
                'リファクタリング：冗長なコードの削減',
                '新機能：多言語対応',
                'バグ修正：タイムゾーン処理のエラー',
                'UI改善：アクセシビリティ対応',
                'データベース：インデックス最適化',
                'セキュリティ：CSRF対策の強化',
            ];

            $taskDescriptions = [
                'RESTful APIのエンドポイント設計書を詳細に作成する。各エンドポイントのリクエスト・レスポンス形式、エラーハンドリングを定義する。',
                'JWT認証を使用したユーザー認証機能を実装する。ログイン、ログアウト、トークンリフレッシュ機能を含む。',
                'アプリケーションで使用するデータベースのテーブル構造を設計し、ER図を作成する。正規化と性能を考慮する。',
                'ログイン画面でボタンがずれている問題を修正する。レスポンシブ対応も確認する。',
                'N+1問題を解決し、データベースクエリを最適化する。Eager Loadingを活用する。',
                'ユーザーダッシュボード画面をReactで実装する。グラフ表示とリアルタイム更新機能を含む。',
                '外部決済サービスのAPIと連携し、クレジットカード決済機能を実装する。',
                'ユーザー入力のサニタイジング処理を追加し、XSS攻撃を防ぐ。',
                '新規メンバーが開発環境をセットアップできるよう、手順書を最新化する。',
                'ユーザー管理機能（CRUD）のユニットテストとインテグレーションテストを作成する。',
                'Docker ComposeでLaravel、MySQL、Redisの開発環境を構築する。',
                '複数のコントローラーで重複しているロジックを抽象化し、ベースコントローラーに移動する。',
                'ユーザーへのメール通知機能を実装する。キュー処理で非同期送信を行う。',
                '画像アップロード時にファイルサイズが大きいとエラーになる問題を修正する。',
                'スマートフォンとタブレットでのレイアウトを最適化し、レスポンシブデザインを実装する。',
                '旧システムのデータベースからデータを抽出し、新システムのフォーマットに変換してインポートする。',
                '全文検索機能を提供するAPIエンドポイントを追加する。ページネーションとフィルタリングをサポート。',
                'セッションベース認証からJWT認証に移行し、セキュリティを強化する。',
                'Redisを使用したキャッシュ機構を実装し、頻繁にアクセスされるデータの取得速度を向上させる。',
                'Cypressを使用してE2Eテストシナリオを作成し、主要なユーザーフローをカバーする。',
                '日付表示がタイムゾーンによって異なる問題を修正し、UTCで統一する。',
                'データをCSVファイルとしてエクスポートする機能を実装する。大量データに対応。',
                'GitHub ActionsでCI/CDパイプラインを構築し、自動テストとデプロイを実現する。',
                '新しく追加したAPIエンドポイントのドキュメントを作成し、既存の仕様書を更新する。',
                '重複したコードや使用されていないコードを削除し、コードベースをクリーンに保つ。',
                'i18n対応を実装し、日本語と英語の切り替えができるようにする。',
                'ユーザーのタイムゾーン設定が正しく反映されないエラーを修正する。',
                'キーボード操作とスクリーンリーダー対応を追加し、アクセシビリティを向上させる。',
                '頻繁に検索されるカラムにインデックスを追加し、クエリのパフォーマンスを改善する。',
                'CSRFトークンの検証を強化し、クロスサイトリクエストフォージェリ攻撃を防ぐ。',
            ];

            $faker = Faker::create('ja_JP');
            $progressBar = $this->output->createProgressBar($count);
            $progressBar->start();

            $successCount = 0;
            $failCount = 0;

            for ($i = 0; $i < $count; $i++) {
                try {
                    $titleIndex = array_rand($taskTitles);
                    $issueData = [
                        'projectId' => $project['id'],
                        'summary' => $taskTitles[$titleIndex],
                        'description' => $taskDescriptions[$titleIndex],
                        'issueTypeId' => $issueTypes[array_rand($issueTypes)]['id'],
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
