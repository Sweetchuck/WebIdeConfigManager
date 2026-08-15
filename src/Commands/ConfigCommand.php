<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Attributes as Cli;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Robo\Contract\TaskInterface;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateComponentName;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateProductDir;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateProductName;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateProductRepositories;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateConfigStatusFilter;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Sweetchuck\WebIdeConfigManager\Robo\JbcmTaskLoader;
use Sweetchuck\WebIdeConfigManager\Product\Handler as ProductHandler;
use Sweetchuck\WebIdeConfigManager\Util\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatus from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class ConfigCommand extends CommandBase
{
    use JbcmTaskLoader;

    protected Helper $helper;

    protected ProductHandler $productHandler;

    /**
     * @phpstan-var array{
     *     colors: ?\Sweetchuck\WebIdeConfigManager\Component\Color\Handler,
     *     fileTemplates: ?\Sweetchuck\WebIdeConfigManager\Component\FileTemplate\Handler,
     *     templates: ?\Sweetchuck\WebIdeConfigManager\Component\Template\Handler,
     * }
     */
    protected array $handlers = [
        'colors' => null,
        'templates' => null,
        'fileTemplates' => null,
    ];

    protected function initInjectDependencies(): static
    {
        parent::initInjectDependencies();
        $this->helper = $this->getContainer()->get('app.helper');
        $this->productHandler = $this->getContainer()->get('app.product.handler');
        $this->handlers['colors'] = $this->getContainer()->get('app.color.handler');
        $this->handlers['fileTemplates'] = $this->getContainer()->get('app.fileTemplate.handler');
        $this->handlers['templates'] = $this->getContainer()->get('app.template.handler');

        return $this;
    }

    // region config:status
    /**
     * @param array<string, mixed> $options
     */
    #[Cli\Command(
        name: 'config:status',
        aliases: ['status'],
    )]
    #[Cli\Help(
        description: 'Gives a report about the synchronization status.',
    )]
    #[Cli\Argument(
        name: 'productName',
        description: 'Name of the JetBrain product. For example: "PhpStorm".',
        suggestedValues: Helper::SUPPORTED_PRODUCTS,
    )]
    #[Cli\Option(
        name: 'statusFilter',
        description: 'Filter by status. Example values: "all", "nc", "o", "orphan,new,changed,upToDate".',
    )]
    #[Cli\Option(
        name: 'format',
        description: 'Output format.',
    )]
    #[Cli\FieldLabels(
        labels: [
            'component' => 'Component',
            'name' => 'Name',
            'status' => 'Status',
            'repositories' => 'Repositories',
        ],
    )]
    #[ValidateProductName(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductDir(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductRepositories(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateConfigStatusFilter(
        type: 'option',
        name: 'statusFilter',
    )]
    public function cmdConfigStatusExecute(
        string $productName,
        array $options = [
            'statusFilter' => 'new,changed',
            'format' => 'table',
        ],
    ): CommandResult {
        $config = $this->getConfig()->export();
        $product = $config['products'][$productName] ?? [];

        return CommandResult::data($this->productHandler->getStatus($product));
    }

    #[Cli\Hook(
        type: HookManager::ALTER_RESULT,
        target: 'config:status',
    )]
    public function cmdConfigStatusAlter(
        mixed $result,
        CommandData $commandData,
    ): mixed {
        $format = $commandData->input()->getOption('format');
        // @todo Custom data type.
        $data = $result instanceof CommandResult
            ? $result->getOutputData()
            : null;
        if (!is_array($data)) {
            return $result;
        }

        /** @phpstan-var JbcmProductStatus $data */
        $hasToBeRows = in_array($format, ['table', 'csv']);
        if ($hasToBeRows) {
            /** @var JbcmProductStatus $data */
            /** @var \Sweetchuck\WebIdeConfigManager\Product\StatusReporter\TableReporter $tableReporter */
            $tableReporter = $this->getContainer()->get('app.product.status.tableReporter');
            $productKey = $commandData->input()->getArgument('productName');
            $statusFilter = ConfigStatus::createFilter($commandData->input()->getOption('statusFilter'));
            $tableReporter->setStatusFilter($statusFilter);
            $config = $this->getConfig()->export();
            /** @var JbcmProduct $product */
            $product = $config['products'][$productKey];
            $rows = new RowsOfFields($tableReporter->getTableRows($product, $data));
            $result->setOutputData($rows);
        } elseif (in_array($format, ['yaml', 'json'])) {
            $this->helper->prepareProductStatusForSerialize($data);
            $result->setOutputData($data);
        }

        return $result;
    }
    // endregion

    // region config:diff
    #[Cli\Command(
        name: 'config:diff',
        aliases: ['diff'],
    )]
    #[Cli\Help(
        description: <<<'TEXT'
            Shows the differences between the local repository and the directory which is actively used by the application.
            TEXT,
    )]
    #[Cli\Argument(
        name: 'productName',
        description: 'Name of the JetBrain product. For example: "PhpStorm".',
        suggestedValues: Helper::SUPPORTED_PRODUCTS,
    )]
    #[ValidateProductName(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductDir(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductRepositories(
        type: 'argument',
        name: 'productName',
    )]
    public function cmdConfigDiffExecute(
        string $productName,
    ): CommandResult {
        $config = $this->getConfig()->export();
        $product = $config['products'][$productName] ?? [];

        return CommandResult::data($this->productHandler->getStatus($product));
    }

    #[Cli\Hook(
        type: HookManager::ALTER_RESULT,
        target: 'config:diff',
    )]
    public function cmdConfigDiffAlter(
        mixed $result,
        CommandData $commandData,
    ): mixed {
        // @todo Custom data type.
        $data = $result instanceof CommandResult
            ? $result->getOutputData()
            : null;

        if (is_array($data)) {
            $productKey = $commandData->input()->getArgument('productName');
            $config = $this->getConfig()->export();
            /** @var JbcmProduct $product */
            $product = $config['products'][$productKey];
            /** @var JbcmProductStatus $data */
            $bufferedOutput = new BufferedOutput();
            /** @var \Sweetchuck\WebIdeConfigManager\Product\StatusReporter\DiffReporter $diffReporter */
            $diffReporter = $this->getContainer()->get('app.product.status.diffReporter');
            $diffReporter
                ->setOutput($bufferedOutput)
                ->generate($product, $data);
            $result->setOutputData($bufferedOutput->fetch());
        }

        return $result;
    }
    // endregion

    // region config:pull
    #[Cli\Command(
        name: 'config:pull',
        aliases: ['pull'],
    )]
    #[Cli\Help(
        description: 'Pulls configs from the directory which is actively used by the application into the local repository.',
    )]
    #[Cli\Argument(
        name: 'productName',
        description: 'Name of the JetBrain product. For example: "PhpStorm".',
        suggestedValues: Helper::SUPPORTED_PRODUCTS,
    )]
    #[ValidateProductName(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductDir(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductRepositories(
        type: 'argument',
        name: 'productName',
    )]
    public function cmdConfigPullExecute(
        string $productName,
    ): TaskInterface {
        $config = $this->getConfig()->export();

        $cb = $this->collectionBuilder();
        $taskList = [];

        $productDir = $this->helper->getProductDir((array) $config['env'], $productName);
        $repositories = $this->getEnabledRepositories($productName);
        $globalTidy = $config['tidy'];
        foreach ($repositories as $repository) {
            $tidy = $this->helper->getFinalTidy(
                $config['products'][$productName]['repositories'][$repository['key']]['tidyStrategy'],
                $globalTidy,
                $config['products'][$productName]['repositories'][$repository['key']]['tidy'] ?? [],
            );

            $taskList["jbcm.pull.$productName.templates.{$repository['key']}"] = $this
                ->taskJbcmTemplatesPull()
                // @phpstan-ignore-next-line
                ->setProductDir($productDir)
                ->setRepository($repository)
                ->setTidy($tidy);

            $taskList["jbcm.pull.$productName.fileTemplates.{$repository['key']}"] = $this
                ->taskJbcmFileTemplatesPull()
                // @phpstan-ignore-next-line
                ->setProductDir($productDir)
                ->setRepository($repository);
        }

        $cb->addTaskList($taskList);

        return $cb;
    }
    // endregion

    // region config:push
    #[Cli\Command(
        name: 'config:push',
        aliases: ['push'],
    )]
    #[Cli\Help(
        description: 'Pushes all the local repositories into the directory which is actively used by the application.',
    )]
    #[Cli\Argument(
        name: 'productName',
        description: 'Name of the JetBrain product. For example: "PhpStorm".',
        suggestedValues: Helper::SUPPORTED_PRODUCTS,
    )]
    #[ValidateProductName(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductDir(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductRepositories(
        type: 'argument',
        name: 'productName',
    )]
    public function cmdConfigPushExecute(
        string $productName,
    ): TaskInterface {
        $config = $this->getConfig()->export();

        $cb = $this->collectionBuilder();

        $productDir = $this->helper->getProductDir($config['env'], $productName);
        $repositories = $this->getEnabledRepositories($productName);

        $globalTidy = $config['tidy'];

        $taskList = [];
        foreach ($repositories as $repository) {
            $tidy = $this->helper->getFinalTidy(
                $config['products'][$productName]['repositories'][$repository['key']]['tidyStrategy'],
                $globalTidy,
                $config['products'][$productName]['repositories'][$repository['key']]['tidy'] ?? [],
            );

            $taskList["config.push.{$repository['key']}.templates.$productName"] = $this
                ->taskJbcmTemplatesPush()
                // @phpstan-ignore-next-line
                ->setProductDir($productDir)
                ->setRepository($repository)
                ->setTidy($tidy);

            $taskList["config.push.{$repository['key']}.fileTemplates.$productName"] = $this
                ->taskJbcmFileTemplatesPush()
                // @phpstan-ignore-next-line
                ->setProductDir($productDir)
                ->setRepository($repository);
        }

        $cb->addTaskList($taskList);

        return $cb;
    }
    // endregion

    // region config:adopt
    #[Cli\Hook(
        type: HookManager::INTERACT,
        target: 'config:adopt',
    )]
    public function cmdConfigAdoptInteract(
        InputInterface $input,
        OutputInterface $output,
        AnnotationData $annotationData,
    ): void {
        $io = new SymfonyStyle($input, $output);
        $config = $this->getConfig()->export();

        /** @var array<string, JbcmProduct> $products */
        $products = $config['products'];

        $productName = $input->getArgument('productName');
        if (!$productName) {
            $productName = $io->choice(
                'Select a product',
                $this->getProductOptions(),
            );
            if ($productName) {
                $input->setArgument('productName', $productName);
            }
        }

        $componentName = $input->getArgument('componentName');
        if (!$componentName) {
            $componentName = $io->choice(
                'Select a component',
                array_combine(Helper::SUPPORTED_COMPONENTS, Helper::SUPPORTED_COMPONENTS),
            );
            if ($componentName) {
                $input->setArgument('componentName', $componentName);
            }
        }

        $relativeFilepath = $input->getArgument('relativeFilepath');
        if (isset($products[$productName]) && !$relativeFilepath) {
            $orphanFiles = [];
            if (array_key_exists($componentName, $this->handlers)) {
                $orphanFiles = $this->handlers[$componentName]->getOrphanItems(
                    $products[$productName]['dir'] ?: '',
                    $config['products'][$productName]['repositories'] ?? [],
                );
            }

            $options = array_combine(
                array_keys($orphanFiles),
                array_keys($orphanFiles),
            );
            $relativeFilepath = $io->choice('Choose an orphan file:', $options);
            if ($relativeFilepath) {
                $input->setArgument('relativeFilepath', $relativeFilepath);
            }
        }

        $repositoryKey = $input->getArgument('repositoryKey');
        if (!$repositoryKey) {
            $options = array_combine(
                array_keys($products[$productName]['repositories'] ?? []),
                array_keys($products[$productName]['repositories'] ?? []),
            );
            $repositoryKey = $io->choice('Repository:', $options);
            if ($repositoryKey) {
                $input->setArgument('repositoryKey', $repositoryKey);
            }
        }
    }

    #[Cli\Hook(
        type: HookManager::ARGUMENT_VALIDATOR,
        target: 'config:adopt',
    )]
    public function cmdConfigAdoptValidate(
        CommandData $commandData,
    ): void {
        $config = $this->getConfig()->export();
        $productName = $commandData->input()->getArgument('productName');
        $product = $config['products'][$productName];
        $componentName = $commandData->input()->getArgument('componentName');
        $relativeFilepath = $commandData->input()->getArgument('relativeFilepath');
        $orphanFiles = $this->handlers[$componentName]->getOrphanItems($product['dir'], $product['repositories']);

        if (!isset($orphanFiles[$relativeFilepath])) {
            throw new \InvalidArgumentException(sprintf(
                'File "%s" is not an orphan file.',
                $relativeFilepath,
            ));
        }

        $repositoryKey = $commandData->input()->getArgument('repositoryKey');
        if (!isset($product['repositories'][$repositoryKey])) {
            throw new \InvalidArgumentException(sprintf(
                'Repository with key "%s" is not exists.',
                $repositoryKey,
            ));
        }
    }

    #[Cli\Command(
        name: 'config:adopt',
        aliases: ['adopt'],
    )]
    #[Cli\Help(
        description: 'Copies an orphan configuration file into a local repository.',
    )]
    #[Cli\Argument(
        name: 'productName',
        description: 'Name of the JetBrain product. For example: "PhpStorm".',
        suggestedValues: Helper::SUPPORTED_PRODUCTS,
    )]
    #[Cli\Argument(
        name: 'componentName',
        description: 'Type of the configuration. For example: "templates".',
        suggestedValues: Helper::SUPPORTED_COMPONENTS,
    )]
    #[Cli\Argument(
        name: 'relativeFilepath',
        description: 'File name',
    )]
    #[Cli\Argument(
        name: 'repositoryKey',
        description: 'Name of the repository.',
    )]
    #[ValidateProductName(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductDir(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateProductRepositories(
        type: 'argument',
        name: 'productName',
    )]
    #[ValidateComponentName(
        type: 'argument',
        name: 'componentName',
    )]
    public function cmdConfigAdoptExecute(
        string $productName,
        string $componentName,
        string $relativeFilepath,
        string $repositoryKey,
    ): ?TaskInterface {
        $config = $this->getConfig()->export();

        $taskList = [];
        $cb = $this->collectionBuilder();

        $product = $config['products'][$productName];
        $repository = $product['repositories'][$repositoryKey];

        switch ($componentName) {
            case 'colors':
                $taskList["jbcm.$componentName.pull.single"] = $this
                    ->taskJbcmColorsPullSingle()
                    // @phpstan-ignore-next-line
                    ->setProductDir($product['dir'])
                    ->setRepository($repository)
                    ->setRelativeFilepath($relativeFilepath);
                break;

            case 'fileTemplates':
                $taskList["jbcm.$componentName.pull.single"] = $this
                    ->taskJbcmFileTemplatesPullSingle()
                    // @phpstan-ignore-next-line
                    ->setProductDir($product['dir'])
                    ->setRepository($repository)
                    ->setRelativeFilepath($relativeFilepath);
                break;

            case 'templates':
                $globalTidy = $config['tidy'];
                $tidy = $this->helper->getFinalTidy(
                    $repository['tidyStrategy'] ?? 'global',
                    $globalTidy,
                    $repository['tidy'] ?? [],
                );

                $taskList["jbcm.$componentName.pull.single"] = $this
                    ->taskJbcmTemplatesPullSingle()
                    // @phpstan-ignore-next-line
                    ->setProductDir($product['dir'])
                    ->setRepository($repository)
                    ->setRelativeFilepath($relativeFilepath)
                    ->setTidy($tidy);
                break;
        }

        $cb->addTaskList($taskList);

        return $cb;
    }
    // endregion
}
