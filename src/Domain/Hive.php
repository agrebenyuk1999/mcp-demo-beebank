<?php

declare(strict_types=1);

namespace BeeBank\Domain;

final readonly class Hive
{
    public function __construct(
        public int $id,
        public string $name,
        public string $queen,
        public HiveStatus $status,
        public int $balance,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'queen' => $this->queen,
            'status' => $this->status->value,
            'balance_grams' => $this->balance,
            'balance' => Honey::format($this->balance),
        ];
    }
}
