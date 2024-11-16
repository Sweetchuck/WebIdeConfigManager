<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Util;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

/**
 * @phpstan-import-type JbcmTidy from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatus from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class Helper
{

    /**
     * @return string[]
     */
    public const array SUPPORTED_PRODUCTS = [
        'PhpStorm',
    ];

    /**
     * @todo Maybe supported components are depend on the product.
     */
    public const array SUPPORTED_COMPONENTS = [
        'colors',
        'fileTemplates',
        'templates',
    ];

    public function normalizeProductName(string $productName): string
    {
        // @todo Decide what to do with invalid argument.
        return match (mb_strtolower($productName)) {
            'phpstorm' => 'PhpStorm',
            default => $productName,
        };
    }

    public function normalizeComponentName(string $componentName): string
    {
        /* @noinspection SpellCheckingInspection */
        return match (mb_strtolower($componentName)) {
            'color',
            'colors' => 'colors',
            'filetemplate',
            'filetemplates' => 'fileTemplates',
            'template',
            'templates' => 'templates',
            default => $componentName,
        };
    }

    public function osFamily(): string
    {
        return \PHP_OS_FAMILY;
    }

    /**
     * @param array<string, string> $envVars
     */
    public function jetBrainsDir(array $envVars): ?string
    {
        $osFamily = $this->osFamily();
        if ($osFamily === 'Windows') {
            if (!empty($envVars['APPDATA'])) {
                return Path::join($envVars['APPDATA'], 'JetBrains');
            }

            return !empty($envVars['HOME'])
                ? Path::join($envVars['HOME'], 'AppData', 'Roaming', 'JetBrains')
                : null;
        }

        if ($osFamily === 'Darwin') {
            return !empty($envVars['HOME'])
                ? Path::join($envVars['HOME'], 'Library', 'Application Support', 'JetBrains')
                : null;
        }

        return !empty($envVars['HOME'])
            ? Path::join($envVars['HOME'], '.config', 'JetBrains')
            : null;
    }

    /**
     * @phpstan-param JbcmTidy $global
     * @phpstan-param JbcmTidy $local
     *
     * @phpstan-return JbcmTidy
     */
    public function getFinalTidy(
        string $strategy,
        array $global,
        array $local
    ): array {
        $default = [
            'enabled' => true,
            'options' => [],
        ];
        switch ($strategy) {
            case 'global':
                $final = $global + $default;
                break;

            case 'merge':
                $final = $local + $global + $default;
                $final['options'] += $global['options'] ?? [];
                break;

            case 'override':
                $final = $local + $default;
                break;

            default:
                $final = $default;
                break;
        }

        return $final;
    }

    public function removeXmlHeader(string $xml): string
    {
        $pattern = sprintf(
            '/^%s.*?%s\n/',
            preg_quote('<?xml', '/'),
            preg_quote('?>', '/'),
        );

        return preg_replace($pattern, '', $xml);
    }

    /**
     * @param array<string, string> $envVars
     *
     * @todo Support for Java properties (idea.config.path).
     *
     * @see https://www.jetbrains.com/help/phpstorm/directories-used-by-the-ide-to-store-settings-caches-plugins-and-logs.html
     */
    public function getProductDir(array $envVars, string $productName): ?string
    {
        $jetBrainsDir = $this->jetBrainsDir($envVars);
        if (!$jetBrainsDir || !is_dir($jetBrainsDir)) {
            return null;
        }

        $productHomes = (new Finder())
            ->in($jetBrainsDir)
            ->directories()
            ->depth(0)
            ->name("$productName*")
            ->sortByName()
            ->reverseSorting();
        foreach ($productHomes as $productHome) {
            return $productHome->getPathname();
        }

        return null;
    }

    /**
     * @phpstan-param JbcmProductStatus $status
     */
    public function prepareProductStatusForSerialize(array &$status): static
    {
        foreach (static::SUPPORTED_COMPONENTS as $componentName) {
            foreach ($status[$componentName]['files'] as &$entry) {
                $entry['status'] = $entry['status']->name;

                if (isset($entry['officialFile'])) {
                    $entry['officialFile'] = $entry['officialFile']->getPathname();
                }

                foreach ($entry['repositories'] as &$repository) {
                    $repository['localFile'] = $repository['localFile']->getPathname();
                }
            }
        }

        return $this;
    }
}
