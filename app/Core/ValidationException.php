<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Levée par Controller::validate() ; transformée par App en réponse 422 (JSON) ou en redirection.
 */
class ValidationException extends \RuntimeException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Les données envoyées sont invalides.', 422);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }
}
