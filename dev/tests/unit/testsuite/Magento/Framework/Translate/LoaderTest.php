<?php

declare(strict_types=1);

namespace Magento\Framework\Translate;

use Magento\Framework\Filesystem\Directory\ReadInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for CSV translation loader
 */
class LoaderTest extends TestCase
{
    /**
     * @var Loader
     */
    private $loader;

    protected function setUp(): void
    {
        $this->loader = new Loader();
    }

    public function testLoadDataFromCsvWithModuleColumn(): void
    {
        $directory = $this->createMock(ReadInterface::class);
        $stream = $this->createMock(\Magento\Framework\Filesystem\File\ReadInterface::class);

        $directory->expects($this->once())
            ->method('isExist')
            ->willReturn(true);
        $directory->expects($this->once())
            ->method('openFile')
            ->willReturn($stream);

        $stream->method('readCsv')
            ->will($this->onConsecutiveCalls(
                ['Original Text 1', 'Translated Text 1', '', 'Vendor_Module1'],
                ['Original Text 2', 'Translated Text 2', '', 'Vendor_Module2'],
                ['Global Text', 'Global Translation'],
                false
            ));
        $stream->expects($this->once())->method('close');

        $result = $this->loader->loadDataFromCsv($directory, 'test.csv');

        $this->assertArrayHasKey('Vendor_Module1', $result);
        $this->assertArrayHasKey('Original Text 1', $result['Vendor_Module1']);
        $this->assertEquals('Translated Text 1', $result['Vendor_Module1']['Original Text 1']);

        $this->assertArrayHasKey('Vendor_Module2', $result);
        $this->assertArrayHasKey('Original Text 2', $result['Vendor_Module2']);
        $this->assertEquals('Translated Text 2', $result['Vendor_Module2']['Original Text 2']);

        $this->assertArrayHasKey('__default', $result);
        $this->assertArrayHasKey('Global Text', $result['__default']);
        $this->assertEquals('Global Translation', $result['__default']['Global Text']);
    }

    public function testLoadDataFromCsvWithoutModuleColumn(): void
    {
        $directory = $this->createMock(ReadInterface::class);
        $stream = $this->createMock(\Magento\Framework\Filesystem\File\ReadInterface::class);

        $directory->expects($this->once())
            ->method('isExist')
            ->willReturn(true);
        $directory->expects($this->once())
            ->method('openFile')
            ->willReturn($stream);

        $stream->method('readCsv')
            ->will($this->onConsecutiveCalls(
                ['Original Text', 'Translated Text'],
                false
            ));
        $stream->expects($this->once())->method('close');

        $result = $this->loader->loadDataFromCsv($directory, 'test.csv');

        $this->assertArrayHasKey('__default', $result);
        $this->assertArrayHasKey('Original Text', $result['__default']);
        $this->assertEquals('Translated Text', $result['__default']['Original Text']);
    }

    public function testLoadDataFromCsvEmptyFile(): void
    {
        $directory = $this->createMock(ReadInterface::class);
        $directory->expects($this->once())
            ->method('isExist')
            ->willReturn(false);

        $result = $this->loader->loadDataFromCsv($directory, 'nonexistent.csv');
        $this->assertEquals([], $result);
    }
}
