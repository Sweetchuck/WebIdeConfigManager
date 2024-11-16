<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Robo\Task;

/**
 * @phpstan-import-type JbcmTidy from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class ColorsPullTask extends ColorsPullTaskBase
{

    /**
     * {@inheritdoc}
     */
    protected function getFilesToPull(): iterable
    {
        $files = [];
        foreach ($this->handler->collectItemsFromRepository($this->getRepository()) as $file) {
            $files[] = $file->getRelativePathname();
        }

        return $files;
    }
}
