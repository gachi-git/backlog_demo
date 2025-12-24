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

            $faker = Faker::create('ja_JP');
            $progressBar = $this->output->createProgressBar($count);
            $progressBar->start();

            $successCount = 0;
            $failCount = 0;

            for ($i = 0; $i < $count; $i++) {
                try {
                    $issueData = [
                        'projectId' => $project['id'],
                        'summary' => $faker->sentence(6),
                        'description' => $faker->paragraph(3),
                        'issueTypeId' => $issueTypes[array_rand($issueTypes)]['id'],
                        'priorityId' => $priorities[array_rand($priorities)]['id'],
                    ];

                    // ランダムで期限日を設定
                    if (rand(0, 1)) {
                        $issueData['dueDate'] = $faker->dateTimeBetween('now', '+3 months')->format('Y-m-d');
                    }

                    // ランダムで見積時間を設定
                    if (rand(0, 1)) {
                        $issueData['estimatedHours'] = rand(1, 40);
                    }

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
