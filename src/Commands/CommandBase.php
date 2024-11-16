<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Attributes as Cli;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Robo\Collection\Tasks as LoopTaskLoader;
use Robo\Common\ConfigAwareTrait;
use Robo\Common\IO;
use Robo\Contract\BuilderAwareInterface;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\IOAwareInterface;
use Robo\Task\Base\Tasks as CommonTaskLoader;
use Robo\TaskAccessor;
use Sweetchuck\WebIdeConfigManager\Util\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
abstract class CommandBase implements
    BuilderAwareInterface,
    ConfigAwareInterface,
    ContainerAwareInterface,
    IOAwareInterface,
    LoggerAwareInterface
{
    use ConfigAwareTrait;
    use ContainerAwareTrait;
    use LoggerAwareTrait;
    use IO;
    use TaskAccessor;
    use CommonTaskLoader;
    use LoopTaskLoader;

    protected Filesystem $fs;

    protected Helper $helper;

    protected function selfRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public function getLogger(): LoggerInterface
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    protected function init(InputInterface $input, AnnotationData $annotationData): static
    {
        $this->initInjectDependencies();

        return $this;
    }

    protected function initInjectDependencies(): static
    {
        $container = $this->getContainer();
        $this->fs = $container->get('symfony.filesystem');
        $this->helper = $container->get('app.helper');

        return $this;
    }

    /**
     * @param \Consolidation\AnnotatedCommand\AnnotationData<string, mixed> $annotationData
     */
    #[Cli\Hook(
        type: HookManager::INITIALIZE,
        target: '*',
    )]
    public function cmdAllInit(InputInterface $input, AnnotationData $annotationData): void
    {
        $this->init($input, $annotationData);
    }

    /**
     * @phpstan-return array<array-key, JbcmRepository>
     */
    protected function getRepositories(string $productName): array
    {
        $comparer = $this->getContainer()->get('app.repository.comparer');
        $config = $this->getConfig()->export();
        $repositories = $config['products'][$productName]['repositories'] ?? [];
        uasort($repositories, $comparer);

        return $repositories;
    }

    /**
     * @phpstan-return array<array-key, JbcmRepository>
     */
    protected function getEnabledRepositories(string $productName): array
    {
        return array_filter(
            $this->getRepositories($productName),
            fn(array $repository): bool => $repository['enabled'] && $this->fs->exists($repository['path']),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function getEnvVars(): array
    {
        return (array) $this->getConfig()->get('env');
    }

    /**
     * @return array<string, string>
     */
    protected function getProductOptions(): array
    {
        $config = $this->getConfig()->export();
        $supportedProducts = Helper::SUPPORTED_PRODUCTS;
        $options = [];
        foreach (array_keys($config['products']) as $productName) {
            $productName = $this->helper->normalizeProductName((string) $productName);
            if (in_array($productName, $supportedProducts)) {
                $options[$productName] = $productName;
            }
        }

        return $options;
    }
}
