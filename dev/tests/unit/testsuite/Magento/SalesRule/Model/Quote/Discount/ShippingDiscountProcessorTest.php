<?php
declare(strict_types=1);

namespace Magento\SalesRule\Test\Unit\Model\Quote\Discount;

use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Quote\Discount\ShippingDiscountProcessor;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Magento\SalesRule\Model\Quote\Discount\ShippingDiscountProcessor
 */
class ShippingDiscountProcessorTest extends TestCase
{
    /** @var RuleRepository|\PHPUnit\Framework\MockObject\MockObject */
    private $ruleRepositoryMock;

    /** @var ShippingDiscountProcessor */
    private $processor;

    protected function setUp(): void
    {
        $this->ruleRepositoryMock = $this->createMock(RuleRepository::class);
        $this->processor = new ShippingDiscountProcessor($this->ruleRepositoryMock);
    }

    public function testStopRulesProcessingPreventsLaterShippingDiscount(): void
    {
        // First rule – does NOT apply to shipping but has stop_rules_processing = true
        $firstRule = $this->createMock(Rule::class);
        $firstRule->method('getStopRulesProcessing')->willReturn(true);
        $firstRule->method('getIsActive')->willReturn(true);
        $firstRule->method('getApplyToShipping')->willReturn(false);
        $firstRule->method('getDiscountAmount')->willReturn(10);
        $firstRule->method('getSimpleAction')->willReturn(Rule::DISCOUNT_FIXED);

        // Second rule – applies to shipping, should be ignored because of stop flag
        $secondRule = $this->createMock(Rule::class);
        $secondRule->method('getStopRulesProcessing')->willReturn(false);
        $secondRule->method('getIsActive')->willReturn(true);
        $secondRule->method('getApplyToShipping')->willReturn(true);
        $secondRule->method('getDiscountAmount')->willReturn(20);
        $secondRule->method('getSimpleAction')->willReturn(Rule::DISCOUNT_FIXED);

        // Rule repository returns the proper rule based on ID
        $this->ruleRepositoryMock->expects($this->any())
            ->method('getById')
            ->willReturnCallback(function (int $id) use ($firstRule, $secondRule) {
                return $id === 1 ? $firstRule : $secondRule;
            });

        // Quote mock – shipping discount amount starts at 0
        $quote = $this->createMock(Quote::class);
        $quote->method('getAppliedRuleIds')->willReturn('1,2');
        $quote->method('getShippingDiscountAmount')->willReturn(0.0);
        $quote->method('getBaseShippingDiscountAmount')->willReturn(0.0);
        $quote->method('setShippingDiscountAmount')->with($this->callback(function ($value) {
            // After processing the value must still be 0 because second rule is ignored
            $this->assertEquals(0.0, $value);
            return true;
        }))->willReturnSelf();
        $quote->method('setBaseShippingDiscountAmount')->with($this->callback(function ($value) {
            $this->assertEquals(0.0, $value);
            return true;
        }))->willReturnSelf();

        // Shipping address mock – not used because second rule never runs
        $address = $this->createMock(Address::class);
        $quote->method('getShippingAddress')->willReturn($address);

        // Execute processor
        $this->processor->collect($quote);
    }
}
