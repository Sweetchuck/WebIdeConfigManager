<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Robo\Task;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Sweetchuck\WebIdeConfigManager\Component\FileTemplate\Handler as FileTemplateHandler;

/**
 * @phpstan-import-type JbcmTidy from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
abstract class FileTemplatesPullTaskBase extends TaskBase
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

    protected FileTemplateHandler $handler;

    protected Filesystem $fs;

    protected function runPrepare(): static
    {
        parent::runPrepare();
        $this->fs = $this->getContainer()->get('symfony.filesystem');
        $this->handler = $this->getContainer()->get('app.fileTemplate.handler');

        return $this;
    }

    protected function runAction(): static
    {
        $productDir = $this->getProductDir();
        $repository = $this->getRepository();

        $logger = $this->getLogger();
        $logger->notice(
            'Pull fileTemplates: {src} => {dst}',
            [
                'src' => $productDir,
                'dst' => $repository['path'],
            ],
        );

        $productFileTemplatesDir = Path::join($productDir, $this->handler->getConfigSubDir());
        foreach ($this->getFilesToPull() as $srcRelativeFilepath) {
            $srcFilepath = Path::join($productFileTemplatesDir, $srcRelativeFilepath);
            if (!$this->fs->exists($srcFilepath)) {
                $logger->notice(
                    'Source file not found: {file.path}',
                    [
                        'file.path' => $srcFilepath,
                    ],
                );

                continue;
            }

            $dstFilepath = Path::join(
                $repository['path'],
                $this->handler->getConfigSubDir(),
                $srcRelativeFilepath,
            );
            $logger->info(
                'Copying file: {src} => {dst}',
                [
                    'src' => $srcFilepath,
                    'dst' => $dstFilepath,
                ],
            );

            $this->fs->dumpFile(
                $dstFilepath,
                (string) file_get_contents($srcFilepath),
            );
        }

        return $this;
    }

    /**
     * @return iterable<string>
     */
    abstract protected function getFilesToPull(): iterable;
}
