<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class BacklogApiService
{
    protected ?string $spaceUrl;
    protected ?string $apiKey;
    protected int $timeout;
    protected int $retryAfter;
    protected int $maxRetries;
    protected int $paginationCount;

    public function __construct()
    {
        $this->spaceUrl = config('backlog.space_url') ? rtrim(config('backlog.space_url'), '/') : null;
        $this->apiKey = config('backlog.api_key');
        $this->timeout = config('backlog.timeout');
        $this->retryAfter = config('backlog.rate_limit.retry_after');
        $this->maxRetries = config('backlog.rate_limit.max_retries');
        $this->paginationCount = config('backlog.pagination.count');
    }

    /**
     * API認証情報が設定されているかチェック
     */
    protected function ensureConfigured(): void
    {
        if (empty($this->spaceUrl) || empty($this->apiKey)) {
            throw new Exception('Backlog API credentials are not configured. Please set BACKLOG_SPACE_URL and BACKLOG_API_KEY in .env file.');
        }
    }

    /**
     * 課題一覧を取得（差分更新対応）
     *
     * @param string|null $updatedSince 更新日時フィルタ (yyyy-MM-dd形式)
     * @return array
     */
    public function getIssues(?string $updatedSince = null): array
    {
        $this->ensureConfigured();

        $allIssues = [];
        $offset = 0;

        do {
            $params = [
                'apiKey' => $this->apiKey,
                'count' => $this->paginationCount,
                'offset' => $offset,
            ];

            if ($updatedSince) {
                $params['updatedSince'] = $updatedSince;
            }

            $issues = $this->makeRequest('GET', '/api/v2/issues', $params);

            if (empty($issues)) {
                break;
            }

            $allIssues = array_merge($allIssues, $issues);
            $offset += $this->paginationCount;

            // レートリミット対策: 1秒待機
            sleep(1);

        } while (count($issues) === $this->paginationCount);

        return $allIssues;
    }

    /**
     * 課題の詳細を取得
     *
     * @param string $issueIdOrKey 課題IDまたはキー
     * @return array
     */
    public function getIssue(string $issueIdOrKey): array
    {
        return $this->makeRequest('GET', "/api/v2/issues/{$issueIdOrKey}", [
            'apiKey' => $this->apiKey,
        ]);
    }

    /**
     * レートリミット情報を取得
     *
     * @return array
     */
    public function getRateLimit(): array
    {
        return $this->makeRequest('GET', '/api/v2/rateLimit', [
            'apiKey' => $this->apiKey,
        ]);
    }

    /**
     * HTTP リクエストを実行（レートリミット対応）
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param int $retryCount
     * @return array
     * @throws Exception
     */
    protected function makeRequest(string $method, string $endpoint, array $params = [], int $retryCount = 0): array
    {
        $url = $this->spaceUrl . $endpoint;

        try {
            $response = Http::timeout($this->timeout)
                ->$method($url, $params);

            // レートリミット情報をログ出力
            $this->logRateLimitHeaders($response->headers());

            if ($response->status() === 429) {
                return $this->handleRateLimitError($method, $endpoint, $params, $retryCount);
            }

            if ($response->failed()) {
                throw new Exception("Backlog API request failed: {$response->status()} - {$response->body()}");
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('Backlog API request error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 429エラー（レートリミット超過）の処理
     *
     * @param string $method
     * @param string $endpoint
     * @param array $params
     * @param int $retryCount
     * @return array
     * @throws Exception
     */
    protected function handleRateLimitError(string $method, string $endpoint, array $params, int $retryCount): array
    {
        if ($retryCount >= $this->maxRetries) {
            throw new Exception("Rate limit exceeded. Max retries ({$this->maxRetries}) reached.");
        }

        $waitTime = $this->retryAfter;
        Log::warning("Rate limit exceeded. Retrying after {$waitTime} seconds... (Attempt: " . ($retryCount + 1) . "/{$this->maxRetries})");

        sleep($waitTime);

        return $this->makeRequest($method, $endpoint, $params, $retryCount + 1);
    }

    /**
     * レートリミットヘッダーをログ出力
     *
     * @param array $headers
     * @return void
     */
    protected function logRateLimitHeaders(array $headers): void
    {
        $rateLimitInfo = [
            'limit' => $headers['X-RateLimit-Limit'][0] ?? null,
            'remaining' => $headers['X-RateLimit-Remaining'][0] ?? null,
            'reset' => $headers['X-RateLimit-Reset'][0] ?? null,
        ];

        if ($rateLimitInfo['limit']) {
            Log::debug('Backlog API Rate Limit', $rateLimitInfo);
        }
    }

    /**
     * プロジェクト一覧を取得
     *
     * @return array
     */
    public function getProjects(): array
    {
        $this->ensureConfigured();

        return $this->makeRequest('GET', '/api/v2/projects', [
            'apiKey' => $this->apiKey,
        ]);
    }

    /**
     * 課題タイプ一覧を取得
     *
     * @param int $projectId
     * @return array
     */
    public function getIssueTypes(int $projectId): array
    {
        return $this->makeRequest('GET', "/api/v2/projects/{$projectId}/issueTypes", [
            'apiKey' => $this->apiKey,
        ]);
    }

    /**
     * 優先度一覧を取得
     *
     * @return array
     */
    public function getPriorities(): array
    {
        return $this->makeRequest('GET', '/api/v2/priorities', [
            'apiKey' => $this->apiKey,
        ]);
    }

    /**
     * 課題を作成
     *
     * @param array $data
     * @return array
     */
    public function createIssue(array $data): array
    {
        $this->ensureConfigured();

        // APIキーはクエリパラメータとして送る
        $url = $this->spaceUrl . '/api/v2/issues?apiKey=' . $this->apiKey;

        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post($url, $data);

            $this->logRateLimitHeaders($response->headers());

            if ($response->status() === 429) {
                return $this->handleRateLimitError('POST', '/api/v2/issues', $params, 0);
            }

            if ($response->failed()) {
                throw new Exception("Failed to create issue: {$response->status()} - {$response->body()}");
            }

            return $response->json();

        } catch (Exception $e) {
            Log::error('Failed to create Backlog issue', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }
}
