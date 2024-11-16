<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Robo\Task;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Sweetchuck\WebIdeConfigManager\Component\Template\Handler as TemplateHandler;

/**
 * @phpstan-import-type JbcmTidy from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
abstract class TemplatesPullTaskBase extends TaskBase
{

    // region tidy
    /**
     * @phpstan-var JbcmTidy
     */
    protected array $tidy = [];

    /**
     *
     * @phpstan-return JbcmTidy
     */
    public function getTidy(): array
    {
        return $this->tidy;
    }

    /**
     * @phpstan-param JbcmTidy $tidy
     *
     * @see \Sweetchuck\WebIdeConfigManager\Util\Helper::getFinalTidy
     */
    public function setTidy(array $tidy): static
    {
        $this->tidy = $tidy;

        return $this;
    }
    // endregion

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

    protected TemplateHandler $handler;

    protected Filesystem $fs;

    protected function runPrepare(): static
    {
        parent::runPrepare();
        $this->fs = $this->getContainer()->get('symfony.filesystem');
        $tidy = $this->getTidy();
        $this->handler = $this->getContainer()->get('app.template.handler');
        $this->handler
            ->setTidyEnabled(!empty($tidy['enabled']))
            ->setTidyOptions($tidy['options'] ?? []);

        return $this;
    }

    protected function runAction(): static
    {
        $tidy = $this->getTidy();
        $productDir = $this->getProductDir();
        $repository = $this->getRepository();

        $logger = $this->getLogger();
        $logger->notice(
            'Pull templates with {tidy.status} Tidy status: {src} => {dst}',
            [
                'tidy.status' => empty($tidy['enabled']) ? 'disabled' : 'enabled',
                'src' => $productDir,
                'dst' => $repository['path'],
            ],
        );

        $productTemplatesDir = Path::join($productDir, $this->handler->getConfigSubDir());
        // @todo Separate "pull" task for a single file and reuse it for "adopt".
        foreach ($this->getFilesToPull() as $srcRelativeFilepath) {
            $srcFilepath = Path::join($productTemplatesDir, $srcRelativeFilepath);
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

            $srcContent = (string) file_get_contents($srcFilepath);
            $this->fs->dumpFile(
                $dstFilepath,
                $this->handler->convertToHumanReadable($srcContent),
            );
        }

        return $this;
    }

    /**
     * @return iterable<string>
     */
    abstract protected function getFilesToPull(): iterable;
}
