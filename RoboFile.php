<?php

declare(strict_types = 1);

use Consolidation\AnnotatedCommand\Attributes\Argument;
use Consolidation\AnnotatedCommand\Attributes\Command;
use Consolidation\AnnotatedCommand\Attributes\Help;
use Consolidation\AnnotatedCommand\Attributes\Hook;
use Consolidation\AnnotatedCommand\Attributes\Option;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use League\Container\Container as LeagueContainer;
use NuvoleWeb\Robo\Task\Config\Robo\loadTasks as ConfigLoader;
use Robo\Collection\CallableTask;
use Sweetchuck\Robo\Composer\ComposerTaskLoader;
use Sweetchuck\WebIdeConfigManager\Dev\Robo\Attributes\InitLintReporters;
use Sweetchuck\WebIdeConfigManager\Dev\Robo\Commands\BuildCommandsTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Robo\Collection\CollectionBuilder;
use Robo\Common\ConfigAwareTrait;
use Robo\Contract\ConfigAwareInterface;
use Robo\Contract\TaskInterface;
use Robo\State\Data as RoboState;
use Robo\Tasks;
use Robo\Task\Docker\Tasks as DockerTaskLoader;
use Sweetchuck\LintReport\Reporter\BaseReporter;
use Sweetchuck\Robo\Git\GitTaskLoader;
use Sweetchuck\Robo\Phpcs\PhpcsTaskLoader;
use Sweetchuck\Robo\Phpstan\PhpstanTaskLoader;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

/**
 * @phpstan-import-type DevPhpExecutable from \Sweetchuck\WebIdeConfigManager\Dev\Phpstan
 */
class RoboFile extends Tasks implements LoggerAwareInterface, ConfigAwareInterface
{
    use LoggerAwareTrait;
    use ConfigAwareTrait;
    use ConfigLoader;
    use GitTaskLoader;
    use PhpcsTaskLoader;
    use PhpstanTaskLoader;
    use DockerTaskLoader;
    use BuildCommandsTrait;
    use ComposerTaskLoader;

    #[Hook(
        type: HookManager::PRE_COMMAND_HOOK,
        selector: InitLintReporters::SELECTOR,
    )]
    public function onHookPreCommandInitLintReporters(): void
    {
        $lintServices = BaseReporter::getServices();
        $container = $this->getContainer();
        if (!($container instanceof LeagueContainer)) {
            return;
        }

        foreach ($lintServices as $name => $class) {
            if ($container->has($name)) {
                continue;
            }

            $container
                ->add($name, $class)
                ->setShared(false);
        }
    }

    /**
     * @var array<string, mixed>
     */
    protected array $composerInfo = [];

    protected string $shell = '/bin/bash';

    protected string $packageVendor = '';

    protected string $packageName = '';

    protected string $binDir = 'vendor/bin';

    protected string $gitHook = '';

    protected string $envVarNamePrefix = '';

    protected string $environmentType = '';

    /**
     * Allowed values: local, jenkins, travis.
     */
    protected string $environmentName = '';

    protected Filesystem $fs;

    public function __construct()
    {
        $this->fs = new Filesystem();

        $this
            ->initShell()
            ->initComposerInfo()
            ->initEnvVarNamePrefix()
            ->initEnvironmentTypeAndName();
    }

    // region Git Hooks
    #[Command(name: 'githook:pre-commit')]
    #[Help(
        description: 'Git "pre-commit" hook callback.',
        hidden: true,
    )]
    #[InitLintReporters]
    public function cmdGithookPreCommitExecute(): CollectionBuilder
    {
        $this->gitHook = 'pre-commit';

        return $this
            ->collectionBuilder()
            ->addTaskList(array_filter([
                'composer.validate' => $this->getTaskComposerValidate(),
                'circleci.config.validate' => $this->getTaskCircleCiConfigValidate(),
                'phpcs.lint' => $this->getTaskPhpcsLint(),
                'phpstan.analyze' => $this->getTaskPhpstanAnalyze(),
                'phpunit.run' => $this->getTaskPhpunitRunSuites(),
            ]));
    }
    // endregion

    #[Command(name: 'requirements')]
    public function cmdRequirementsExecute(): CommandResult
    {
        $jsonFileName = getenv('COMPOSER') ?: 'composer.json';
        $lockFileName = preg_replace('/\.json$/', '.lock', $jsonFileName);
        $lock = json_decode(file_get_contents($lockFileName) ?: '{}', true);
        $phpExtensions = $this->fetchExtensions($lock['platform'] ?? []);
        foreach ($lock['packages'] as $package) {
            $phpExtensions += $this->fetchExtensions($package['require'] ?? []);
        }

        ksort($phpExtensions);

        return CommandResult::data(array_keys($phpExtensions));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @noinspection PhpUnusedParameterInspection
     */
    #[Command(name: 'config:export')]
    #[Help(
        description: 'Export the project configuration.',
    )]
    #[Option(
        name: 'format',
        description: 'Output format',
    )]
    public function cmdConfigExportExecute(
        array $options = [
            'format' => 'yaml',
        ],
    ): CommandResult {
        return CommandResult::data($this->getConfig()->export());
    }

    #[Command(name: 'lint')]
    #[Help(
        description: 'Runs static code analyzers.',
    )]
    #[InitLintReporters]
    public function cmdLintExecute(): CollectionBuilder
    {
        return $this
            ->collectionBuilder()
            ->addTaskList(array_filter([
                'composer.validate' => $this->getTaskComposerValidate(),
                'circleci.config.validate' => $this->getTaskCircleCiConfigValidate(),
                'phpcs.lint' => $this->getTaskPhpcsLint(),
                'phpstan.analyze' => $this->getTaskPhpstanAnalyze(),
                'circeci.config.validate' => $this->getTaskCircleCiConfigValidate(),
            ]));
    }

    // region PHPCS
    #[Command(name: 'lint:phpcs')]
    #[Help(
        description: 'Runs PHP Code Sniffer.',
    )]
    #[InitLintReporters]
    public function cmdLintPhpcsExecute(): TaskInterface
    {
        return $this->getTaskPhpcsLint();
    }
    // endregion

    // region PHPStan
    #[Command(name: 'lint:phpstan')]
    #[Help(
        description: 'Runs PHPStan.',
    )]
    #[InitLintReporters]
    public function cmdLintPhpstanExecute(): TaskInterface
    {
        return $this->getTaskPhpstanAnalyze();
    }

    protected function getTaskPhpstanAnalyze(): TaskInterface
    {
        $taskGenerateProd = $this
            ->taskPhpstanGeneratePhp()
            // @phpstan-ignore-next-line
            ->setSrcFiles(
                (new Finder())
                    ->in('./.phpstan')
                    ->files()
                    ->name('parameters.typeAliases.prod.neon')
            )
            ->setDstFilePath('./src/Phpstan.php')
            ->setNamespace('Sweetchuck\WebIdeConfigManager');

        $taskGenerateDev = $this
            ->taskPhpstanGeneratePhp()
            // @phpstan-ignore-next-line
            ->setSrcFiles(
                (new Finder())
                    ->in('./.phpstan')
                    ->files()
                    ->name('parameters.typeAliases.dev.neon')
            )
            ->setDstFilePath('./src-dev/Phpstan.php')
            ->setNamespace('Sweetchuck\WebIdeConfigManager\Dev');

        /** @var \Sweetchuck\LintReport\Reporter\VerboseReporter $verboseReporter */
        $verboseReporter = $this->getContainer()->get('lintVerboseReporter');
        $verboseReporter->setFilePathStyle('relative');

        $taskLint = $this
            ->taskPhpstanAnalyze()
            // @phpstan-ignore-next-line
            ->setNoProgress(true)
            ->setNoInteraction(true)
            ->setErrorFormat('json')
            ->addLintReporter('lintVerboseReporter', $verboseReporter);

        $cb = $this->collectionBuilder();
        $cb->addTaskList([
            'generate.prod' => $taskGenerateProd,
            'generate.dev' => $taskGenerateDev,
            'lint' => $taskLint,
        ]);

        return $cb;
    }
    // endregion

    // region CircleCI
    #[Command(name: 'lint:circleci-config')]
    #[Help(
        description: 'Runs CircleCI config validation.',
    )]
    public function cmdLintCircleciConfigExecute(): ?TaskInterface
    {
        return $this->getTaskCircleCiConfigValidate();
    }

    protected function getTaskCircleCiConfigValidate(): ?TaskInterface
    {
        if ($this->environmentType === 'ci') {
            return null;
        }

        if ($this->gitHook === 'pre-commit') {
            $cb = $this->collectionBuilder();
            $cb->addTask(
                $this
                    ->taskGitListStagedFiles()
                    // @phpstan-ignore-next-line
                    ->setPaths(['./.circleci/config.yml' => true])
                    ->setDiffFilter(['d' => false])
                    ->setAssetNamePrefix('staged.')
            );

            $cb->addTask(
                $this
                    ->taskGitReadStagedFiles()
                    // @phpstan-ignore-next-line
                    ->setCommandOnly(true)
                    ->setWorkingDirectory('.')
                    // @phpstan-ignore-next-line
                    ->deferTaskConfiguration('setPaths', 'staged.fileNames')
            );

            $taskForEach = $this->taskForEach();
            $taskForEach
                // @phpstan-ignore-next-line
                ->iterationMessage('CircleCI config validate: {key}')
                // @phpstan-ignore-next-line
                ->deferTaskConfiguration('setIterable', 'files')
                ->withBuilder(function (
                    CollectionBuilder $builder,
                    string $key,
                    $file
                ) {
                    $builder->addTask(
                        $this->taskExec("{$file['command']} | circleci --skip-update-check config validate -"),
                    );
                });
            $cb->addTask($taskForEach);

            return $cb;
        }

        return $this->taskExec('circleci --skip-update-check config validate');
    }
    // endregion

    #[Hook(
        type: HookManager::ARGUMENT_VALIDATOR,
        target: 'test',
    )]
    public function cmdTestValidate(CommandData $commandData): void
    {
        $validSuiteNames = $this->getValidTestSuiteNames();
        $actualSuiteNames = $commandData->input()->getArgument('suiteNames');
        $missingSuiteNames = array_diff($actualSuiteNames, $validSuiteNames);
        if ($missingSuiteNames) {
            throw new \InvalidArgumentException(sprintf(
                'The suite names "%s" are not valid. Valid suite names are: "%s"',
                implode(', ', $missingSuiteNames),
                implode(', ', $validSuiteNames),
            ));
        }
    }

    /**
     * @param array<string> $suiteNames
     */
    #[Command(name: 'test')]
    #[Help(
        description: 'Runs the PHPUnit tests.',
    )]
    #[Argument(
        name: 'suiteNames',
        description: 'PHPUnit suite names',
    )]
    public function cmdTestExecute(array $suiteNames): CollectionBuilder
    {
        return $this->getTaskPhpunitRunSuites($suiteNames);
    }

    /**
     * @return array<string>
     */
    protected function getValidTestSuiteNames(): array
    {
        $suiteNames = [];
        $filePath = $this->fs->exists('phpunit.xml')
            ? 'phpunit.xml'
            : 'phpunit.dist.xml';
        $xml = new \DOMDocument();
        $xml->load($filePath);
        $xpath = new \DOMXPath($xml);
        /** @var iterable<\DOMElement> $suiteElements */
        $suiteElements = $xpath->query('/phpunit/testsuites/testsuite[@name]');
        foreach ($suiteElements ?: [] as $suiteElement) {
            $suiteNames[] = $suiteElement->getAttribute('name');
        }

        return $suiteNames;
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Command(name: 'php-core:release:list')]
    #[Option(
        name: 'format',
        description: 'Output format.',
    )]
    public function cmdPhpCoreReleaseListExecute(
        array $options = [
            'format' => 'yaml',
        ],
    ): CommandResult {
        $releases = file_get_contents('https://www.php.net/releases/?json');
        if ($releases === false) {
            return CommandResult::exitCode(1);
        }

        return CommandResult::data(json_decode($releases, true));
    }

    /**
     * @param iterable<string, string> $requirements
     *
     * @return array<string, string>
     *
     * @todo Move to utils.
     */
    protected function fetchExtensions(iterable $requirements): array
    {
        $phpExtensions = [];
        foreach ($requirements as $packageName => $version) {
            $matches = [];
            preg_match('@^ext-(?P<name>[^/]+)$@', $packageName, $matches);
            if (!empty($matches['name'])) {
                $extName = (string) $matches['name'];
                $phpExtensions[$extName] = $version;
            }
        }

        return $phpExtensions;
    }

    protected function errorOutput(): ?OutputInterface
    {
        $output = $this->output();

        return ($output instanceof ConsoleOutputInterface) ? $output->getErrorOutput() : $output;
    }

    protected function initEnvVarNamePrefix(): static
    {
        $this->envVarNamePrefix = strtoupper(str_replace('-', '_', $this->packageName));

        return $this;
    }

    protected function initEnvironmentTypeAndName(): static
    {
        $this->environmentType = (string) getenv($this->getEnvVarName('environment_type'));
        $this->environmentName = (string) getenv($this->getEnvVarName('environment_name'));

        if (!$this->environmentType) {
            if (getenv('CIRCLECI') === 'true') {
                $this->environmentType = 'ci';
                $this->environmentName = 'circleci';
            } elseif (getenv('GITLAB_CI') === 'true') {
                $this->environmentType = 'ci';
                $this->environmentName = 'gitlab';
            } elseif (getenv('GITHUB_ACTION')) {
                $this->environmentType = 'ci';
                $this->environmentName = 'github';
            } elseif (getenv('JENKINS_HOME')) {
                $this->environmentType = 'ci';
                $this->environmentName = 'jenkins';
            } elseif (getenv('BITBUCKET_REPO_UUID')) {
                $this->environmentType = 'ci';
                $this->environmentName = 'bitbucket';
            } elseif (getenv('CI') === 'true') {
                $this->environmentType = 'ci';
            }
        }

        if (!$this->environmentType) {
            $this->environmentType = 'dev';
        }

        if (!$this->environmentName) {
            $this->environmentName = 'local';
        }

        return $this;
    }

    protected function getEnvVarName(string $name): string
    {
        return "{$this->envVarNamePrefix}_" . strtoupper($name);
    }

    protected function initShell(): static
    {
        $this->shell = getenv('SHELL') ?: '/bin/bash';

        return $this;
    }

    protected function initComposerInfo(): static
    {
        if ($this->composerInfo) {
            return $this;
        }

        $composerFile = getenv('COMPOSER') ?: 'composer.json';
        $composerContent = file_get_contents($composerFile);
        if ($composerContent === false) {
            return $this;
        }

        $this->composerInfo = json_decode($composerContent, true);
        [$this->packageVendor, $this->packageName] = explode('/', $this->composerInfo['name']);

        if (!empty($this->composerInfo['config']['bin-dir'])) {
            $this->binDir = $this->composerInfo['config']['bin-dir'];
        }

        return $this;
    }

    /**
     * @return \Robo\Collection\CollectionBuilder|\Robo\Task\Composer\Validate
     */
    protected function getTaskComposerValidate()
    {
        $composerExecutable = $this->getConfig()->get('composerExecutable');

        return $this->taskComposerValidate($composerExecutable);
    }

    /**
     * @param array<string> $suiteNames
     *
     * @return \Robo\Collection\CollectionBuilder
     */
    protected function getTaskPhpunitRunSuites(array $suiteNames = []): CollectionBuilder
    {
        if (!$suiteNames) {
            $suiteNames = ['all'];
        }

        $phpExecutables = $this->getEnabledPhpExecutables();
        $cb = $this->collectionBuilder();
        foreach ($suiteNames as $suiteName) {
            foreach ($phpExecutables as $phpExecutable) {
                $cb->addTask($this->getTaskPhpunitRunSuite($suiteName, $phpExecutable));
            }
        }

        return $cb;
    }

    /**
     * @phpstan-param DevPhpExecutable $php
     */
    protected function getTaskPhpunitRunSuite(string $suite, array $php): CollectionBuilder
    {
        $command = $php['command'];
        $command[] = "{$this->binDir}/phpunit";

        if ($this->input()->getOption('ansi') !== false) {
            $command[] = '--colors=always';
        }

        if ($suite !== 'all') {
            $command[] = "--testsuite=$suite";
        }

        $cb = $this->collectionBuilder();

        return $cb
            ->addCode(function () use ($command, $php) {
                $this->output()->writeln(strtr(
                    '<question>[{name}]</question> runs <info>{command}</info>',
                    [
                        '{name}' => 'PhpUnit',
                        '{command}' => implode(' ', $command),
                    ],
                ));

                $process = new Process(
                    $command,
                    null,
                    $php['envVars'] ?? null,
                );

                return $process->run($this->getProcessCallback());
            });
    }

    protected function getTaskPhpcsLint(): TaskInterface
    {
        $options = [
            'failOn' => 'warning',
            'lintReporters' => [
                'lintVerboseReporter' => null,
            ],
        ];

        $reportsDir = $this->geReportsDir();
        if ($this->environmentType === 'ci' && $this->environmentName === 'jenkins') {
            $options['failOn'] = 'never';
            $options['lintReporters']['lintCheckstyleReporter'] = $this
                ->getContainer()
                ->get('lintCheckstyleReporter')
                ->setDestination("$reportsDir/machine/checkstyle/phpcs.psr2.xml");
        }

        if ($this->gitHook === 'pre-commit') {
            return $this
                ->collectionBuilder()
                ->addTask($this
                    ->taskPhpcsParseXml()
                    // @phpstan-ignore-next-line
                    ->setAssetNamePrefix('phpcsXml.'))
                ->addTask($this
                    ->taskGitListStagedFiles()
                    // @phpstan-ignore-next-line
                    ->setPaths(['*.php' => true])
                    ->setDiffFilter(['d' => false])
                    ->setAssetNamePrefix('staged.'))
                ->addTask($this
                    ->taskGitReadStagedFiles()
                    // @phpstan-ignore-next-line
                    ->setCommandOnly(true)
                    ->setWorkingDirectory('.')
                    // @phpstan-ignore-next-line
                    ->deferTaskConfiguration('setPaths', 'staged.fileNames'))
                ->addTask($this
                    ->taskPhpcsLintInput($options)
                    ->deferTaskConfiguration('setFiles', 'files')
                    ->deferTaskConfiguration('setIgnore', 'phpcsXml.exclude-patterns'));
        }

        return $this->taskPhpcsLintFiles($options);
    }

    protected function geReportsDir(): string
    {
        return 'reports';
    }

    protected function getProcessCallback(
        bool $hideStdOutput = false,
        bool $hideStdError = false
    ): \Closure {
        return function (string $type, string $data) use ($hideStdOutput, $hideStdError) {
            if (($type === Process::OUT && $hideStdOutput)
                || ($type === Process::ERR && $hideStdError)
            ) {
                return;
            }

            switch ($type) {
                case Process::OUT:
                    $this->output()->write($data);
                    break;

                case Process::ERR:
                    $this->errorOutput()->write($data);
                    break;
            }
        };
    }

    /**
     * @return array<string, DevPhpExecutable>
     */
    protected function getEnabledPhpExecutables(): array
    {
        /** @phpstan-var array<string, DevPhpExecutable> $phpExecutables */
        $phpExecutables = array_filter(
            $this->getConfig()->get('php.executables'),
            function (array $php): bool {
                if (!array_key_exists('conditions', $php)) {
                    return true;
                }

                if (array_key_exists('environmentType', $php['conditions'])
                    && $php['conditions']['environmentType'] !== $this->environmentType
                ) {
                    return false;
                }

                if (array_key_exists('environmentName', $php['conditions'])
                    && $php['conditions']['environmentName'] !== $this->environmentName
                ) {
                    return false;
                }

                return true;
            },
        );

        return $phpExecutables;
    }

    // region Release
    protected function getPharDestination(): string
    {
        $name = $this->getAppName();

        return "artifacts/$name.phar";
    }

    /**
     * @phpstan-param array<string, mixed> $options
     */
    #[Command(name: 'release:build')]
    #[Help(
        description: 'Generates an executable PHAR file.',
    )]
    public function cmdReleaseBuildExecute(
        string $destination = '',
        array $options = [
            'tag' => '',
        ],
    ): TaskInterface {
        if (!$destination) {
            $destination = $this->getPharDestination();
        }

        if (!$this->fs->isAbsolutePath($destination)) {
            $destination = Path::makeAbsolute($destination, (string) getcwd());
        }

        $cb = $this->collectionBuilder();
        $cb->addTaskList([
            'release:build init' => $this->getTaskReleaseBuildInit($cb),
            'release:build prepare-working-directory' => $this->getTaskReleaseBuildPrepareWorkingDirectory($cb),
            'release:build copy-project-collect' => $this->getTaskReleaseBuildCopyProjectCollect($cb),
            'release:build copy-project-do-it' => $this->getTaskReleaseBuildPharCopyProjectDoIt($cb),
            // @phpstan-ignore-next-line
            'release:build composer:install' => $this->taskComposerInstall()->option('no-dev'),
            'release:build composer-package-paths' => $this->getTaskReleaseBuildComposerPackagePaths($cb),
            'release:build phar' => $this->getTaskReleaseBuildPhar($cb, $destination, $options['tag']),
        ]);

        return $cb;
    }

    protected function getTaskReleaseBuildInit(CollectionBuilder $cb): TaskInterface
    {
        return new CallableTask(
            function (RoboState $state): int {
                $state['srcDir'] = getcwd();
                $state['appName'] = $this->getAppName();

                return 0;
            },
            $cb,
        );
    }

    protected function getTaskReleaseBuildPrepareWorkingDirectory(CollectionBuilder $cb): TaskInterface
    {
        return $this
            ->taskTmpDir(basename(__DIR__), (string) realpath('..'))
            // @phpstan-ignore-next-line
            ->cwd();
    }

    protected function getTaskReleaseBuildCopyProjectCollect(CollectionBuilder $cb): TaskInterface
    {
        return $this
            ->taskGitListFiles()
            // @phpstan-ignore-next-line
            ->setAssetNamePrefix('project.')
            // @phpstan-ignore-next-line
            ->deferTaskConfiguration('setWorkingDirectory', 'srcDir');
    }

    protected function getTaskReleaseBuildPharCopyProjectDoIt(CollectionBuilder $cb): TaskInterface
    {
        $taskForeach = $this->taskForEach();
        $taskForeach
            // @phpstan-ignore-next-line
            ->iterationMessage('Copy source files into a temporary directory: {key}')
            // @phpstan-ignore-next-line
            ->deferTaskConfiguration('setIterable', 'project.files')
            ->withBuilder(function (CollectionBuilder $builder, string $fileName) use ($cb): int {
                $srcDir = $cb->getState()['srcDir'];
                // @phpstan-ignore-next-line
                $builder->addTask($this->taskFilesystemStack()->copy(
                    "$srcDir/$fileName",
                    "./$fileName",
                ));

                return 0;
            });

        return $taskForeach;
    }

    protected function getTaskReleaseBuildComposerPackagePaths(CollectionBuilder $cb): TaskInterface
    {
        $task = $this->taskComposerPackagePaths();
        $task->deferTaskConfiguration('setWorkingDirectory', 'path');

        return $task;
    }

    protected function getTaskReleaseBuildPhar(CollectionBuilder $cb, string $pharPathname, string $version): TaskInterface
    {
        return new CallableTask(
            function (RoboState $state) use ($pharPathname, $version): int {
                $this->logger->info(
                    'Create PHAR; version: {version} ; path: {pharPathname}',
                    [
                        'pharPathname' => $pharPathname,
                        'version' => $version,
                    ],
                );
                $vendorDir = 'vendor';

                $filesExtra = [
                    $state['path'] . '/composer.json',
                ];
                $files = new \AppendIterator();
                $files->append(
                    (new Finder())
                        ->in('./src/')
                        ->files()
                        ->name('*.php')
                        ->getIterator(),
                );
                $files->append(
                    (new Finder())
                        ->in('./resources/')
                        ->files()
                        ->name('*.yml')
                        ->getIterator(),
                );
                $files->append(
                    (new Finder())
                        ->in("./$vendorDir/")
                        ->files()
                        ->notPath('psr/log/Psr/Log/Test')
                        ->notPath('.phpstan')
                        ->notPath('bin')
                        ->notPath('src-dev')
                        ->notPath('tests')
                        ->notName('composer.json')
                        ->notName('composer.lock')
                        ->notName('phpcs.xml')
                        ->notName('phpcs.xml.dist')
                        ->notName('phpstan.neon')
                        ->notName('phpstan.dist.neon')
                        ->notName('phpstan.neon.dist')
                        ->notName('phpunit.xml')
                        ->notName('phpunit.dist.xml')
                        ->notName('robo.yml')
                        ->notName('robo.yml.dist')
                        ->notName('RoboFile.php')
                        ->notName('*.md')
                        ->ignoreVCS(true)
                        ->getIterator(),
                );

                $packageDirs = (new Finder())
                    ->in($vendorDir)
                    ->directories()
                    ->depth(1);

                /** @var \Symfony\Component\Finder\SplFileInfo $packageDir */
                foreach ($packageDirs as $packageDir) {
                    if (!$packageDir->isLink()) {
                        continue;
                    }

                    $packageFiles = (new Finder())
                        ->in((string) realpath($packageDir->getPathname()))
                        ->files()
                        ->notPath('bin')
                        ->notPath('reports')
                        ->notPath('tests')
                        ->notPath('Test')
                        ->notPath('vendor')
                        ->notName('codeception.*')
                        ->notName('composer.json')
                        ->notName('composer.lock')
                        ->notName('phpcs.xml')
                        ->notName('phpcs.xml.dist')
                        ->notName('phpunit.xml')
                        ->notName('phpunit.dist.xml')
                        ->notName('robo.yml')
                        ->notName('robo.yml.dist')
                        ->notName('RoboFile.php')
                        ->notName('*.md')
                        ->ignoreVCS(true);
                    foreach ($packageFiles as $packageFile) {
                        $filesExtra[] = $state['path']
                            . '/' . $packageDir->getPathname()
                            . '/' . $packageFile->getRelativePathname();
                    }
                }

                $files->append(new \ArrayIterator($filesExtra));

                $appName = $state['appName'];
                if (file_exists($pharPathname)) {
                    unlink($pharPathname);
                }

                $startFile = "bin/$appName";
                /** @var array<string> $startContent */
                $startContent = (array) file($startFile);
                array_shift($startContent);
                if ($version !== '') {
                    $startContent = preg_replace(
                        '/^\$appVersion = \'.*?\';$/m',
                        sprintf('$appVersion = %s;', var_export($version, true)),
                        $startContent,
                    );
                }

                $this->fs->mkdir(dirname($pharPathname), 0777 - umask());
                $phar = new \Phar($pharPathname, 0);
                $phar->buildFromIterator($files, $state['path']);
                $phar->addFromString($startFile, implode('', $startContent));
                $phar->setStub($this->getPharStubCode($appName, $startFile));
                chmod($pharPathname, 0777 - umask());

                return 0;
            },
            $cb,
        );
    }

    protected function getPharStubCode(string $appName, string $startFile): string
    {
        return sprintf(
            <<<'PHP'
                #!/usr/bin/env php
                <?php
                Phar::mapPhar(%s);
                set_include_path(%s . get_include_path());
                require(%s);
                __HALT_COMPILER();
                PHP,
            var_export($appName, true),
            var_export("phar://$appName/", true),
            var_export($startFile, true),
        );
    }

    #[Command(name: 'phar:content')]
    #[Help(
        description: 'Show the content of a phar file',
    )]
    #[Argument(
        name: 'path',
        description: 'Path to the phar file.',
    )]
    public function cmdPharContentExecute(string $path = ''): void
    {
        if ($path === '') {
            $path = sprintf(
                './artifacts/%s.phar',
                $this->getAppName(),
            );
        }

        $path = realpath($path);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("phar://$path"));
        $output = $this->output();
        /** @var \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($files as $file) {
            $output->writeln(str_replace("phar://$path", '', $file->getPathname()));
        }
    }
    // endregion

    protected function getAppName(): string
    {
        $binPath = !empty($this->composerInfo['bin'])
            ? reset($this->composerInfo['bin'])
            : './bin/app';

        return pathinfo($binPath, \PATHINFO_FILENAME);
    }
}
