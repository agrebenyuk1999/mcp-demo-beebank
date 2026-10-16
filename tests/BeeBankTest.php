<?php

declare(strict_types=1);

namespace BeeBank\Tests;

use BeeBank\Database;
use BeeBank\Domain\BeeBank;
use BeeBank\Domain\BeeBankException;
use PHPUnit\Framework\TestCase;

final class BeeBankTest extends TestCase
{
    private BeeBank $bank;

    protected function setUp(): void
    {
        $this->bank = new BeeBank(Database::connect(':memory:'));
    }

    public function testBalanceIsDerivedFromLedger(): void
    {
        $lypka = $this->bank->openHive('Липка', 'Матильда');
        $hrechka = $this->bank->openHive('Гречка', 'Зоряна');

        $this->bank->harvest($lypka->id, 1000);
        $this->bank->transfer($lypka->id, $hrechka->id, 300, 'за рамку');
        $this->bank->withdraw($hrechka->id, 100);

        self::assertSame(700, $this->bank->hive($lypka->id)->balance);
        self::assertSame(200, $this->bank->hive($hrechka->id)->balance);
    }

    public function testOverdraftIsForbidden(): void
    {
        $lypka = $this->bank->openHive('Липка', 'Матильда');
        $hrechka = $this->bank->openHive('Гречка', 'Зоряна');
        $this->bank->harvest($lypka->id, 100);

        $this->expectException(BeeBankException::class);
        $this->expectExceptionMessage('Недостатньо меду');

        $this->bank->transfer($lypka->id, $hrechka->id, 101, 'забагато');
    }

    public function testFrozenHiveCannotReceive(): void
    {
        $lypka = $this->bank->openHive('Липка', 'Матильда');
        $hrechka = $this->bank->freeze($this->bank->openHive('Гречка', 'Зоряна')->id);
        $this->bank->harvest($lypka->id, 100);

        $this->expectException(BeeBankException::class);
        $this->expectExceptionMessage('заморожено');

        $this->bank->transfer($lypka->id, $hrechka->id, 50, 'в мороз');
    }

    public function testSelfTransferIsForbidden(): void
    {
        $lypka = $this->bank->openHive('Липка', 'Матильда');
        $this->bank->harvest($lypka->id, 100);

        $this->expectException(BeeBankException::class);

        $this->bank->transfer($lypka->id, $lypka->id, 10, 'сам собі');
    }

    public function testNonPositiveAmountIsForbidden(): void
    {
        $lypka = $this->bank->openHive('Липка', 'Матильда');

        $this->expectException(BeeBankException::class);

        $this->bank->harvest($lypka->id, 0);
    }

    public function testHiveWithHistoryCannotBeDeleted(): void
    {
        $lypka = $this->bank->openHive('Липка', 'Матильда');
        $this->bank->harvest($lypka->id, 100);

        $this->expectException(BeeBankException::class);
        $this->expectExceptionMessage('freeze');

        $this->bank->deleteHive($lypka->id);
    }

    public function testHiveWithoutHistoryCanBeDeleted(): void
    {
        $empty = $this->bank->openHive('Порожній', 'Ніхто');

        $this->bank->deleteHive($empty->id);

        self::assertCount(0, $this->bank->hives());
    }
}
