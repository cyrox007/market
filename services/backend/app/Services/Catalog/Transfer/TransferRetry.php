<?php

namespace App\Services\Catalog\Transfer;

class TransferRetry extends \RuntimeException
{
    public function __construct(public readonly int $delay, public readonly bool $countAttempt = true)
    {
        parent::__construct('Источник временно недоступен. Повтор после паузы.');
    }
}
