<?php

declare(strict_types=1);

namespace BeeBank\Domain;

enum OperationType: string
{
    case Harvest = 'harvest';
    case Withdrawal = 'withdrawal';
    case Transfer = 'transfer';
}
