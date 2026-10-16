<?php

declare(strict_types=1);

namespace BeeBank\Mcp;

use BeeBank\Domain\BeeBank;
use BeeBank\Domain\Operation;
use Mcp\Capability\Attribute\McpTool;
use Mcp\Capability\Attribute\Schema;
use Mcp\Exception\ToolCallException;
use Mcp\Schema\ToolAnnotations;

final class LedgerTools
{
    use TranslatesDomainErrors;

    public function __construct(private readonly BeeBank $bank)
    {
    }

    /**
     * Зарахувати зібраний мед (збір нектару) на баланс вулика.
     *
     * @param int         $hiveId ID вулика
     * @param int         $grams  кількість меду в грамах
     * @param string|null $memo   коментар
     */
    #[McpTool(name: 'record_harvest')]
    public function harvest(int $hiveId, #[Schema(minimum: 1)] int $grams, ?string $memo = null): array
    {
        return $this->guard(fn (): array => $this->bank->harvest($hiveId, $grams, $memo)->toArray());
    }

    /**
     * Пасічник забирає мед з вулика.
     *
     * @param int         $hiveId ID вулика
     * @param int         $grams  кількість меду в грамах
     * @param string|null $memo   коментар
     */
    #[McpTool(name: 'withdraw_honey')]
    public function withdraw(int $hiveId, #[Schema(minimum: 1)] int $grams, ?string $memo = null): array
    {
        return $this->guard(fn (): array => $this->bank->withdraw($hiveId, $grams, $memo)->toArray());
    }

    /**
     * Переказати мед з одного вулика на інший.
     *
     * @param int    $fromHiveId ID вулика-відправника
     * @param int    $toHiveId   ID вулика-отримувача
     * @param int    $grams      кількість меду в грамах
     * @param string $memo       призначення переказу (обов'язково)
     */
    #[McpTool(name: 'transfer_honey', annotations: new ToolAnnotations(destructiveHint: true))]
    public function transfer(int $fromHiveId, int $toHiveId, #[Schema(minimum: 1)] int $grams, string $memo): array
    {
        return $this->guard(fn (): array => $this->bank->transfer($fromHiveId, $toHiveId, $grams, $memo)->toArray());
    }

    /**
     * Журнал операцій (ledger), від найновіших.
     *
     * @param int|null    $hiveId лише операції цього вулика
     * @param string|null $date   лише за цей день, формат YYYY-MM-DD ("today" — сьогодні)
     * @param int         $limit  максимум записів
     */
    #[McpTool(name: 'list_operations', annotations: new ToolAnnotations(readOnlyHint: true))]
    public function list(?int $hiveId = null, ?string $date = null, int $limit = 50): array
    {
        try {
            $day = null === $date ? null : new \DateTimeImmutable($date);
        } catch (\Exception) {
            throw new ToolCallException("Невірна дата: {$date}. Очікується YYYY-MM-DD або \"today\".");
        }

        return ['operations' => array_map(
            static fn (Operation $op): array => $op->toArray(),
            $this->bank->operations($hiveId, $day, $limit),
        )];
    }
}
