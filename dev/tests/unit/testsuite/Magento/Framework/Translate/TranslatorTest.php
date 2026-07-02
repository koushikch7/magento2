<?php

declare(strict_types=1);

namespace Magento\Framework\Translate;

use Magento\Framework\Phrase\RendererInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test for translation service
 */
class TranslatorTest extends TestCase
{
    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var RendererInterface|MockObject
     */
    private $rendererMock;

    protected function setUp(): void
    {
        $this->rendererMock = $this->createMock(RendererInterface::class);
        $this->translator = new Translator($this->rendererMock);
    }

    public function testTranslateWithModuleContext(): void
    {
        $data = [
            'Vendor_Module' => ['Test Phrase' => 'Module Translation'],
            '__default' => ['Test Phrase' => 'Global Translation']
        ];
        $this->translator->addData($data);

        $this->rendererMock->expects($this->once())
            ->method('render')
            ->with(['Module Translation'], [])
            ->willReturn('Module Translation');

        $result = $this->translator->translate('Test Phrase', [], 'Vendor_Module');
        $this->assertEquals('Module Translation', $result);
    }

    public function testTranslateFallbackToGlobal(): void
    {
        $data = [
            '__default' => ['Test Phrase' => 'Global Translation']
        ];
        $this->translator->addData($data);

        $this->rendererMock->expects($this->once())
            ->method('render')
            ->with(['Global Translation'], [])
            ->willReturn('Global Translation');

        $result = $this->translator->translate('Test Phrase', [], 'Vendor_Module');
        $this->assertEquals('Global Translation', $result);
    }

    public function testTranslateWithoutTranslation(): void
    {
        $this->rendererMock->expects($this->once())
            ->method('render')
            ->with(['Test Phrase'], [])
            ->willReturn('Test Phrase');

        $result = $this->translator->translate('Test Phrase');
        $this->assertEquals('Test Phrase', $result);
    }
}
