<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\helpers\Env;

/** Minimal Gemini REST client over cURL. API keys stay on the server and are sent in a header, never in the URL. */
final class GeminiClient
{
    public static function apiKey(): string { return (string)Env::get('GEMINI_API_KEY', ''); }
    public static function configured(): bool { return self::apiKey() !== ''; }
    public static function embeddingKey(): string { return (string)Env::get('EMBEDDING_API_KEY', ''); }
    public static function embeddingsConfigured(): bool { return self::embeddingKey() !== ''; }
    public static function model(): string { return Env::get('GEMINI_MODEL') ?: 'gemini-2.0-flash'; }
    public static function embeddingModel(): string { return Env::get('GEMINI_EMBEDDING_MODEL') ?: 'text-embedding-004'; }
    private static function base(): string { return rtrim(Env::get('GEMINI_API_BASE') ?: 'https://generativelanguage.googleapis.com/v1beta', '/'); }

    /** @throws AiException */
    public static function generate(string $system, string $user, int $maxTokens = 4096): string
    {
        if (!self::configured()) { throw new AiException('The AI service is not configured.'); }
        $body = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => mb_substr($user, 0, 80000)]]]],
            'generationConfig' => ['temperature' => 0.1, 'maxOutputTokens' => $maxTokens, 'responseMimeType' => 'application/json'],
        ];
        $data = self::post('/models/' . rawurlencode(self::model()) . ':generateContent', $body, self::apiKey());
        if (!empty($data['promptFeedback']['blockReason'])) { throw new AiException('The AI provider declined to process this text.'); }
        $parts = $data['candidates'][0]['content']['parts'] ?? null;
        if (!is_array($parts)) { throw new AiException('The AI provider returned an empty response.'); }
        $text = '';
        foreach ($parts as $p) { if (isset($p['text']) && is_string($p['text'])) { $text .= $p['text']; } }
        if (trim($text) === '') { throw new AiException('The AI provider returned an empty response.'); }
        return $text;
    }

    /** @param string[] $texts @return array[] one float vector per text @throws AiException */
    public static function embed(array $texts): array
    {
        if (!self::embeddingsConfigured()) { throw new AiException('Embeddings are not configured.'); }
        $model = self::embeddingModel();
        $reqs = array_map(fn($t) => ['model' => 'models/' . $model, 'content' => ['parts' => [['text' => mb_substr((string)$t, 0, 8000)]]]], array_values($texts));
        $data = self::post('/models/' . rawurlencode($model) . ':batchEmbedContents', ['requests' => $reqs], self::embeddingKey());
        $out = [];
        foreach (($data['embeddings'] ?? []) as $e) { $out[] = $e['values'] ?? null; }
        if (count($out) !== count($texts)) { throw new AiException('The embedding provider returned an unexpected response.'); }
        return $out;
    }

    private static function post(string $path, array $body, string $key): array
    {
        $url = self::base() . $path;
        $sleepMs = (int)(Env::get('AI_RETRY_SLEEP_MS', '1000'));
        $last = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($attempt > 0) { usleep($sleepMs * 1000 * $attempt); }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 60,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-goog-api-key: ' . $key],
                CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
            ]);
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($raw === false || $errno) {
                error_log('AI request transport error (curl ' . $errno . ')');
                $last = new AiException($errno === 28 ? 'The AI provider timed out. Please retry.' : 'Could not reach the AI provider. Check your internet connection.');
                continue;
            }
            if ($status >= 200 && $status < 300) {
                $data = json_decode((string)$raw, true);
                if (!is_array($data)) { throw new AiException('The AI provider returned an unreadable response.'); }
                return $data;
            }
            $detail = json_decode((string)$raw, true)['error']['message'] ?? '';
            error_log('AI request failed: HTTP ' . $status . ' ' . mb_substr((string)$detail, 0, 200));
            if (in_array($status, [429, 500, 502, 503, 504], true)) {
                $last = new AiException($status === 429 ? 'The AI rate limit was reached. Please retry in a minute.' : 'The AI provider is temporarily unavailable. Please retry.');
                continue;
            }
            throw new AiException(match (true) {
                in_array($status, [401, 403], true) => 'The AI API key is invalid or not permitted.',
                $status === 404 => 'The configured AI model was not found. Check GEMINI_MODEL in .env.',
                default => 'The AI provider rejected the request.',
            });
        }
        throw $last ?? new AiException('The AI request failed.');
    }
}
