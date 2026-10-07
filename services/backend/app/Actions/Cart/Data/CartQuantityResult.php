<?php

namespace App\Actions\Cart\Data;

use Vanilo\Cart\Contracts\CartItem;

/**
 * Итог изменения позиции: applied — сколько добавлено или установлено, previous — сколько было до изменения.
 */
final class CartQuantityResult
{
    public function __construct(
        public readonly CartItem $item,
        public readonly int $requested,
        public readonly int $applied,
        public readonly int $previous,
        public readonly ?int $stock,
        public readonly ?CartLimitReason $reason,
    ) {
    }

    public function wasAdjusted(): bool
    {
        return $this->applied < $this->requested;
    }

    public function reasonMessage(): string
    {
        return $this->reason ? ' (' . $this->reason->message($this->requested, $this->stock) . ')' : '';
    }
}
