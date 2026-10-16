<?php

declare(strict_types=1);

namespace BeeBank\Mcp;

use BeeBank\Domain\BeeBankException;
use Mcp\Exception\ToolCallException;

trait TranslatesDomainErrors
{
    /**
     * Доменна помилка → isError: true, щоб модель побачила причину і могла виправитись.
     *
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private function guard(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (BeeBankException $e) {
            throw new ToolCallException($e->getMessage(), previous: $e);
        }
    }
}
