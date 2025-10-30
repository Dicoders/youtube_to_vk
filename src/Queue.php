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

    public function push(string $class, array $data): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO queue (payload, priority) VALUES (:payload, :priority)");
        $stmt->execute([
            'payload' => json_encode(['class_handler' => $class, 'data' => $data]),
            'priority' => $class::getPriority()
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
        $task = $stmt->fetch(\PDO::FETCH_ASSOC);

        $update = $this->pdo->prepare("UPDATE queue SET processed = 1 WHERE id = :id");
        $update->execute(['id' => $task['id']]);

        $payload = json_decode($task['payload'], true);

        return [$task['id'], $payload['class_handler'], $payload['data']];
    }

    public function rollback(int $task_id): void
    {
        $update = $this->pdo->prepare("UPDATE queue SET processed = 0 WHERE id = :id");
        $update->execute(['id' => $task_id]);
    }

    public function size(): int
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM queue WHERE processed = 0");
        return (int)$stmt->fetchColumn();
    }

    public function acquireLock(): bool
    {
        try {
            $this->pdo->exec("BEGIN IMMEDIATE"); // блокирует на запись

            // Пытаемся вставить, если уже есть — значит другой воркер работает
            $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO worker_lock (id) VALUES (1)");
            $stmt->execute();

            $rows = $this->pdo->query("SELECT changes()")->fetchColumn();
            if ($rows == 0) {
                $this->pdo->exec("ROLLBACK");
                return false; // уже заблокировано
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
