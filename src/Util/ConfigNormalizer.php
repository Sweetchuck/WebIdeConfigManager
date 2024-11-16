<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Util;

use Consolidation\Config\ConfigInterface;
use Symfony\Component\Filesystem\Path;

class ConfigNormalizer
{

    public function __construct(
        protected Helper $helper,
    ) {
    }

    public function normalize(ConfigInterface $config): static
    {
        $this->normalizeProducts($config);

        return $this;
    }

    protected function normalizeProducts(ConfigInterface $config): static
    {
        $products = (array) $config->get('products');
        foreach ($products as $productKey => $product) {
            $parents = ['products', $productKey];
            $parentsPath = implode('.', $parents);

            $config->set("$parentsPath.key", $productKey);

            $defaults = [
                'dir' => $this->helper->getProductDir((array) $config->get('env'), $productKey),
                'repositories' => [],
            ];
            $this->setDefaults($config, $parents, $defaults);

            $this->normalizeProductRepositories($config, $parents);
        }

        return $this;
    }

    /**
     * @phpstan-param array<string> $parents
     */
    protected function normalizeProductRepositories(ConfigInterface $config, array $parents): static
    {
        $parentsPath = implode('.', $parents);
        $productKey = (string) end($parents);
        $jetBrainsDir = (string) $config->get('stash.jetBrainsDir');
        foreach ($config->get("$parentsPath.repositories") as $repositoryKey => $repository) {
            $config->set("$parentsPath.repositories.$repositoryKey.key", $repositoryKey);

            $defaults = [
                'enabled' => true,
                'tidyStrategy' => 'global',
            ];
            if ($jetBrainsDir) {
                $defaults['path'] = Path::join(
                    $jetBrainsDir,
                    $productKey,
                    'config',
                    $repositoryKey,
                );
            }

            $this->setDefaults(
                $config,
                array_merge($parents, ['repositories', $repositoryKey]),
                $defaults,
            );
        }

        return $this;
    }

    /**
     * @phpstan-param array<string> $parents
     * @phpstan-param array<string, mixed> $values
     */
    protected function setDefaults(ConfigInterface $config, array $parents, array $values): static
    {
        $parentsPath = implode('.', $parents);
        foreach ($values as $key => $value) {
            if ($config->has("$parentsPath.$key")) {
                continue;
            }

            $config->set("$parentsPath.$key", $value);
        }

        return $this;
    }
}
