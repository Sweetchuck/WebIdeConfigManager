<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Robo\Task;

use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-import-type JbcmTidy       from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class ColorsPushTask extends TaskBase
{

    // region productDir
    protected string $productDir = '';

    public function getProductDir(): string
    {
        return $this->productDir;
    }

    public function setProductDir(string $productDir): static
    {
        $this->productDir = $productDir;

        return $this;
    }
    // endregion

    // region repository
    /**
     * @phpstan-var JbcmRepository
     *
     * @phpstan-ignore-next-line
     */
    protected array $repository = [];

    /**
     * @return JbcmRepository
     */
    public function getRepository(): array
    {
        return $this->repository;
    }

    /**
     * @phpstan-param JbcmRepository $repository
     */
    public function setRepository(array $repository): static
    {
        $this->repository = $repository;

        return $this;
    }
    // endregion

    protected function runAction(): static
    {
        $productDir = $this->getProductDir();
        $repository = $this->getRepository();

        $logger = $this->getLogger();
        $logger->notice(
            'Push fileTemplates: {src} => {dst}',
            [
                'src' => $repository['path'],
                'dst' => $productDir,
            ],
        );

        /** @var \Symfony\Component\Filesystem\Filesystem $fs */
        $fs = $this->getContainer()->get('symfony.filesystem');
        /** @var \Sweetchuck\WebIdeConfigManager\Component\Color\Handler $handler */
        $handler = $this->getContainer()->get('app.colors.handler');

        $configSubDirPath = Path::join($productDir, $handler->getConfigSubDir());
        $files = $handler->collectItemsFromRepository($repository);
        foreach ($files as $file) {
            $dstFilePath = Path::join($configSubDirPath, $file->getRelativePathname());
            $action = $fs->exists($dstFilePath) ? 'update' : 'create';

            $logger->notice(
                'Push {action}: {src} => {dst}',
                [
                    'action' => $action,
                    'src' => $file->getPathname(),
                    'dst' => $dstFilePath,
                ],
            );

            $fs->dumpFile(
                $dstFilePath,
                $file->getContents(),
            );
        }

        return $this;
    }
}
