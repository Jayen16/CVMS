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
            throw new RuntimeException('AI summaries are not configured. Please contact your system administrator.');
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
            throw new RuntimeException('The AI service could not be reached. Check the internet connection and try again.');
        }

        if (! $response->successful()) {
            throw new RuntimeException(match ($response->status()) {
                400, 401, 403 => 'The AI service could not authorize this request. Please contact your system administrator.',
                404 => 'The AI service is currently unavailable. Please contact your system administrator.',
                429 => 'The AI service usage limit was reached. Please try again later.',
                default => 'The AI service could not generate a summary. Please try again later.',
            });
        }

        if ($response->json('candidates.0.finishReason') !== 'STOP') {
            throw new RuntimeException('The AI service did not return a complete summary. Please try again.');
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
            throw new RuntimeException('The AI service returned an empty summary. Please try again.');
        }

        return trim($summary);
    }
}
