<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Fichier audio refusé : $reason est une clé de lang/{locale}/questions.php (audio.errors.*).
 */
final class AudioException extends \RuntimeException
{
    /** @param array<string, scalar> $params */
    public function __construct(public readonly string $reason, public readonly array $params = [])
    {
        parent::__construct('Audio refusé : ' . $reason);
    }

    public function userMessage(): string
    {
        return __('questions.audio.errors.' . $this->reason, $this->params);
    }
}
