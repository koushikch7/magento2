<?php

declare(strict_types=1);

namespace Magento\Framework\Translate;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Directory\ReadInterface;

/**
 * CSV translation loader
 */
class Loader
{
    /**
     * Load translation data from CSV file
     *
     * @param ReadInterface $directory
     * @param string $file
     * @return array
     * @throws LocalizedException
     */
    public function loadDataFromCsv(ReadInterface $directory, string $file): array
    {
        if (!$directory->isExist($file)) {
            return [];
        }

        $data = [];
        $stream = $directory->openFile($file, 'r');
        try {
            while (($row = $stream->readCsv()) !== false) {
                if (!is_array($row) || count($row) < 2) {
                    continue;
                }

                $original = trim($row[0]);
                $translated = trim($row[1]);

                if ($original === '' || $translated === '') {
                    continue;
                }

                // Check for optional module column (4th column)
                $module = isset($row[3]) ? trim($row[3]) : '';

                if ($module !== '') {
                    // Store module-specific translation
                    $data[$module][$original] = $translated;
                } else {
                    // Global translation (backward compatibility)
                    $data['__default'][$original] = $translated;
                }
            }
        } finally {
            $stream->close();
        }

        return $data;
    }
}
