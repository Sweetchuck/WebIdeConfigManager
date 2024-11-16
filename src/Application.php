<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager;

use Consolidation\AnnotatedCommand\CommandFileDiscovery;
use League\Container\ContainerAwareInterface;
use League\Container\ContainerAwareTrait;
use Robo\Common\ConfigAwareTrait;
use Sweetchuck\Utils\Comparer\ArrayValueComparer;
use Sweetchuck\WebIdeConfigManager\Component\Color\Handler as ColorHandler;
use Sweetchuck\WebIdeConfigManager\Component\FileTemplate\Handler as FileTemplateHandler;
use Sweetchuck\WebIdeConfigManager\Component\Template\Handler as TemplateHandler;
use Sweetchuck\WebIdeConfigManager\Product\StatusReporter\DiffReporter;
use Sweetchuck\WebIdeConfigManager\Product\StatusReporter\TableReporter;
use Sweetchuck\WebIdeConfigManager\Util\ConfigNormalizer;
use Sweetchuck\WebIdeConfigManager\Util\ConfigValidator;
use Sweetchuck\WebIdeConfigManager\Util\Helper;
use Sweetchuck\WebIdeConfigManager\Product\Handler as ProductHandler;
use Symfony\Component\Console\Application as ApplicationBase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Application extends ApplicationBase implements ContainerAwareInterface
{
    use ConfigAwareTrait;
    use ContainerAwareTrait;

    const string NAME = 'jbcm';

    /**
     * @return array<string>
     */
    public function getCommandClasses(string $projectRoot): array
    {
        return (new CommandFileDiscovery())
            ->setSearchPattern('*Command.php')
            ->discover(
                [
                    "$projectRoot/src/Commands",
                ],
                __NAMESPACE__ . '\Commands',
            );
    }

    /**
     * @param array<string, string> $envVars
     *
     * @return array<string>
     */
    public function getConfigFiles(string $projectRoot, array $envVars): array
    {
        $appName = $this->getName();
        $envVarNamePrefix = mb_strtoupper($appName);

        // Only files.
        $configFiles = [
            "$projectRoot/$appName.yml",
        ];

        // Mixed files and directories.
        $configPaths = [
            "$projectRoot/resources/config",
        ];

        $configPathsUser = array_filter(
            explode(
                \PATH_SEPARATOR,
                $envVars["{$envVarNamePrefix}_CONFIG"] ?? '',
            ),
            fn(string $value): bool => strlen($value) > 0,
        );
        if (!$configPathsUser) {
            $configPathsUser = [
                ($envVars['HOME'] ?? '') . "/.config/$appName",
            ];
        }

        $configPaths = array_merge(
            $configPaths,
            $configPathsUser,
        );

        foreach ($configPaths as $path) {
            if (!file_exists($path)) {
                continue;
            }

            if (is_file($path)) {
                $configFiles[] = $path;

                continue;
            }

            $filesList = (new Finder())
                ->in($path)
                ->depth(0)
                ->files()
                ->name("$appName.*.yml")
                ->sortByName();
            foreach ($filesList as $file) {
                $configFiles[] = $file->getPathname();
            }
        }

        return $configFiles;
    }

    public function configureContainer(): static
    {
        /** @var \League\Container\Container $container */
        $container = $this->getContainer();
        $container->add('symfony.finder', Finder::class);
        $container->addShared('symfony.filesystem', Filesystem::class);
        $container->addShared('app.config.validator', ConfigValidator::class);
        $container->addShared('app.helper', Helper::class);
        $container->addShared('app.fileTemplate.handler', FileTemplateHandler::class);
        $container->addShared('app.color.handler', ColorHandler::class);
        $container
            ->addShared('app.template.handler', TemplateHandler::class)
            ->addArgument('app.helper');
        $container
            ->addShared('app.configNormalizer', ConfigNormalizer::class)
            ->addArgument('app.helper');
        $container
            ->addShared('app.product.handler', ProductHandler::class)
            ->addArgument('app.template.handler')
            ->addArgument('app.fileTemplate.handler')
            ->addArgument('app.color.handler');
        $container->add('app.product.status.tableReporter', TableReporter::class);
        $container
            ->add('app.product.status.diffReporter', DiffReporter::class)
            ->addArgument('app.template.handler');

        $container->add(
            'app.repository.comparer',
            static function (): callable {
                $comparer = new ArrayValueComparer();
                $comparer->setKeys([
                    'weight' => [
                        'default' => 0,
                    ],
                    'key' => [
                        'default' => '',
                    ],
                ]);

                return $comparer;
            },
        );

        return $this;
    }
}
