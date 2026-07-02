<?php

declare(strict_types=1);

namespace Magento\Framework\Translate;

use Magento\Framework\Phrase\RendererInterface;

/**
 * Translation service
 */
class Translator
{
    /**
     * @var array
     */
    private $data = [];

    /**
     * @var RendererInterface
     */
    private $renderer;

    /**
     * Translator constructor.
     *
     * @param RendererInterface $renderer
     */
    public function __construct(RendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Add translation data
     *
     * @param array $data
     * @return void
     */
    public function addData(array $data): void
    {
        foreach ($data as $module => $translations) {
            if (!isset($this->data[$module])) {
                $this->data[$module] = [];
            }
            $this->data[$module] = array_merge($this->data[$module], $translations);
        }
    }

    /**
     * Translate phrase
     *
     * @param string $text
     * @param array $arguments
     * @param string|null $module
     * @return string
     */
    public function translate(string $text, array $arguments = [], ?string $module = null): string
    {
        $translated = $text;

        // Try module-specific translation first
        if ($module && isset($this->data[$module][$text])) {
            $translated = $this->data[$module][$text];
        }
        // Fallback to global translation
        elseif (isset($this->data['__default'][$text])) {
            $translated = $this->data['__default'][$text];
        }

        return $this->renderer->render([$translated], $arguments);
    }

    /**
     * Get all translation data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}
