<?php

declare(strict_types=1);

use BeeBank\Database;
use BeeBank\Domain\BeeBank;
use BeeBank\Domain\Hive;
use BeeBank\Domain\Operation;

require dirname(__DIR__).'/vendor/autoload.php';

$pdo = Database::connect();
$bank = new BeeBank($pdo);

switch ($_GET['api'] ?? null) {
    case 'state':
        $hives = $bank->hives();
        $names = array_column(array_map(static fn (Hive $h): array => $h->toArray(), $hives), 'name', 'id');

        header('Content-Type: application/json');
        echo json_encode([
            'hives' => array_map(static fn (Hive $h): array => $h->toArray(), $hives),
            'operations' => array_map(static fn (Operation $op): array => [
                ...$op->toArray(),
                'from' => $names[$op->fromHiveId] ?? null,
                'to' => $names[$op->toHiveId] ?? null,
            ], $bank->operations(limit: 15)),
        ], \JSON_UNESCAPED_UNICODE);
        exit;
}
?>
<!doctype html>
<html lang="uk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BeeBank 🐝</title>
<style>
  :root {
    --bg: #17140f; --panel: #221d15; --line: #3a3124; --text: #f6efe0; --muted: #a8997d;
    --honey: #f5b324; --honey-dim: #8a6514; --up: #5fd38d; --down: #ff6b5b; --frozen: #7cc4ff;
  }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--text); font: 16px/1.4 ui-sans-serif, system-ui, -apple-system, sans-serif; }
  header { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 32px; border-bottom: 1px solid var(--line); }
  h1 { margin: 0; font-size: 28px; letter-spacing: .5px; }
  h1 span { color: var(--honey); }
  h2 { margin: 0 0 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 1.5px; color: var(--muted); font-weight: 600; }
  main { padding: 24px 32px; display: grid; gap: 32px; }
  .hives { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
  .hive { position: relative; background: var(--panel); border: 1px solid var(--line); border-radius: 14px; padding: 16px 18px; transition: border-color .3s, box-shadow .3s; }
  .hive .id { color: var(--muted); font-size: 13px; }
  .hive .name { font-size: 20px; font-weight: 700; margin: 2px 0; }
  .hive .queen { color: var(--muted); font-size: 13px; }
  .hive .balance { font-size: 30px; font-weight: 800; color: var(--honey); margin-top: 10px; font-variant-numeric: tabular-nums; }
  .hive.frozen { opacity: .65; border-style: dashed; }
  .hive.frozen .balance { color: var(--frozen); }
  .badge { position: absolute; top: 14px; right: 14px; font-size: 12px; padding: 2px 8px; border-radius: 99px; background: #1d3347; color: var(--frozen); }
  .delta { position: absolute; right: 16px; top: 14px; font-size: 18px; font-weight: 700; opacity: 0; transition: opacity .4s; }
  .flash-up { border-color: var(--up); box-shadow: 0 0 24px #5fd38d55; }
  .flash-down { border-color: var(--down); box-shadow: 0 0 24px #ff6b5b55; }
  .flash-up .delta { color: var(--up); opacity: 1; }
  .flash-down .delta { color: var(--down); opacity: 1; }
  .flash-new { border-color: var(--honey); box-shadow: 0 0 24px #f5b32455; }
  table { width: 100%; border-collapse: collapse; background: var(--panel); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; }
  th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
  th { color: var(--muted); font-size: 13px; font-weight: 600; }
  tr:last-child td { border-bottom: 0; }
  td.amount { font-variant-numeric: tabular-nums; white-space: nowrap; font-weight: 700; color: var(--honey); }
  td.memo { color: var(--muted); max-width: 520px; }
  td.time { white-space: nowrap; color: var(--muted); }
  tr.fresh td { animation: fresh 3s ease-out; }
  @keyframes fresh { from { background: #f5b32433; } to { background: transparent; } }
  @media (max-width: 640px) { header, main { padding-left: 16px; padding-right: 16px; } td.memo { max-width: none; } }
</style>
</head>
<body>
<header>
  <h1>🐝 Bee<span>Bank</span></h1>
</header>
<main>
  <section>
    <h2>Вулики</h2>
    <div id="hives" class="hives"></div>
  </section>
  <section>
    <h2>Ledger</h2>
    <table>
      <thead><tr><th>#</th><th>Час</th><th>Тип</th><th>Звідки → Куди</th><th>Сума</th><th>Memo</th></tr></thead>
      <tbody id="ops"></tbody>
    </table>
  </section>
</main>
<script>
const TYPES = { harvest: '🌼 Harvest', withdrawal: '🧑‍🌾 Withdrawal', transfer: '🔁 Transfer', interest: '📈 Interest' };
const balances = new Map();
let lastOpId = null;

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const kg = (g) => Math.abs(g) < 1000 ? `${g} г` : `${+(g / 1000).toFixed(3)} кг`;

function renderHives(hives) {
  const root = document.getElementById('hives');
  root.innerHTML = hives.map((h) => `
    <div class="hive ${h.status}" data-id="${h.id}">
      ${h.status === 'frozen' ? '<span class="badge">❄️ frozen</span>' : ''}
      <div class="id">#${h.id}</div>
      <div class="name">${esc(h.name)}</div>
      <div class="queen">👑 ${esc(h.queen)}</div>
      <div class="balance">${esc(h.balance)}</div>
      <div class="delta"></div>
    </div>`).join('');

  for (const h of hives) {
    const el = root.querySelector(`[data-id="${h.id}"]`);
    const prev = balances.get(h.id);
    if (balances.size && prev === undefined) {
      el.classList.add('flash-new');
    } else if (prev !== undefined && prev !== h.balance_grams) {
      const diff = h.balance_grams - prev;
      el.classList.add(diff > 0 ? 'flash-up' : 'flash-down');
      el.querySelector('.delta').textContent = `${diff > 0 ? '+' : '−'}${kg(Math.abs(diff))}`;
      setTimeout(() => el.classList.remove('flash-up', 'flash-down'), 4000);
    }
    balances.set(h.id, h.balance_grams);
  }
}

function renderOps(ops) {
  document.getElementById('ops').innerHTML = ops.map((o) => `
    <tr class="${lastOpId !== null && o.id > lastOpId ? 'fresh' : ''}">
      <td>${o.id}</td>
      <td class="time">${esc(o.created_at.slice(5))}</td>
      <td>${TYPES[o.type] ?? esc(o.type)}</td>
      <td>${esc(o.from ?? '—')} → ${esc(o.to ?? '—')}</td>
      <td class="amount">${esc(o.amount)}</td>
      <td class="memo">${esc(o.memo)}</td>
    </tr>`).join('');
  lastOpId = ops.length ? ops[0].id : lastOpId;
}


let lastJson = '';
async function tick() {
  try {
    const res = await fetch('?api=state', { cache: 'no-store' });
    const json = await res.text();
    if (json !== lastJson) {
      lastJson = json;
      const state = JSON.parse(json);
      renderHives(state.hives);
      renderOps(state.operations);
    }
  } catch (e) {
    console.error(e);
  }
  setTimeout(tick, 1000);
}


tick();
</script>
</body>
</html>
