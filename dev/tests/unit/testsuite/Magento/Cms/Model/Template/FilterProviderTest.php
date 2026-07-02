<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Cms\Test\Unit\Model\Template;

use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Filter\Template as TemplateFilter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test class for \Magento\Cms\Model\Template\FilterProvider.
 */
class FilterProviderTest extends TestCase
{
    /** @var ObjectManagerInterface|MockObject */
    private $objectManagerMock;

    /** @var FilterProvider */
    private $filterProvider;

    /** @var TemplateFilter|MockObject */
    private $filterMock;

    protected function setUp(): void
    {
        $this->objectManagerMock = $this->createMock(ObjectManagerInterface::class);
        $this->filterMock = $this->createMock(TemplateFilter::class);

        // Expect the object manager to be called only once for the page filter class.
        $this->objectManagerMock->expects($this->once())
            ->method('get')
            ->with(\Magento\Cms\Model\Template\Filter::class)
            ->willReturn($this->filterMock);

        $this->filterProvider = new FilterProvider(
            $this->objectManagerMock,
            \Magento\Cms\Model\Template\Filter::class,
            \Magento\Cms\Model\Template\Filter::class
        );
    }

    public function testGetPageFilterReturnsSameInstance(): void
    {
        $first  = $this->filterProvider->getPageFilter();
        $second = $this->filterProvider->getPageFilter();

        $this->assertSame($this->filterMock, $first, 'First call should return the mock created by the object manager');
        $this->assertSame($first, $second, 'Subsequent calls must return the same instance');
    }
}
