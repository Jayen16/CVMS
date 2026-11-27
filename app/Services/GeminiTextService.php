<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiTextService
{
    /** @param array<string, mixed> $facts */
    public function generate(string $instructions, array $facts): string
    {
        $key = (string) config('services.gemini.key');
        if (trim($key) === '') {
            throw new RuntimeException('Gemini AI summaries are not configured. Please contact your system administrator.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $key])
                ->connectTimeout(10)->timeout(45)
                ->post('https://generativelanguage.googleapis.com/v1beta/models/'
                    .rawurlencode((string) config('services.gemini.model', 'gemini-flash-lite-latest')).':generateContent', [
                        'systemInstruction' => ['parts' => [['text' => $instructions]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($facts, JSON_THROW_ON_ERROR)]]]],
                        'generationConfig' => ['temperature' => 0.2, 'maxOutputTokens' => 2048],
                    ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Gemini could not be reached. Check your internet connection and try again.');
        }

        if (! $response->successful()) {
            throw new RuntimeException(match ($response->status()) {
                400, 401, 403 => 'Gemini could not accept this request. Please contact your system administrator.',
                404 => 'The configured Gemini model is unavailable. Please contact your system administrator.',
                429 => 'Gemini\'s usage limit was reached. Please try again later.',
                default => 'Gemini could not generate a summary right now. Please try again in a moment.',
            });
        }

        if ($response->json('candidates.0.finishReason') !== 'STOP') {
            throw new RuntimeException('Gemini did not return a complete summary. Please try again.');
        }

        $parts = $response->json('candidates.0.content.parts', []);
        $texts = [];
        if (is_array($parts)) {
            foreach ($parts as $part) {
                if (is_array($part) && empty($part['thought']) && is_string($part['text'] ?? null)) {
                    $texts[] = $part['text'];
                }
            }
        }
        $summary = implode("\n", $texts);

        if (trim($summary) === '') {
            throw new RuntimeException('Gemini returned an empty summary. Please try again.');
        }

        return trim($summary);
    }
}
