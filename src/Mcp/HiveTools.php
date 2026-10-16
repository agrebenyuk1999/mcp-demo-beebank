<?php

declare(strict_types=1);

namespace BeeBank\Mcp;

use BeeBank\Domain\BeeBank;
use BeeBank\Domain\Hive;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;

final class HiveTools
{
    use TranslatesDomainErrors;

    public function __construct(private readonly BeeBank $bank)
    {
    }

    /**
     * Список усіх вуликів BeeBank з балансами.
     */
    #[McpTool(name: 'list_hives', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(): array
    {
        return ['hives' => array_map(static fn (Hive $h): array => $h->toArray(), $this->bank->hives())];
    }

    /**
     * Деталі вулика: баланс і останні операції.
     *
     * @param int $hiveId ID вулика
     */
    #[McpTool(name: 'get_hive', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function get(int $hiveId): array
    {
        return $this->guard(fn (): array => [
            ...$this->bank->hive($hiveId)->toArray(),
            'recent_operations' => array_map(
                static fn ($op): array => $op->toArray(),
                $this->bank->operations(hiveId: $hiveId, limit: 10),
            ),
        ]);
    }

    /**
     * Відкрити новий вулик (клієнта BeeBank) з нульовим балансом.
     *
     * @param string $name  назва вулика
     * @param string $queen ім'я матки
     */
    #[McpTool(name: 'open_hive')]
    public function open(string $name, string $queen): array
    {
        return $this->guard(fn (): array => $this->bank->openHive($name, $queen)->toArray());
    }

    /**
     * Змінити назву вулика та/або ім'я матки.
     *
     * @param int         $hiveId ID вулика
     * @param string|null $name   нова назва
     * @param string|null $queen  нове ім'я матки
     */
    #[McpTool(name: 'update_hive', annotations: new ToolAnnotations(idempotentHint: true))]
    public function update(int $hiveId, ?string $name = null, ?string $queen = null): array
    {
        return $this->guard(fn (): array => $this->bank->updateHive($hiveId, $name, $queen)->toArray());
    }

    /**
     * Заморозити вулик: він не зможе ні відправляти, ні отримувати мед.
     *
     * @param int $hiveId ID вулика
     */
    #[McpTool(name: 'freeze_hive', annotations: new ToolAnnotations(idempotentHint: true))]
    public function freeze(int $hiveId): array
    {
        return $this->guard(fn (): array => $this->bank->freeze($hiveId)->toArray());
    }

    /**
     * Розморозити вулик.
     *
     * @param int $hiveId ID вулика
     */
    #[McpTool(name: 'unfreeze_hive', annotations: new ToolAnnotations(idempotentHint: true))]
    public function unfreeze(int $hiveId): array
    {
        return $this->guard(fn (): array => $this->bank->unfreeze($hiveId)->toArray());
    }

    /**
     * Видалити вулик. Дозволено лише для вулика без жодної операції; інакше використовуй freeze_hive.
     *
     * @param int $hiveId ID вулика
     */
    #[McpTool(name: 'delete_hive', annotations: new ToolAnnotations(destructiveHint: true))]
    public function delete(int $hiveId): array
    {
        return $this->guard(function () use ($hiveId): array {
            $this->bank->deleteHive($hiveId);

            return ['deleted' => $hiveId];
        });
    }
}
