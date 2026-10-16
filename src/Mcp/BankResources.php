<?php

declare(strict_types=1);

namespace BeeBank\Mcp;

use BeeBank\Domain\Rules;
use Mcp\Capability\Attribute\McpResource;

final class BankResources
{

    #[McpResource(uri: 'beebank://rules', name: 'rules', title: 'Правила BeeBank', mimeType: 'text/markdown')]
    public function rules(): string
    {
        return "# Правила BeeBank\n\n".implode("\n", array_map(static fn (string $r): string => '- '.$r, Rules::ALL))."\n";
    }
}
