<?php

declare(strict_types=1);

namespace App\Services;

/** Transition de statut interdite (§4.2.3). */
final class WorkflowException extends \LogicException
{
    public function __construct(public readonly string $from, public readonly string $to)
    {
        parent::__construct(sprintf('Transition interdite : %s → %s', $from, $to));
    }
}
