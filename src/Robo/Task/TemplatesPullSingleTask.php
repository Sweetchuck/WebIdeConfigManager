<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Robo\Task;

/**
 * @phpstan-import-type JbcmTidy from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class TemplatesPullSingleTask extends TemplatesPullTaskBase
{

    // region relativeFilepath
    protected string $relativeFilepath = '';

    public function getRelativeFilepath(): string
    {
        return $this->relativeFilepath;
    }

    public function setRelativeFilepath(string $relativeFilepath): static
    {
        $this->relativeFilepath = $relativeFilepath;

        return $this;
    }
    // endregion

    /**
     * {@inheritdoc}
     */
    protected function getFilesToPull(): iterable
    {
        return [
            $this->getRelativeFilepath(),
        ];
    }
}
