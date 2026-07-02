<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\Catalog\Model\ResourceModel\Eav\Attribute\Plugin;

use Magento\Catalog\Model\ResourceModel\Eav\Attribute as AttributeResource;
use Magento\Eav\Model\Entity\Attribute\Option as AttributeOption;
use Magento\Swatches\Model\ResourceModel\Swatch as SwatchResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Magento\Catalog\Model\ResourceModel\Eav\Attribute\Plugin\SaveSwatchesOptionParams
 */
class SaveSwatchesOptionParamsTest extends TestCase
{
    /** @var SwatchResource|MockObject */
    private $swatchResourceMock;

    /** @var SaveSwatchesOptionParams */
    private $plugin;

    protected function setUp(): void
    {
        $this->swatchResourceMock = $this->createMock(SwatchResource::class);
        $this->plugin = new SaveSwatchesOptionParams($this->swatchResourceMock);
    }

    public function testSwatchKeyAbsentDoesNotTriggerDeleteOrSave(): void
    {
        $option = $this->createMock(AttributeOption::class);
        $option->method('getId')->willReturn(123);
        $option->method('getData')->willReturn(['label' => 'Red']); // no 'swatch' key

        $attribute = $this->createMock(AttributeResource::class);
        $attribute->method('getOptions')->willReturn([$option]);

        $subject = $this->createMock(AttributeResource::class);
        $result = null;

        $this->swatchResourceMock->expects($this->never())
            ->method('deleteSwatch');
        $this->swatchResourceMock->expects($this->never())
            ->method('saveSwatch');

        $this->plugin->afterSave($subject, $result, $attribute);
    }

    public function testEmptySwatchValueTriggersDelete(): void
    {
        $option = $this->createMock(AttributeOption::class);
        $option->method('getId')->willReturn(456);
        $option->method('getData')->willReturn(['label' => 'Blue', 'swatch' => null]);

        $attribute = $this->createMock(AttributeResource::class);
        $attribute->method('getOptions')->willReturn([$option]);

        $subject = $this->createMock(AttributeResource::class);
        $result = null;

        $this->swatchResourceMock->expects($this->once())
            ->method('deleteSwatch')
            ->with(456);
        $this->swatchResourceMock->expects($this->never())
            ->method('saveSwatch');

        $this->plugin->afterSave($subject, $result, $attribute);
    }
}
