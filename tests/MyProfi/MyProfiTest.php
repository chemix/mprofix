<?php

namespace MyProfiTests;

use PHPUnit\Framework\TestCase;

/**
 * Class MyProfiTest
 * @package MyProfiTests
 */
class MyProfiTest extends TestCase
{
    /** @var \MyProfi\MyProfi */
    private $myprofi;

    protected function setUp(): void
    {
        $this->myprofi = new \MyProfi\MyProfi();
        parent::setUp();
    }

    private function getProtectedProperty(object $obj, string $prop): mixed
    {
        $ref = new \ReflectionClass($obj);
        $p = $ref->getProperty($prop);
        return $p->getValue($obj);
    }

    public function testCsVFilenameIsDetected(): void
    {
        $this->myprofi->setInputFile('foobar.csv');
        self::assertEquals(true, $this->getProtectedProperty($this->myprofi, 'csv'));
        self::assertEquals('foobar.csv', $this->getProtectedProperty($this->myprofi, 'filename'));
    }

    public function testNonCsvFilenameIsDetected(): void
    {
        $this->myprofi->setInputFile('foobar.log');
        self::assertEquals(false, $this->getProtectedProperty($this->myprofi, 'csv'));
        self::assertEquals('foobar.log', $this->getProtectedProperty($this->myprofi, 'filename'));
    }

    /**
     * Actually these do not test a "unit", but the whole process
     * I am sure this could be done better, but for the moment [tm] it is ok
     */
    private function setUpSimpleSlowYodaEventLog(): void
    {
        $this->myprofi->setInputFile(__DIR__ . '/../logs/slow_yoda_event.log');
        $this->myprofi->slow(true);
        $this->myprofi->processQueries();
    }

    /**
     * Actually these do not test a "unit", but the whole process
     * I am sure this could be done better, but for the moment [tm] it is ok
     */
    private function setUpPerconaStyleShortLog(): void
    {
        $this->myprofi->setInputFile(__DIR__ . '/../logs/percona_style_short.log');
        $this->myprofi->slow(true);
        $this->myprofi->processQueries();
    }

    public function testSimpleSlowYodaEventLogTotal(): void
    {
        $this->setUpSimpleSlowYodaEventLog();

        $totalNumberOfEntries = $this->myprofi->total();
        self::assertEquals(2, $totalNumberOfEntries);
    }

    public function testSimpleSlowYodaEventLogTypesStat(): void
    {
        $this->setUpSimpleSlowYodaEventLog();

        self::assertCount(1, $this->myprofi->getTypesStat());

        foreach ($this->myprofi->getTypesStat() as $type => $num) {
            self::assertEquals('select', $type);
            self::assertEquals(2, $num);
        }
    }

    public function testSimpleSlowYodaEventLogNums(): void
    {
        $this->setUpSimpleSlowYodaEventLog();

        $patternNums = $this->myprofi->getPatternNums();

        $expectedNums = [];
        $expectedNums['bec61a1e580942b2b0eb38cd4b5e9fc1'] = 1;
        $expectedNums['397ccc9858a34713edf005e9d92d5e64'] = 1;

        self::assertEquals($expectedNums, $patternNums);
    }

    public function testSimpleSlowYodaEventLogQueries(): void
    {
        $this->setUpSimpleSlowYodaEventLog();

        $patternQueries = $this->myprofi->getPatternQueries();

        $expectedQueries = [];
        $expectedQueries['bec61a1e580942b2b0eb38cd4b5e9fc1'] = 'select*from yoda_event;';
        $expectedQueries['397ccc9858a34713edf005e9d92d5e64'] = 'select*from yoda_event where location={};';

        self::assertEquals($expectedQueries, $patternQueries);
    }

    public function testPerconaStyleShortLogNums(): void
    {
        $this->setUpPerconaStyleShortLog();

        $patternNums = $this->myprofi->getPatternNums();

        $expectedNums = [];
        $expectedNums['bec61a1e580942b2b0eb38cd4b5e9fc1'] = 1;

        self::assertEquals($expectedNums, $patternNums);
    }

    public function testPerconaStyleShortLogQueries(): void
    {
        $this->setUpPerconaStyleShortLog();

        $patternQueries = $this->myprofi->getPatternQueries();

        $expectedQueries = [];
        $expectedQueries['bec61a1e580942b2b0eb38cd4b5e9fc1'] = 'select*from yoda_event;';

        self::assertEquals($expectedQueries, $patternQueries);
    }
}
