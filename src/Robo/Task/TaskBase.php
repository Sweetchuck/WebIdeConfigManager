<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Robo\Task;

use Consolidation\AnnotatedCommand\Output\OutputAwareInterface;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Robo\Common\OutputAwareTrait;
use Robo\Result;
use Robo\Task\BaseTask;
use Robo\TaskInfo;

abstract class TaskBase extends BaseTask implements ContainerAwareInterface, OutputAwareInterface
{

    use ContainerAwareTrait;
    use OutputAwareTrait;

    /**
     * @var array<string, mixed>
     */
    protected array $options = [];

    protected int $actionExitCode = 0;

    protected string $actionStdOutput = '';

    protected string $actionStdError = '';

    /**
     * @var array<string, mixed>
     */
    protected array $assets = [];

    // region assetNameTemplate
    protected string $assetNameTemplate = '{{ key }}';

    public function getAssetNameTemplate(): string
    {
        return $this->assetNameTemplate;
    }

    public function setAssetNameTemplate(string $assetNameTemplate): static
    {
        $this->assetNameTemplate = $assetNameTemplate;

        return $this;
    }
    // endregion

    protected string $taskName = 'Pull templates';

    public function getTaskName(): string
    {
        return $this->taskName ?: TaskInfo::formatTaskName($this);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): static
    {
        return $this;
    }

    protected function initOptions(): static
    {
        $this->options = [];

        return $this;
    }

    protected function getLogger(): LoggerInterface
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Robo\Result<string, mixed>
     */
    public function run()
    {
        return $this
            ->runPrepare()
            ->runHeader()
            ->runAction()
            ->runProcessOutputs()
            ->runReturn();
    }

    protected function runPrepare(): static
    {
        $this->initOptions();

        return $this;
    }

    protected function runHeader(): static
    {
        return $this;
    }

    abstract protected function runAction(): static;

    protected function runProcessOutputs(): static
    {
        return $this;
    }

    /**
     * @return \Robo\Result<string, mixed>
     */
    protected function runReturn(): Result
    {
        return new Result(
            $this,
            $this->getTaskResultCode(),
            $this->getTaskResultMessage(),
            $this->getAssetsWithPrefixedNames(),
        );
    }

    /**
     * @param null|array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    protected function getTaskContext($context = null)
    {
        if (!$context) {
            $context = [];
        }

        if (empty($context['name'])) {
            $context['name'] = $this->getTaskName();
        }

        return parent::getTaskContext($context);
    }

    protected function getTaskResultCode(): int
    {
        return $this->actionExitCode;
    }

    protected function getTaskResultMessage(): string
    {
        return $this->actionStdError;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getAssetsWithPrefixedNames(): array
    {
        $assetNameTemplate = $this->getAssetNameTemplate();

        if ($assetNameTemplate === '{{ key }}') {
            return $this->assets;
        }

        $assets = [];
        foreach ($this->assets as $key => $value) {
            $replacementPairs = [
                '{{ key }}' => $key,
            ];
            $key = strtr($assetNameTemplate, $replacementPairs);
            $assets[$key] = $value;
        }

        return $assets;
    }
}
