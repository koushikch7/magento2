<?php
declare(strict_types=1);

namespace Magento\Framework\View\Asset;

use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the Underscore UMD asset.
 *
 * Ensures that the generated JavaScript file does not contain a source‑mapping
 * comment pointing to a non‑existent *.js.map file.
 */
class UnderscoreUmdTest extends TestCase
{
    /** @var Repository */
    private $assetRepository;

    protected function setUp(): void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->assetRepository = $objectManager->get(Repository::class);
    }

    public function testUnderscoreUmdDoesNotContainSourceMap(): void
    {
        $asset = $this->assetRepository->createAsset('underscore-umd.js');
        $sourceFile = $asset->getSourceFile();
        $this->assertNotFalse($sourceFile, 'Source file should exist');
        $content = file_get_contents($sourceFile);
        $this->assertIsString($content, 'Unable to read asset content');
        $this->assertStringNotContainsString(
            'sourceMappingURL=underscore-umd.js.map',
            $content,
            'Underscore UMD file should not contain a source‑mapping comment.'
        );
    }
}
