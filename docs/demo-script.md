# Демо BeeBank: повний шлях (~30 хв)

## За день до виступу

- [ ] Прогнати весь сценарій нижче від початку до кінця з таймером
- [ ] Прибрати за собою після репетиції:
  - `claude mcp remove beebank -s user` — на демо підключатимемо з нуля
  - `patch -R -p1 < docs/pay_interest.patch` — відкотити live coding

## За 15 хв до виступу

- [ ] `make up && make install && make seed`
- [ ] Вікна: зліва браузер з http://localhost:8080, справа термінал. Слайди — окремим вікном
- [ ] Збільшити шрифт терміналу й браузера (проєктор!)
- [ ] Перевірити, що сервер живий: `curl -i -X POST localhost:8080/mcp` → `401` (просить токен, отже працює)
- [ ] `claude mcp list` — `beebank` у списку **немає** (якщо є: `claude mcp remove beebank -s user`)
- [ ] Термінал відкритий у **будь-якій папці, не в репо** (наприклад, `~`) — так, як у реальному житті
- [ ] Ще одна вкладка терміналу, у репо: `make watch` — живий журнал запитів до `/mcp`. Поставити її так, щоб було видно поряд з Claude Code
- [ ] Insomnia: імпортувати `docs/insomnia-beebank-mcp.json` (один раз), відкрити колекцію **BeeBank MCP**, збільшити масштаб (`Cmd +`)
- [ ] `make seed` наостанок — чистий стан

---

## Крок 0. Слайди до «Демо: BeeBank» (~10 хв)

Проблема N×M → стандарт → host / client / server → три примітиви → JSON-RPC → шлях одного запиту → tool на PHP.

## Крок 1. Знайомство з BeeBank (~30 с)

Показати UI: вулики, баланси в кг, ledger. Сказати одним реченням: «Звичайний PHP-застосунок у Docker. Поруч з UI він слухає `/mcp` — це і є MCP-сервер».

## Крок 2. Протокол руками: робимо те, що робить Claude Code (~4 хв)

Відкрити в Insomnia колекцію **BeeBank MCP** і виконувати запити по черзі, згори вниз. Люди бачать і запит (метод, заголовки, JSON), і відповідь. Після `1. initialize` скрипт зберігає `Mcp-Session-Id` у змінну `session`, і наступні запити підставляють її самі. Ті самі запити одразу з'являються в `make watch`.

Що казати на кожному кроці:

| # | Запит | Що сказати |
|---|---|---|
| 0 | без токена | «Це звичайний HTTP. Без токена — 401, до MCP навіть не дійшло» |
| 1 | `initialize` | «Клієнт і сервер обмінюються можливостями. Дивіться на `Mcp-Session-Id` у заголовку — далі він їде в кожному запиті» |
| 2 | `notifications/initialized` | «Нотифікація: без `id`, відповідь — порожній 202» |
| 3 | `tools/list` | «Ось що Claude Code покладе в запит до моделі. Модель обирає tool лише за цими описами». Знайти у відповіді `transfer_honey`: «Цю JSON Schema ніхто не писав руками — SDK згенерував її з PHP-сигнатури й docblock» |
| 4 | `resources/read` | «Ресурс — дані за URI, як файл» |
| 5 | `prompts/get` | «Prompt від сервера — у Claude Code це слеш-команда» |
| 6 | `tools/call get_hive` | «Це те, що Claude Code робить, коли модель сказала "виклич get_hive"» |
| 7 | `withdraw_honey` 999 кг | «HTTP 200, JSON-RPC без помилки, але `isError: true`. Це бізнес-помилка для моделі — вона прочитає й виправиться» |
| 8 | `hiveId: "abc"` | «А це помилка протоколу: схема не пройшла, наш PHP-метод навіть не викликався» |
| 9 | `transfer_honey` | «Пишуча дія» — **показати на UI**: «Фабрика Пєті» червона, «Бджолині Межигірʼя» зелене |
| 10 | `DELETE` | «Клієнт закриває сесію» |
| 11 | той самий запит | «Той самий `Mcp-Session-Id` тепер — 404» |

> Меседж: **це весь протокол**. HTTP POST з JSON-RPC, три примітиви, сесія в заголовку. Жодної магії.

Запасний варіант: `make inspector` → Transport **Streamable HTTP**, URL `http://localhost:8080/mcp`, Authentication → Bearer `dev-secret` → Connect.

## Крок 3. Код (~2 хв)

- `public/mcp.php` — увесь сервер: перевірка токена → `Server::builder()` → `StreamableHttpTransport`. Звичайний фронт-контролер
- `src/Mcp/HiveTools.php` — `#[McpTool]` + docblock = те, що ми щойно бачили в `tools/list`
- `BeeBankException` → `ToolCallException` → `isError: true` (запит 7 в Insomnia)
- Домен (`src/Domain`) про MCP нічого не знає — MCP лише ще один адаптер, як контролер

## Крок 4. Підключаємо BeeBank до Claude Code (~2 хв)

Сказати: «Так підключають будь-який реальний MCP-сервер: Jira, Figma, Sentry. Сервер живе десь за URL, ми лише кажемо Claude Code, де він».

**4.1. Що вже підключено**

```bash
claude mcp list
```

Видно інші сервери (Atlassian, Figma, Google…) — усі підключені так само, за URL. `beebank` поки немає.

**4.2. Додаємо сервер — одна команда**

```bash
claude mcp add --transport http --scope user beebank http://localhost:8080/mcp \
  --header "Authorization: Bearer dev-secret"
```

Пояснити по частинах:

| Частина | Що означає |
|---|---|
| `--transport http` | Streamable HTTP: Claude Code шле POST на URL. (Інший варіант — `stdio`: Claude Code сам запускає процес) |
| `--scope user` | Доступно в усіх моїх проєктах. `local` — лише в поточній папці, `project` — записати в `.mcp.json` репо для всієї команди |
| `beebank` | Ім'я сервера. З нього префікс tools: `mcp__beebank__get_hive` |
| `http://localhost:8080/mcp` | Адреса. Для реального сервера — `https://…` в інтернеті чи внутрішній мережі |
| `--header "Authorization: …"` | Наш простий токен. Сервери з OAuth (Jira, Figma) натомість відкриють браузер для логіну |

Очікувано: `Added HTTP MCP server beebank … to user config` і `File modified: ~/.claude.json`.

**4.3. Перевіряємо**

```bash
claude mcp get beebank
```

Очікувано: `Scope: User config (available in all your projects)`, `Status: ✔ Connected`.

Показати `make watch`: Claude Code щойно постукав у сервер. Сказати: «Insomnia говорила ревізією `2025-11-25`: `initialize`, сесія в заголовку. А Claude Code вже говорить новою, `2026-07-28`: замість `initialize` — `server/discover`, сесій немає, версія й можливості клієнта їдуть у `_meta` кожного запиту». Повний вигляд: `make watch ARGS=--full`.

**4.4. Запускаємо Claude Code — з будь-якої папки**

```bash
claude
```

У сесії: `/mcp` → `beebank` → видно 11 tools, prompt і resource.

## Крок 5. Claude Code: те саме, але запити робить агент (~4 хв)

Сказати: «Claude Code зараз робить ті самі запити, що ми руками. Але вирішує, *які* tools викликати, модель». Тримати `make watch` на екрані: видно кожен `tools/call`, який tool обрала модель і які аргументи вона сформувала.

Промпти по черзі (дивимось, як блимають баланси в UI):

1. > Відкрий новий вулик «Еспарцет» з маткою Аллою, зарахуй йому 3 кг першого збору, а Маткавщина хай подарує йому 500 г на новосілля

   Перед першим викликом Claude Code питає дозвіл: «Allow `mcp__beebank__open_hive`?» — відповідати **Yes**. Агент сам: `open_hive` → `record_harvest` → `transfer_honey`.

2. > Переведи з Маткавщини 10 кг Фабриці Пєті

   Овердрафт → `isError` → агент пояснює і пропонує варіант.

3. > Видали вулик «Бджолині Межигірʼя»

   Заборонено (є історія) → агент пропонує `freeze_hive`.

4. `/mcp__beebank__daily_review` — prompt від сервера як слеш-команда: агент сам збирає операції за день і робить звіт.

## Крок 6. Live coding: `pay_interest` (~3 хв)

Мета: новий tool — це 15 рядків, і агент одразу ним користується.

- **A** (найнадійніше): в іншій вкладці терміналу, у репо: `patch -p1 < docs/pay_interest.patch` і пройтись по diff
- **B**: написати руками за `docs/pay_interest.patch` (enum case + метод домену + tool)
- **C**: окрема сесія Claude Code у репо: _«Додай tool pay_interest: нараховує всім активним вуликам N% від балансу, новий тип операції Interest»_ — ефектно, але довше

Далі, у сесії з кроку 5:

1. `/mcp` → `beebank` → **Reconnect**. Сервер перезапускати не треба (кожен HTTP-запит — свіжий PHP), але Claude Code кешує `tools/list`
2. > Нарахуй усім вуликам 5% медових відсотків

   Усі баланси в UI блимають зеленим, а в `make watch` видно `tools/call pay_interest` з `{"percent": 5}`.

## Крок 7. Слайди після демо + Q&A (~5 хв)

Коли MCP не потрібен → що спробувати в нас → питання.

## Після виступу

```bash
claude mcp remove beebank -s user
patch -R -p1 < docs/pay_interest.patch
```

---

## План Б

| Що пішло не так | Що робити |
|---|---|
| `claude mcp get beebank` — не Connected | `docker compose ps`; `make up`; `curl -i -X POST localhost:8080/mcp` → `401`; у сесії `/mcp` → Reconnect |
| Помилка в команді `claude mcp add` | `claude mcp remove beebank -s user` і додати знову |
| Insomnia: 400/404 на запитах після initialize | Перезапустити `1. initialize` — він оновить `Mcp-Session-Id`, потім далі по черзі |
| Insomnia недоступна | `make inspector` |
| Зламалась БД / дивний стан | `make seed` |
| Логи сервера | `tail -f var/mcp.log` (детально: `MCP_DEBUG: 1` в `environment` у `compose.yaml` + `make up`) |
