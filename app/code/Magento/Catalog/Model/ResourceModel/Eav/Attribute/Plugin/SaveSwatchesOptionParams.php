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

/**
 * Plugin that saves visual swatch data for attribute options.
 *
 * The original implementation overwrote swatch data whenever an option is saved.
 * When the REST API updates only the label and omits the `swatch` key, the plugin
 * interpreted the missing value as a request to delete the swatch, resulting in
 * loss of visual swatch information.
 *
 * This plugin now checks whether the incoming option payload explicitly contains
 * a `swatch` key. If the key is absent, the swatch data is left untouched.
 */
class SaveSwatchesOptionParams
{
    /**
     * @var SwatchResource
     */
    private SwatchResource $swatchResource;

    /**
     * @param SwatchResource $swatchResource
     */
    public function __construct(SwatchResource $swatchResource)
    {
        $this->swatchResource = $swatchResource;
    }

    /**
     * After save plugin for \Magento\Catalog\Model\ResourceModel\Eav\Attribute.
     *
     * @param AttributeResource $subject
     * @param mixed $result The original method result (usually void).
     * @param \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute
     * @return mixed
     */
    public function afterSave(
        AttributeResource $subject,
        $result,
        \Magento\Catalog\Model\ResourceModel\Eav\Attribute $attribute
    ) {
        // Retrieve all options attached to the attribute.
        $options = $attribute->getOptions();
        foreach ($options as $option) {
            if (!($option instanceof AttributeOption)) {
                continue;
            }

            $optionId = (int) $option->getId();
            $optionData = $option->getData();

            // If the payload does not contain a "swatch" key, skip any processing.
            if (!array_key_exists('swatch', $optionData)) {
                continue;
            }

            $swatchValue = $optionData['swatch'];
            // Empty value means the caller wants to delete the swatch.
            if ($swatchValue === null || $swatchValue === '' || $swatchValue === []) {
                $this->swatchResource->deleteSwatch($optionId);
                continue;
            }

            // Otherwise store the provided swatch value.
            $this->swatchResource->saveSwatch($optionId, $swatchValue);
        }

        return $result;
    }
}
