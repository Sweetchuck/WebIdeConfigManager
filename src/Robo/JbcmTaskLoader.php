<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Robo;

use Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPullSingleTask;
use Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPullTask;
use Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPushTask;
use Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPullSingleTask;
use Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPullTask;
use Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPushTask;
use Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPullSingleTask;
use Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPullTask;
use Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPushTask;

/**
 * @method \Robo\Collection\CollectionBuilder|\Robo\Contract\TaskInterface task(string $className, ...$args)
 */
trait JbcmTaskLoader
{

    // region Component: Colors.
    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPullTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmColorsPull()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPullTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(ColorsPullTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }

    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPullSingleTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmColorsPullSingle()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPullSingleTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(ColorsPullSingleTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }

    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPushTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmColorsPush()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\ColorsPushTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(ColorsPushTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }
    // endregion

    // region Component: FileTemplates.
    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPullTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmFileTemplatesPull()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPullTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(FileTemplatesPullTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }

    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPullSingleTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmFileTemplatesPullSingle()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPullSingleTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(FileTemplatesPullSingleTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }

    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPushTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmFileTemplatesPush()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\FileTemplatesPushTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(FileTemplatesPushTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }
    // endregion

    // region Component: Templates.
    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPullTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmTemplatesPull()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPullTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(TemplatesPullTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }

    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPullSingleTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmTemplatesPullSingle()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPullSingleTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(TemplatesPullSingleTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }

    /**
     * @return \Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPushTask|\Robo\Collection\CollectionBuilder
     */
    protected function taskJbcmTemplatesPush()
    {
        /** @var \Sweetchuck\WebIdeConfigManager\Robo\Task\TemplatesPushTask|\Robo\Collection\CollectionBuilder $task */
        $task = $this->task(TemplatesPushTask::class);
        // @phpstan-ignore-next-line
        $task->setContainer($this->getContainer());

        return $task;
    }
    // endregion
}
