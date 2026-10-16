# 🐝 BeeBank — фінтех для пасіки

Демо-проєкт до knowledge sharing про **MCP (Model Context Protocol)**: маленький банк, клієнти якого — вулики,
а валюта — мед у грамах. Поверх доменного шару — MCP-сервер на офіційному PHP SDK
([`mcp/sdk`](https://github.com/modelcontextprotocol/php-sdk)), який підключається до Claude Code / Cursor / будь-якого MCP-клієнта.

Глосарій домену — [CONTEXT.md](CONTEXT.md).

## Запуск

Потрібні лише Docker і (для Inspector) Node.js.

```bash
make up        # docker compose up -d --build
make install   # composer install у контейнері
make seed      # демо-дані: 6 вуликів, 18 операцій
```

UI з балансами й ledger (оновлюється наживо): http://localhost:8080

## Підключення MCP

MCP-сервер працює всередині того ж контейнера, що й UI, і слухає **`http://localhost:8080/mcp`** (транспорт Streamable HTTP).
Клієнт нічого не запускає сам: він просто шле HTTP-запити на цей URL з токеном `Authorization: Bearer dev-secret`
(задається в `compose.yaml` через `BEEBANK_MCP_TOKEN`).

### Claude Code

Як будь-який реальний MCP-сервер — один раз зареєструвати, далі він доступний з будь-якої папки:

```bash
claude mcp add --transport http --scope user beebank http://localhost:8080/mcp \
  --header "Authorization: Bearer dev-secret"

claude mcp get beebank     # Status: ✔ Connected
claude                     # у сесії: /mcp → beebank
```

Прибрати: `claude mcp remove beebank -s user`.

### Cursor / Claude Desktop / інші клієнти

```json
{
  "mcpServers": {
    "beebank": {
      "type": "http",
      "url": "http://localhost:8080/mcp",
      "headers": { "Authorization": "Bearer dev-secret" }
    }
  }
}
```

### Живий журнал запитів

```bash
make watch                # нові запити в реальному часі
make watch ARGS=--all     # з початку журналу
make watch ARGS=--full    # без скорочень і з _meta
```

Усе, що приходить на `/mcp`, пишеться в `var/mcp-requests.log` (JSON Lines): який клієнт, яка ревізія протоколу, який tool з якими аргументами, що відповів сервер і скільки це тривало.


### MCP Inspector

```bash
make inspector
```

В UI: Transport **Streamable HTTP**, URL `http://localhost:8080/mcp`, у Authentication — Bearer `dev-secret`.

## Що всередині

| MCP-примітив | Що | Хто ініціює |
|---|---|---|
| **Tools** | `list_hives`, `get_hive`, `open_hive`, `update_hive`, `freeze_hive`, `unfreeze_hive`, `delete_hive`, `record_harvest`, `withdraw_honey`, `transfer_honey`, `list_operations` | модель |
| **Resource** | `beebank://rules` — правила банку | застосунок / користувач |
| **Prompt** | `daily_review` — щоденний огляд (у Claude Code: `/mcp__beebank__daily_review`) | користувач |

```
src/
├── Domain/        # BeeBank, Hive, Operation, правила — нічого не знає про MCP
├── Mcp/           # тонкий адаптер: атрибути #[McpTool] / #[McpResource] / #[McpPrompt]
└── Database.php   # SQLite
public/mcp.php     # MCP-сервер: POST /mcp
public/router.php  # /mcp → mcp.php, решта → UI
public/index.php   # UI
bin/seed           # скидання й демо-дані
```

MCP-шар — це просто ще один адаптер над доменом, як контролер чи консольна команда.

## Тести

```bash
make test
```
