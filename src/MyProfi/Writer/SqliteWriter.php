<?php

namespace MyProfi\Writer;

/**
 * SqliteWriter writes query execution data to SQLite database
 */
class SqliteWriter
{
    protected \PDO $pdo;
    protected \PDOStatement $insertStmt;
    protected int $insertCount = 0;
    protected int $batchSize = 1000;
    protected int $currentBatch = 0;

    public function __construct(string $filename)
    {
        if (file_exists($filename)) {
            unlink($filename);
        }

        $this->pdo = new \PDO('sqlite:' . $filename);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->createSchema();
        $this->prepareStatements();
        $this->pdo->beginTransaction();
    }

    protected function createSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE query_executions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pattern_hash TEXT NOT NULL,
                timestamp TEXT,
                thread_id TEXT,
                user TEXT,
                host TEXT,
                schema_name TEXT,
                query_time REAL DEFAULT 0,
                lock_time REAL DEFAULT 0,
                rows_sent INTEGER DEFAULT 0,
                rows_examined INTEGER DEFAULT 0,
                raw_sql TEXT NOT NULL,
                normalized_pattern TEXT NOT NULL
            )
        ');

        $this->pdo->exec('
            CREATE TABLE export_metadata (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        ');

        $this->pdo->exec('CREATE INDEX idx_pattern_hash ON query_executions(pattern_hash)');
        $this->pdo->exec('CREATE INDEX idx_timestamp ON query_executions(timestamp)');
        $this->pdo->exec('CREATE INDEX idx_thread_id ON query_executions(thread_id)');
        $this->pdo->exec('CREATE INDEX idx_user ON query_executions(user)');
    }

    protected function prepareStatements(): void
    {
        $this->insertStmt = $this->pdo->prepare('
            INSERT INTO query_executions (
                pattern_hash, timestamp, thread_id, user, host, schema_name,
                query_time, lock_time, rows_sent, rows_examined,
                raw_sql, normalized_pattern
            ) VALUES (
                :pattern_hash, :timestamp, :thread_id, :user, :host, :schema_name,
                :query_time, :lock_time, :rows_sent, :rows_examined,
                :raw_sql, :normalized_pattern
            )
        ');
    }

    public function writeExecution(array $data): void
    {
        $this->insertStmt->execute([
            ':pattern_hash' => $data['hash'],
            ':timestamp' => $data['timestamp'] ?? null,
            ':thread_id' => $data['thread_id'] ?? null,
            ':user' => $data['user'] ?? null,
            ':host' => $data['host'] ?? null,
            ':schema_name' => $data['schema'] ?? null,
            ':query_time' => $data['qt'] ?? 0,
            ':lock_time' => $data['lt'] ?? 0,
            ':rows_sent' => $data['rs'] ?? 0,
            ':rows_examined' => $data['re'] ?? 0,
            ':raw_sql' => $data['sql'],
            ':normalized_pattern' => $data['pattern'],
        ]);

        $this->insertCount++;
        $this->currentBatch++;

        if ($this->currentBatch >= $this->batchSize) {
            $this->pdo->commit();
            $this->pdo->beginTransaction();
            $this->currentBatch = 0;
        }
    }

    public function setMetadata(string $key, string $value): void
    {
        $stmt = $this->pdo->prepare('INSERT OR REPLACE INTO export_metadata (key, value) VALUES (?, ?)');
        $stmt->execute([$key, $value]);
    }

    public function finalize(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }

        $this->setMetadata('export_time', date('Y-m-d H:i:s'));
        $this->setMetadata('total_queries', (string) $this->insertCount);
    }

    public function getInsertCount(): int
    {
        return $this->insertCount;
    }
}
