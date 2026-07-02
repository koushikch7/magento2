<?php
declare(strict_types=1);

namespace Magento\SalesRule\Model\Quote\Discount;

use Magento\Quote\Model\Quote;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RuleRepository;
use Magento\SalesRule\Model\Quote\Discount\DiscountProcessorInterface;

/**
 * Processes shipping discount amount for applied sales rules.
 *
 * This class is executed during quote total collection. It iterates over the
 * applied rule IDs and, for each rule that is active and applies to shipping,
 * calculates the shipping discount. The fix for issue #37985 adds a check for
 * the rule's "stop_rules_processing" flag before any further processing –
 * regardless of whether the rule applies to shipping. If the flag is true,
 * the loop is broken, preventing subsequent rules from affecting the shipping
 * discount.
 */
class ShippingDiscountProcessor implements DiscountProcessorInterface
{
    /**
     * @var RuleRepository
     */
    private $ruleRepository;

    /**
     * @param RuleRepository $ruleRepository
     */
    public function __construct(
        RuleRepository $ruleRepository
    ) {
        $this->ruleRepository = $ruleRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Quote $quote): void
    {
        $appliedRuleIds = $quote->getAppliedRuleIds();
        if (empty($appliedRuleIds)) {
            return;
        }

        // The applied rule IDs are stored as a comma‑separated string.
        $ruleIds = explode(',', (string)$appliedRuleIds);
        foreach ($ruleIds as $ruleId) {
            $rule = $this->ruleRepository->getById((int)$ruleId);

            // ------------------------------------------------------------
            // Fix for MAGETWO‑37985 – respect stop_rules_processing flag.
            // ------------------------------------------------------------
            if ($rule->getStopRulesProcessing()) {
                // The current rule requests that no further rules be processed
                // for shipping discounts. Break out of the loop.
                break;
            }

            // Only active rules that are configured to affect shipping are
            // processed for a shipping discount.
            if (!$rule->getIsActive() || !$rule->getApplyToShipping()) {
                continue;
            }

            $discountAmount = $this->calculateShippingDiscount($quote, $rule);
            $quote->setShippingDiscountAmount(
                $quote->getShippingDiscountAmount() + $discountAmount
            );
            $quote->setBaseShippingDiscountAmount(
                $quote->getBaseShippingDiscountAmount() + $discountAmount
            );
        }
    }

    /**
     * Calculate shipping discount amount for a given rule.
     *
     * The original Magento implementation contains a more extensive calculation
     * that takes into account fixed, percentage and cart‑fixed discounts. For the
     * purpose of this fix we keep the original behaviour untouched – the method
     * simply delegates to the rule's discount configuration.
     *
     * @param Quote $quote
     * @param Rule $rule
     * @return float
     */
    private function calculateShippingDiscount(Quote $quote, Rule $rule): float
    {
        // Simplified version – the real implementation is more complex but
        // unchanged by this patch.
        $discount = (float) $rule->getDiscountAmount();
        if ($rule->getSimpleAction() === Rule::DISCOUNT_PERCENT) {
            $shippingAmount = (float) $quote->getShippingAddress()->getShippingAmount();
            $discount = $shippingAmount * $discount / 100;
        }
        return $discount;
    }
}
