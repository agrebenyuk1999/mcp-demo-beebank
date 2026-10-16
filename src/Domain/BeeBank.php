<?php

declare(strict_types=1);

namespace BeeBank\Domain;

final readonly class BeeBank
{
    private const string HIVE_SELECT = <<<'SQL'
        SELECT h.*,
            COALESCE((SELECT SUM(grams) FROM operations WHERE to_hive_id = h.id), 0)
            - COALESCE((SELECT SUM(grams) FROM operations WHERE from_hive_id = h.id), 0) AS balance
        FROM hives h
        SQL;

    public function __construct(private \PDO $db)
    {
    }

    /** @return list<Hive> */
    public function hives(): array
    {
        $rows = $this->db->query(self::HIVE_SELECT.' ORDER BY h.id')->fetchAll();

        return array_map($this->hydrateHive(...), $rows);
    }

    public function hive(int $id): Hive
    {
        $stmt = $this->db->prepare(self::HIVE_SELECT.' WHERE h.id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (false === $row) {
            throw new BeeBankException("Вулик #{$id} не знайдено.");
        }

        return $this->hydrateHive($row);
    }

    public function openHive(string $name, string $queen): Hive
    {
        $name = trim($name);
        if ('' === $name) {
            throw new BeeBankException('Назва вулика не може бути порожньою.');
        }

        $this->db->prepare('INSERT INTO hives (name, queen, status) VALUES (?, ?, ?)')
            ->execute([$name, trim($queen), HiveStatus::Active->value]);

        return $this->hive((int) $this->db->lastInsertId());
    }

    public function updateHive(int $id, ?string $name = null, ?string $queen = null): Hive
    {
        $hive = $this->hive($id);

        $this->db->prepare('UPDATE hives SET name = ?, queen = ? WHERE id = ?')
            ->execute([trim($name ?? $hive->name), trim($queen ?? $hive->queen), $id]);

        return $this->hive($id);
    }

    public function freeze(int $id): Hive
    {
        return $this->changeStatus($id, HiveStatus::Frozen);
    }

    public function unfreeze(int $id): Hive
    {
        return $this->changeStatus($id, HiveStatus::Active);
    }

    public function deleteHive(int $id): void
    {
        $this->hive($id);

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM operations WHERE from_hive_id = :id OR to_hive_id = :id');
        $stmt->execute(['id' => $id]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new BeeBankException("Вулик #{$id} має історію операцій — видалення заборонено, лише freeze.");
        }

        $this->db->prepare('DELETE FROM hives WHERE id = ?')->execute([$id]);
    }

    public function harvest(int $hiveId, int $grams, ?string $memo = null): Operation
    {
        return $this->transactional(function () use ($hiveId, $grams, $memo): Operation {
            $this->assertActive($this->hive($hiveId));

            return $this->record(OperationType::Harvest, null, $hiveId, $grams, $memo);
        });
    }

    public function withdraw(int $hiveId, int $grams, ?string $memo = null): Operation
    {
        return $this->transactional(function () use ($hiveId, $grams, $memo): Operation {
            $this->assertCanSpend($this->hive($hiveId), $grams);

            return $this->record(OperationType::Withdrawal, $hiveId, null, $grams, $memo);
        });
    }

    public function transfer(int $fromHiveId, int $toHiveId, int $grams, string $memo): Operation
    {
        if ($fromHiveId === $toHiveId) {
            throw new BeeBankException('Переказ самому собі заборонено.');
        }
        if ('' === trim($memo)) {
            throw new BeeBankException('Memo обов\'язкове для переказу.');
        }

        return $this->transactional(function () use ($fromHiveId, $toHiveId, $grams, $memo): Operation {
            $this->assertCanSpend($this->hive($fromHiveId), $grams);
            $this->assertActive($this->hive($toHiveId));

            return $this->record(OperationType::Transfer, $fromHiveId, $toHiveId, $grams, $memo);
        });
    }

    /** @return list<Operation> */
    public function operations(?int $hiveId = null, ?\DateTimeImmutable $day = null, int $limit = 50): array
    {
        $where = [];
        $params = [];
        if (null !== $hiveId) {
            $where[] = '(from_hive_id = :hive OR to_hive_id = :hive)';
            $params['hive'] = $hiveId;
        }
        if (null !== $day) {
            $where[] = 'date(created_at) = :day';
            $params['day'] = $day->format('Y-m-d');
        }

        $sql = 'SELECT * FROM operations'
            .($where ? ' WHERE '.implode(' AND ', $where) : '')
            .' ORDER BY id DESC LIMIT '.max(1, $limit);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn (array $row): Operation => new Operation(
            (int) $row['id'],
            OperationType::from($row['type']),
            null === $row['from_hive_id'] ? null : (int) $row['from_hive_id'],
            null === $row['to_hive_id'] ? null : (int) $row['to_hive_id'],
            (int) $row['grams'],
            $row['memo'],
            new \DateTimeImmutable($row['created_at']),
        ), $stmt->fetchAll());
    }

    private function hydrateHive(array $row): Hive
    {
        return new Hive(
            (int) $row['id'],
            $row['name'],
            $row['queen'],
            HiveStatus::from($row['status']),
            (int) $row['balance'],
        );
    }

    private function changeStatus(int $id, HiveStatus $status): Hive
    {
        $this->hive($id);
        $this->db->prepare('UPDATE hives SET status = ? WHERE id = ?')->execute([$status->value, $id]);

        return $this->hive($id);
    }

    private function assertActive(Hive $hive): void
    {
        if (HiveStatus::Frozen === $hive->status) {
            throw new BeeBankException("Вулик #{$hive->id} «{$hive->name}» заморожено — операції заборонені.");
        }
    }

    private function assertCanSpend(Hive $hive, int $grams): void
    {
        $this->assertActive($hive);
        if ($hive->balance < $grams) {
            throw new BeeBankException(\sprintf(
                'Недостатньо меду у вулику #%d «%s»: баланс %s, потрібно %s.',
                $hive->id,
                $hive->name,
                Honey::format($hive->balance),
                Honey::format($grams),
            ));
        }
    }

    private function record(OperationType $type, ?int $from, ?int $to, int $grams, ?string $memo): Operation
    {
        if ($grams <= 0) {
            throw new BeeBankException('Сума операції має бути > 0.');
        }

        $this->db->prepare('INSERT INTO operations (type, from_hive_id, to_hive_id, grams, memo, created_at) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$type->value, $from, $to, $grams, $memo, new \DateTimeImmutable()->format('Y-m-d H:i:s')]);

        return $this->operations(limit: 1)[0];
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     */
    private function transactional(callable $fn): mixed
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $result = $fn();
            $this->db->exec('COMMIT');

            return $result;
        } catch (\Throwable $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }
}
