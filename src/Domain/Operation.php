<?php

declare(strict_types=1);

namespace BeeBank\Domain;

final readonly class Operation
{
    public function __construct(
        public int $id,
        public OperationType $type,
        public ?int $fromHiveId,
        public ?int $toHiveId,
        public int $grams,
        public ?string $memo,
        public \DateTimeImmutable $createdAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'from_hive_id' => $this->fromHiveId,
            'to_hive_id' => $this->toHiveId,
            'grams' => $this->grams,
            'amount' => Honey::format($this->grams),
            'memo' => $this->memo,
            'created_at' => $this->createdAt->format('Y-m-d H:i'),
        ];
    }
}
