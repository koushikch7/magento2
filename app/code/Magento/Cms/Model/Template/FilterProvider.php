<?php
/**
 * Copyright 2013 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Cms\Model\Template;

use Magento\Framework\Filter\Template as TemplateFilter;
use Magento\Framework\ObjectManagerInterface;

/**
 * Class Cms Template Filter Provider
 *
 * Provides cached instances of template filters used for CMS pages and blocks.
 *
 * @api
 */
class FilterProvider
{
    /**
     * Object manager used to instantiate filter classes.
     *
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Fully qualified class name of the page filter.
     *
     * @var string
     */
    private $pageFilterClass;

    /**
     * Cached instance of the page filter.
     *
     * @var TemplateFilter|null
     */
    private $pageFilterInstance = null;

    /**
     * Fully qualified class name of the block filter.
     *
     * @var string
     */
    private $blockFilterClass;

    /**
     * Cache of already created filter instances indexed by class name.
     *
     * @var TemplateFilter[]
     */
    private $instanceList = [];

    /**
     * @param ObjectManagerInterface $objectManager
     * @param string $pageFilter Fully qualified class name of the page filter. Defaults to
     *     {@see \Magento\Cms\Model\Template\Filter::class}.
     * @param string $blockFilter Fully qualified class name of the block filter. Defaults to
     *     {@see \Magento\Cms\Model\Template\Filter::class}.
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        $pageFilter = \Magento\Cms\Model\Template\Filter::class,
        $blockFilter = \Magento\Cms\Model\Template\Filter::class
    ) {
        $this->objectManager   = $objectManager;
        $this->pageFilterClass = $pageFilter;
        $this->blockFilterClass = $blockFilter;
    }

    /**
     * Retrieve (and cache) a filter instance.
     *
     * @param string $instanceName Fully qualified class name of the filter.
     * @return TemplateFilter
     * @throws \Exception If the resolved class does not implement the required interface.
     */
    private function getFilterInstance(string $instanceName): TemplateFilter
    {
        if (!isset($this->instanceList[$instanceName])) {
            $instance = $this->objectManager->get($instanceName);
            if (!$instance instanceof TemplateFilter) {
                throw new \Exception('Template filter ' . $instanceName . ' does not implement required interface');
            }
            $this->instanceList[$instanceName] = $instance;
        }
        return $this->instanceList[$instanceName];
    }

    /**
     * Return a cached page filter instance.
     *
     * The first call creates the filter via the object manager; subsequent calls return the same
     * instance, preventing repeated construction of heavy dependencies such as StoreResolver and
     * ScopeConfig.
     *
     * @return TemplateFilter
     * @throws \Exception
     */
    public function getPageFilter(): TemplateFilter
    {
        if ($this->pageFilterInstance === null) {
            $instance = $this->objectManager->get($this->pageFilterClass);
            if (!$instance instanceof TemplateFilter) {
                throw new \Exception('Template filter ' . $this->pageFilterClass . ' does not implement required interface');
            }
            $this->pageFilterInstance = $instance;
        }
        return $this->pageFilterInstance;
    }

    /**
     * Return a cached block filter instance.
     *
     * Block filters are cached per class name using the generic instance cache.
     *
     * @return TemplateFilter
     */
    public function getBlockFilter(): TemplateFilter
    {
        return $this->getFilterInstance($this->blockFilterClass);
    }
}
