<?php

namespace App;

use Throwable;

class Queue
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function push(string $class, Task $task): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO queue (payload, priority) VALUES (:payload, :priority)");
        $stmt->execute([
            'payload'  => json_encode(['class_handler' => $class, 'data' => $task->toArray()]),
            'priority' => $class::getPriority(),
        ]);
    }

    public function pop(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM queue
            WHERE processed = 0
            ORDER BY priority DESC, id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        $update = $this->pdo->prepare("UPDATE queue SET processed = 1 WHERE id = :id");
        $update->execute(['id' => $row['id']]);

        $payload = json_decode($row['payload'], true);

        return [$row['id'], $payload['class_handler'], Task::fromArray($payload['data'])];
    }

    /**
     * Откатывает задачу. После MAX_ATTEMPTS попыток помечает как окончательно упавшую.
     */
    public function rollback(int $task_id): void
    {
        $stmt = $this->pdo->prepare("SELECT attempts FROM queue WHERE id = :id");
        $stmt->execute(['id' => $task_id]);
        $attempts = (int)$stmt->fetchColumn() + 1;

        if ($attempts >= Config::MAX_ATTEMPTS) {
            $update = $this->pdo->prepare(
                "UPDATE queue SET processed = 1, failed = 1, attempts = :attempts WHERE id = :id"
            );
        } else {
            $update = $this->pdo->prepare(
                "UPDATE queue SET processed = 0, attempts = :attempts WHERE id = :id"
            );
        }

        $update->execute(['id' => $task_id, 'attempts' => $attempts]);
    }

    public function isFailed(int $task_id): bool
    {
        $stmt = $this->pdo->prepare("SELECT failed FROM queue WHERE id = :id");
        $stmt->execute(['id' => $task_id]);
        return (bool)$stmt->fetchColumn();
    }

    public function size(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM queue WHERE processed = 0");
        return (int)$stmt->fetchColumn();
    }

    /**
     * Пытается захватить лок. Автоматически снимает зависшие локи (старше LOCK_TIMEOUT_SECONDS).
     */
    public function acquireLock(): bool
    {
        try {
            $this->pdo->exec("BEGIN IMMEDIATE");

            // Снимаем зависший лок если он старше таймаута
            $this->pdo->exec(sprintf(
                "DELETE FROM worker_lock WHERE locked_at < datetime('now', '-%d seconds')",
                Config::LOCK_TIMEOUT_SECONDS
            ));

            $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO worker_lock (id) VALUES (1)");
            $stmt->execute();

            $rows = $this->pdo->query("SELECT changes()")->fetchColumn();
            if ($rows == 0) {
                $this->pdo->exec("ROLLBACK");
                return false;
            }

            $this->pdo->exec("COMMIT");
            return true;
        } catch (Throwable $e) {
            $this->pdo->exec("ROLLBACK");
            return false;
        }
    }

    public function releaseLock(): void
    {
        $this->pdo->exec("DELETE FROM worker_lock WHERE id = 1");
    }
}