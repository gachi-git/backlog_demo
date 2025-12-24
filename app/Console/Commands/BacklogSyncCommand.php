<?php

namespace App\Console\Commands;

use App\Models\RawBacklogIssue;
use App\Models\SyncLog;
use App\Services\BacklogApiService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Exception;

class BacklogSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backlog:sync
                            {--full : 全件同期（差分更新ではなく全データ取得）}
                            {--since= : 指定日時以降を同期（yyyy-MM-dd形式）}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backlogから課題データを同期します';

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
        $this->info('Backlog同期を開始します...');

        $syncLog = SyncLog::create([
            'resource_type' => 'issues',
            'status' => 'running',
        ]);

        try {
            // 差分更新の基準日時を決定
            $updatedSince = $this->determineUpdatedSince();

            if ($updatedSince) {
                $this->info("差分更新: {$updatedSince} 以降のデータを取得します");
            } else {
                $this->warn('全件取得モード');
            }

            // Backlog APIから課題を取得
            $this->info('Backlog APIから課題を取得中...');
            $issues = $this->backlogApi->getIssues($updatedSince);

            $this->info(count($issues) . ' 件の課題を取得しました');

            // データベースに保存
            $this->info('データベースに保存中...');
            $this->saveIssuesToDatabase($issues);

            // 同期ログを更新
            $syncLog->update([
                'status' => 'completed',
                'last_synced_at' => now(),
                'total_fetched' => count($issues),
            ]);

            $this->info('同期が完了しました！');

            // レートリミット情報を表示
            $this->displayRateLimitInfo();

            return Command::SUCCESS;

        } catch (Exception $e) {
            $syncLog->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            $this->error('同期に失敗しました: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * 差分更新の基準日時を決定
     */
    protected function determineUpdatedSince(): ?string
    {
        // --sinceオプションが指定されている場合
        if ($since = $this->option('since')) {
            return $since;
        }

        // --fullオプションが指定されている場合は全件取得
        if ($this->option('full')) {
            return null;
        }

        // 前回の同期日時を取得
        $lastSync = SyncLog::getLastSync('issues');

        if ($lastSync && $lastSync->last_synced_at) {
            return $lastSync->last_synced_at->format('Y-m-d');
        }

        // 初回実行時は全件取得
        return null;
    }

    /**
     * 課題データをデータベースに保存
     */
    protected function saveIssuesToDatabase(array $issues): void
    {
        $progressBar = $this->output->createProgressBar(count($issues));
        $progressBar->start();

        foreach ($issues as $issue) {
            RawBacklogIssue::updateOrCreate(
                ['backlog_id' => $issue['id']],
                [
                    'issue_key' => $issue['issueKey'],
                    'data' => $issue,
                    'synced_at' => now(),
                    'updated_at_backlog' => Carbon::parse($issue['updated']),
                ]
            );

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
    }

    /**
     * レートリミット情報を表示
     */
    protected function displayRateLimitInfo(): void
    {
        try {
            $rateLimit = $this->backlogApi->getRateLimit();

            $this->newLine();
            $this->info('=== レートリミット情報 ===');
            $this->line("上限: {$rateLimit['rateLimit']['limit']} リクエスト/分");
            $this->line("残り: {$rateLimit['rateLimit']['remaining']} リクエスト");
            $this->line("リセット: " . Carbon::createFromTimestamp($rateLimit['rateLimit']['reset'])->format('Y-m-d H:i:s'));

        } catch (Exception $e) {
            $this->warn('レートリミット情報の取得に失敗しました');
        }
    }
}
