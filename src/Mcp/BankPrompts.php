<?php

declare(strict_types=1);

namespace BeeBank\Mcp;

use Mcp\Capability\Attribute\McpPrompt;

final class BankPrompts
{
    #[McpPrompt(name: 'daily_review', title: 'Щоденний огляд BeeBank')]
    public function dailyReview(): array
    {
        return [[
            'role' => 'user',
            'content' => <<<'TXT'
                Ти — операціоніст BeeBank. Зроби щоденний огляд:
                1. Отримай усі операції за сьогодні (list_operations з date="today") і список вуликів.
                2. Коротко підсумуй день: скільки меду зібрано, переказано, знято.
                3. Дай фінальний звіт таблицею: вулик, баланс, що змінилось за день.
                TXT,
        ]];
    }
}
