<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Component;

use Sweetchuck\WebIdeConfigManager\ConfigStatus;

/**
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
abstract class BaseHandler
{
    abstract public function getConfigSubDir(): string;

    /**
     * @phpstan-param JbcmProduct $product
     *
     * @return iterable<\Symfony\Component\Finder\SplFileInfo>
     */
    public function collectItemsFromProduct(array $product): iterable
    {
        return $this->collectItemsFromConfigDir($product['dir']);
    }

    /**
     * @phpstan-param JbcmRepository $repository
     *
     * @return iterable<\Symfony\Component\Finder\SplFileInfo>
     */
    public function collectItemsFromRepository(array $repository): iterable
    {
        return $this->collectItemsFromConfigDir($repository['path']);
    }

    /**
     * @return iterable<\Symfony\Component\Finder\SplFileInfo>
     */
    abstract public function collectItemsFromConfigDir(string $configDir): iterable;

    /**
     * @param array<string, JbcmRepository> $repositories
     *
     * @return array<string, \Symfony\Component\Finder\SplFileInfo>
     */
    public function getOrphanItems(
        string $productDir,
        array $repositories,
    ): array {
        if (!is_dir($productDir)) {
            return [];
        }
        $officialFiles = [];
        foreach ($this->collectItemsFromConfigDir($productDir) as $file) {
            $officialFiles[$file->getRelativePathname()] = $file;
        }

        foreach ($repositories as $repository) {
            foreach ($this->collectItemsFromConfigDir($repository['path']) as $file) {
                unset($officialFiles[$file->getRelativePathname()]);
            }
        }

        ksort($officialFiles);

        return $officialFiles;
    }


    /**
     * @phpstan-param JbcmProduct $product
     *
     * @return array<string, mixed>
     */
    public function getStatus(array $product): array
    {
        $status = [
            'files' => [],
            'filesByStatus' => [
                ConfigStatus::Orphan->value => [],
                ConfigStatus::New->value => [],
                ConfigStatus::Changed->value => [],
                ConfigStatus::UpToDate->value => [],
            ],
            'filesFromMultipleRepositories' => [],
        ];

        foreach ($this->collectItemsFromProduct($product) as $file) {
            $status['files'][$file->getRelativePathname()] = [
                'officialFile' => $file,
                'status' => ConfigStatus::Orphan,
                'repositories' => [],
            ];
        }

        /** @var JbcmRepository $repository */
        foreach ($product['repositories'] as $repository) {
            foreach ($this->collectItemsFromRepository($repository) as $localFile) {
                $status['files'][$localFile->getRelativePathname()]['repositories'][$repository['key']] = [
                    'localFile' => $localFile,
                ];

                $status['files'][$localFile->getRelativePathname()] += [
                    'officialFile' => null,
                ];
            }
        }

        /** @var string $relativePathname */
        foreach ($status['files'] as $relativePathname => $entry) {
            $firstRepositoryKey = array_key_first($entry['repositories']);
            $configStatus = $this->getConfigStatus(
                $entry['officialFile'],
                $entry['repositories'][$firstRepositoryKey]['localFile'] ?? null,
            );

            $status['files'][$relativePathname]['status'] = $configStatus;
            $status['filesByStatus'][$configStatus->value][] = $relativePathname;

            if (count($entry['repositories']) > 1) {
                $status['filesFromMultipleRepositories'][] = $relativePathname;
            }
        }

        ksort($status['files']);

        return $status;
    }

    protected function getConfigStatus(
        ?\SplFileInfo $officialFile,
        ?\SplFileInfo $localFile,
    ): ConfigStatus {
        if ($officialFile === null) {
            return ConfigStatus::New;
        }

        if ($localFile === null) {
            return ConfigStatus::Orphan;
        }

        $officialContent = (string) file_get_contents($officialFile->getPathname());
        $localContent = (string) file_get_contents($localFile->getPathname());

        return $officialContent === $localContent
            ? ConfigStatus::UpToDate
            : ConfigStatus::Changed;
    }
}
