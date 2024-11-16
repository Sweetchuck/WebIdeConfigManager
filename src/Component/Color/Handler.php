<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Component\Color;

use Sweetchuck\WebIdeConfigManager\Component\BaseHandler;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class Handler extends BaseHandler
{
    public function getConfigSubDir(): string
    {
        return 'colors';
    }

    /**
     * @return iterable<\Symfony\Component\Finder\SplFileInfo>
     */
    public function collectItemsFromConfigDir(string $configDir): iterable
    {
        $dir = Path::join($configDir, $this->getConfigSubDir());
        if (!is_dir($dir)) {
            return [];
        }

        return (new Finder())
            ->in($dir)
            ->files()
            ->name('*.icls');
    }
}
