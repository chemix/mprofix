<?php

namespace MyProfiTests;

use PHPUnit\Framework\TestCase;

/**
 * Class HelperTest
 * @package MyProfiTests
 */
class HelperTest extends TestCase
{

    public function testOutputsIndividualText(): void
    {
        $docFile = __DIR__ . '/../outputs/doc.txt';

        if (!is_readable($docFile)) {
            self::markTestSkipped('file to compare not found');
        }

        $compare = file_get_contents($docFile);

        $mock = $this->getMockBuilder(\MyProfi\Helper::class)
            ->onlyMethods(['output'])
            ->getMock();

        $mock->expects(self::once())
            ->method('output')
            ->willReturnArgument(0);

        self::assertEquals('individual text' . "\n\n" . $compare, $mock->doc('individual text'));
    }
}
