<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use RuntimeException;

trait InteractsWithAiNarratives
{
    #[Locked]
    public ?string $aiNarrative = null;

    #[Locked]
    public ?string $aiNarrativeAt = null;

    #[Locked]
    public ?string $aiNarrativeError = null;

    protected function clearAiNarrative(): void
    {
        $this->reset('aiNarrative', 'aiNarrativeAt', 'aiNarrativeError');
    }

    /** @param callable(): string $generate */
    protected function generateAiNarrative(callable $generate): void
    {
        $this->clearAiNarrative();
        $limitKey = 'gemini-planning:'.auth()->id();
        if (RateLimiter::tooManyAttempts($limitKey, 3)) {
            $this->aiNarrativeError = 'Please wait a minute before generating another summary.';

            return;
        }

        RateLimiter::hit($limitKey, 60);
        try {
            $this->aiNarrative = $generate();
            $this->aiNarrativeAt = now()->timezone('Asia/Manila')->format('M d, Y g:i A').' (Philippine time)';
        } catch (RuntimeException $exception) {
            $this->aiNarrativeError = $exception->getMessage();
        }
    }
}
