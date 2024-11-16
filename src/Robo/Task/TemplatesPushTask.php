<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Robo\Task;

use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-import-type JbcmTidy       from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class TemplatesPushTask extends TaskBase
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

    protected function runAction(): static
    {
        $tidy = $this->getTidy();
        $productDir = $this->getProductDir();
        $repository = $this->getRepository();

        $logger = $this->getLogger();
        $logger->notice(
            'Push templates with {tidy.status} Tidy status: {src} => {dst}',
            [
                'tidy.status' => empty($tidy['enabled']) ? 'disabled' : 'enabled',
                'src' => $productDir,
                'dst' => $repository['path'],
            ],
        );

        /** @var \Symfony\Component\Filesystem\Filesystem $fs */
        $fs = $this->getContainer()->get('symfony.filesystem');
        /** @var \Sweetchuck\WebIdeConfigManager\Component\Template\Handler $templateHandler */
        $templateHandler = $this->getContainer()->get('app.template.handler');
        $templateHandler
            ->setTidyEnabled(!empty($tidy['enabled']))
            ->setTidyOptions($tidy['options'] ?? []);

        $configSubDirPath = Path::join($productDir, 'templates');
        $templateFiles = $templateHandler->collectItemsFromRepository($repository);
        foreach ($templateFiles as $templateFile) {
            $dstFilePath = Path::join($configSubDirPath, $templateFile->getRelativePathname());
            $action = $fs->exists($dstFilePath) ? 'update' : 'create';

            $logger->notice(
                'Push {action}: {src} => {dst}',
                [
                    'action' => $action,
                    'src' => $templateFile->getPathname(),
                    'dst' => $dstFilePath,
                ],
            );

            $deactivatedStates = [];
            if ($fs->exists($dstFilePath)) {
                $dstContent = (string) file_get_contents($dstFilePath);
                $deactivatedStates = $templateHandler->getDeactivatedStates($dstContent);
            }

            $srcContent = (string) file_get_contents($templateFile->getPathname());
            $fs->dumpFile(
                $dstFilePath,
                $templateHandler->convertToOfficial($srcContent, $deactivatedStates),
            );
        }

        return $this;
    }
}
