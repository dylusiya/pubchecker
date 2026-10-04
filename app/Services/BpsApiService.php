<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BpsApiService
 * Wrapper untuk Web API BPS v1 — semua endpoint path-based.
 * Docs: https://webapi.bps.go.id/documentation/
 *
 * Format URL:
 * - List   : /v1/api/list/model/publication/lang/ind/domain/{d}/key/{k}/[page/{p}/][year/{y}/][month/{m}/][keyword/{kw}/]
 * - Detail : /v1/api/view/domain/{d}/model/publication/lang/ind/id/{id}/key/{k}/
 * - Domain : /v1/api/domain/type/{type}/key/{k}/
 */
class BpsApiService
{
    private string $baseUrl = 'https://webapi.bps.go.id/v1';
    private string $apiKey;
    private string $defaultDomain;

    public function __construct()
    {
        $this->apiKey        = config('services.bps_api.key',    '');
        $this->defaultDomain = config('services.bps_api.domain', '6300');
    }

    // ─────────────────────────────────────────────────────────
    // PUBLIC METHODS
    // ─────────────────────────────────────────────────────────

    /**
     * Daftar domain / satker BPS.
     * /v1/api/domain/type/{type}/key/{key}/
     */
    public function getDomains(string $type = 'all', ?string $prov = null): array
    {
        $url = "{$this->baseUrl}/api/domain/type/{$type}";
        if ($prov) $url .= "/prov/{$prov}";
        $url .= "/key/{$this->apiKey}/";

        return $this->fetchUrl($url);
    }

    /**
     * List publikasi dengan filter opsional.
     * /v1/api/list/model/publication/lang/ind/domain/{domain}/key/{key}/
     * Filter tambahan (path): /page/{p}/ /year/{y}/ /month/{m}/ /keyword/{kw}/
     *
     * @return array ['pagination' => [...], 'items' => [...], 'status' => 'OK'|...]
     */
    public function getPublications(
        string  $domain,
        int     $page    = 1,
        ?int    $month   = null,
        ?int    $year    = null,
        ?string $keyword = null
    ): array {
        $url  = "{$this->baseUrl}/api/list/model/publication/lang/ind";
        $url .= "/domain/{$domain}";
        $url .= "/key/{$this->apiKey}/";

        if ($page > 1) $url .= "page/{$page}/";
        if ($year)     $url .= "year/{$year}/";
        if ($month)    $url .= "month/" . str_pad($month, 2, '0', STR_PAD_LEFT) . "/";
        if ($keyword)  $url .= "keyword/" . urlencode($keyword) . "/";

        $raw = $this->fetchUrl($url);
        return $this->parseList($raw);
    }

    /**
     * Detail satu publikasi.
     * /v1/api/view/domain/{domain}/model/publication/lang/ind/id/{id}/key/{key}/
     *
     * @return array raw response BPS
     */
    public function getPublicationDetail(string $domain, string $pubId): array
    {
        $url = "{$this->baseUrl}/api/view/domain/{$domain}/model/publication/lang/ind/id/{$pubId}/key/{$this->apiKey}/";

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::warning("BPS view HTTP {$response->status()} pub_id={$pubId} domain={$domain}");
                return ['status' => 'ERROR', 'data' => []];
            }

            $raw = $response->json();

            if (($raw['data-availability'] ?? '') !== 'available' || empty($raw['data'])) {
                return ['status' => 'ERROR', 'data' => []];
            }

            return $raw;

        } catch (\Throwable $e) {
            Log::error('BPS getPublicationDetail: ' . $e->getMessage());
            return ['status' => 'ERROR', 'data' => []];
        }
    }

    /**
     * Download PDF publikasi ke file temporary.
     * Caller wajib unlink($path) setelah selesai dipakai.
     *
     * @throws \RuntimeException jika HTTP gagal
     */
    public function downloadPdf(string $pdfUrl): string
    {
        $response = Http::timeout(120)
            ->withHeaders(['User-Agent' => 'PubChecker/1.0'])
            ->get($pdfUrl);

        if (!$response->successful()) {
            throw new \RuntimeException("Gagal download PDF: HTTP " . $response->status());
        }

        $tmpPath = tempnam(sys_get_temp_dir(), 'bps_pub_') . '.pdf';
        file_put_contents($tmpPath, $response->body());
        return $tmpPath;
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE HELPERS
    // ─────────────────────────────────────────────────────────

    /**
     * GET ke URL BPS, return array atau [] jika gagal.
     */
    private function fetchUrl(string $url): array
    {
        try {
            Log::debug('BPS API -> ' . $url);
            $response = Http::timeout(15)->get($url);

            if (!$response->successful()) {
                Log::warning('BPS API non-200: ' . $response->status() . ' | ' . $url);
                return [];
            }

            return $response->json() ?? [];

        } catch (\Throwable $e) {
            Log::error('BPS API error: ' . $e->getMessage() . ' | ' . $url);
            return [];
        }
    }

    /**
     * Normalkan struktur response list BPS.
     * Response: { "status":"OK", "data-availability":"available",
     *   "data": [ {pagination_obj}, [{item1},{item2},...] ] }
     */
    private function parseList(array $raw): array
    {
        if (empty($raw['data']) || !is_array($raw['data'])) {
            return [
                'pagination' => [],
                'items'      => [],
                'status'     => $raw['status'] ?? 'error',
            ];
        }

        $pagination = $raw['data'][0] ?? [];
        $items      = isset($raw['data'][1]) && is_array($raw['data'][1])
                      ? array_values($raw['data'][1])
                      : [];

        return [
            'pagination' => $pagination,
            'items'      => $items,
            'status'     => $raw['status'] ?? 'UNKNOWN',
        ];
    }
}