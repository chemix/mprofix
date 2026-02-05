<?php

namespace MyProfiTests\Writer;

use MyProfi\Writer\SqliteWriter;
use PHPUnit\Framework\TestCase;

class SqliteWriterTest extends TestCase
{
    private string $testDbFile;

    protected function setUp(): void
    {
        $this->testDbFile = sys_get_temp_dir() . '/myprofi_test_' . uniqid() . '.sqlite';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDbFile)) {
            unlink($this->testDbFile);
        }
        parent::tearDown();
    }

    public function testCreatesDatabaseFile(): void
    {
        $writer = new SqliteWriter($this->testDbFile);
        $writer->finalize();

        self::assertFileExists($this->testDbFile);
    }

    public function testCreatesSchema(): void
    {
        $writer = new SqliteWriter($this->testDbFile);
        $writer->finalize();

        $pdo = new \PDO('sqlite:' . $this->testDbFile);
        $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tables = $result->fetchAll(\PDO::FETCH_COLUMN);

        self::assertContains('query_executions', $tables);
        self::assertContains('export_metadata', $tables);
    }

    public function testWriteExecution(): void
    {
        $writer = new SqliteWriter($this->testDbFile);

        $writer->writeExecution([
            'hash' => 'abc123',
            'pattern' => 'select*from users where id={}',
            'sql' => 'select * from users where id=42',
            'thread_id' => '100',
            'user' => 'root',
            'host' => 'localhost',
            'timestamp' => '2024-01-01 12:00:00',
            'schema' => 'mydb',
            'qt' => 0.5,
            'lt' => 0.01,
            'rs' => 1,
            're' => 100,
        ]);

        $writer->finalize();

        $pdo = new \PDO('sqlite:' . $this->testDbFile);
        $result = $pdo->query('SELECT * FROM query_executions WHERE pattern_hash = "abc123"');
        $row = $result->fetch(\PDO::FETCH_ASSOC);

        self::assertNotFalse($row);
        self::assertEquals('abc123', $row['pattern_hash']);
        self::assertEquals('select*from users where id={}', $row['normalized_pattern']);
        self::assertEquals('select * from users where id=42', $row['raw_sql']);
        self::assertEquals('100', $row['thread_id']);
        self::assertEquals('root', $row['user']);
        self::assertEquals('localhost', $row['host']);
        self::assertEquals('2024-01-01 12:00:00', $row['timestamp']);
        self::assertEquals('mydb', $row['schema_name']);
        self::assertEquals(0.5, (float) $row['query_time']);
        self::assertEquals(0.01, (float) $row['lock_time']);
        self::assertEquals(1, (int) $row['rows_sent']);
        self::assertEquals(100, (int) $row['rows_examined']);
    }

    public function testInsertCount(): void
    {
        $writer = new SqliteWriter($this->testDbFile);

        for ($i = 0; $i < 5; $i++) {
            $writer->writeExecution([
                'hash' => 'hash' . $i,
                'pattern' => 'select ' . $i,
                'sql' => 'select ' . $i,
            ]);
        }

        self::assertEquals(5, $writer->getInsertCount());
        $writer->finalize();
    }

    public function testSetMetadata(): void
    {
        $writer = new SqliteWriter($this->testDbFile);
        $writer->setMetadata('source_file', 'test.log');
        $writer->setMetadata('custom_key', 'custom_value');
        $writer->finalize();

        $pdo = new \PDO('sqlite:' . $this->testDbFile);
        $result = $pdo->query('SELECT key, value FROM export_metadata ORDER BY key');
        $metadata = $result->fetchAll(\PDO::FETCH_KEY_PAIR);

        self::assertArrayHasKey('source_file', $metadata);
        self::assertEquals('test.log', $metadata['source_file']);
        self::assertArrayHasKey('custom_key', $metadata);
        self::assertEquals('custom_value', $metadata['custom_key']);
        self::assertArrayHasKey('export_time', $metadata);
        self::assertArrayHasKey('total_queries', $metadata);
    }

    public function testFinalizeAddsMetadata(): void
    {
        $writer = new SqliteWriter($this->testDbFile);

        $writer->writeExecution([
            'hash' => 'test',
            'pattern' => 'test',
            'sql' => 'test',
        ]);

        $writer->finalize();

        $pdo = new \PDO('sqlite:' . $this->testDbFile);
        $result = $pdo->query('SELECT value FROM export_metadata WHERE key = "total_queries"');
        $total = $result->fetchColumn();

        self::assertEquals('1', $total);
    }

    public function testOverwritesExistingFile(): void
    {
        // Create first database
        $writer1 = new SqliteWriter($this->testDbFile);
        $writer1->writeExecution([
            'hash' => 'old',
            'pattern' => 'old query',
            'sql' => 'old query',
        ]);
        $writer1->finalize();

        // Create new database (should overwrite)
        $writer2 = new SqliteWriter($this->testDbFile);
        $writer2->writeExecution([
            'hash' => 'new',
            'pattern' => 'new query',
            'sql' => 'new query',
        ]);
        $writer2->finalize();

        $pdo = new \PDO('sqlite:' . $this->testDbFile);

        // Old data should not exist
        $result = $pdo->query('SELECT COUNT(*) FROM query_executions WHERE pattern_hash = "old"');
        self::assertEquals(0, $result->fetchColumn());

        // New data should exist
        $result = $pdo->query('SELECT COUNT(*) FROM query_executions WHERE pattern_hash = "new"');
        self::assertEquals(1, $result->fetchColumn());
    }

    public function testHandlesNullValues(): void
    {
        $writer = new SqliteWriter($this->testDbFile);

        $writer->writeExecution([
            'hash' => 'test',
            'pattern' => 'test pattern',
            'sql' => 'test sql',
            // Omit optional fields
        ]);

        $writer->finalize();

        $pdo = new \PDO('sqlite:' . $this->testDbFile);
        $result = $pdo->query('SELECT * FROM query_executions WHERE pattern_hash = "test"');
        $row = $result->fetch(\PDO::FETCH_ASSOC);

        self::assertNotFalse($row);
        self::assertNull($row['thread_id']);
        self::assertNull($row['user']);
        self::assertNull($row['host']);
        self::assertNull($row['timestamp']);
        self::assertNull($row['schema_name']);
        self::assertEquals(0, (float) $row['query_time']);
    }
}
