<?php
declare(strict_types=1);
namespace TermTrail\services;

use TermTrail\helpers\Env;

/** Groq REST client over cURL (compatible with OpenAI format). */
final class GeminiClient
{
    public static function apiKey(): string { return (string)Env::get('GROQ_API_KEY', Env::get('GEMINI_API_KEY', '')); }
    public static function configured(): bool { return self::apiKey() !== ''; }
    public static function embeddingKey(): string { return (string)Env::get('EMBEDDING_API_KEY', self::apiKey()); }
    public static function embeddingsConfigured(): bool { return self::embeddingKey() !== ''; }
    public static function model(): string { return Env::get('GROQ_MODEL') ?: 'llama-3.3-70b-versatile'; }
    public static function embeddingModel(): string { return Env::get('GEMINI_EMBEDDING_MODEL') ?: 'text-embedding-004'; }
    private static function base(): string { return rtrim(Env::get('GROQ_API_BASE') ?: 'https://api.groq.com/openai/v1', '/'); }

    /** @throws AiException */
    public static function generate(string $system, string $user, int $maxTokens = 4096): string
    {
        if (!self::configured()) { throw new AiException('The AI service is not configured.'); }
        
        $body = [
            'model' => self::model(),
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => mb_substr($user, 0, 80000)]
            ],
            'temperature' => 0.1,
            'max_tokens' => $maxTokens
        ];

        $data = self::post('/chat/completions', $body, self::apiKey());
        $text = $data['choices'][0]['message']['content'] ?? '';
        if (trim($text) === '') { throw new AiException('The AI provider returned an empty response.'); }
        return $text;
    }

    /** @param string[] $texts @return array[] one float vector per text @throws AiException */
    public static function embed(array $texts): array
    {
        throw new AiException('Embeddings are not supported on Groq endpoint directly.');
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
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $key],
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
                $status === 404 => 'The configured AI model was not found. Check GROQ_MODEL in .env.',
                default => 'The AI provider rejected the request (' . $detail . ').',
            });
        }
        throw $last ?? new AiException('The AI request failed.');
    }
}