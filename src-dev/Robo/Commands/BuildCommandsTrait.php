<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Dev\Robo\Commands;

use Consolidation\AnnotatedCommand\Attributes\Command;
use Robo\Collection\CollectionBuilder;
use Robo\Contract\TaskInterface;
use Robo\Task\Base\Tasks as ExecTaskLoader;
use Sweetchuck\Robo\Phpstan\PhpstanTaskLoader;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;

trait BuildCommandsTrait
{
    use ExecTaskLoader;
    use PhpstanTaskLoader;

    /**
     * @param \Robo\Symfony\ConsoleIO $io
     *
     * @return \Robo\Collection\CollectionBuilder
     */
    abstract protected function collectionBuilder($io = null);

    #[Command(name: 'build')]
    public function cmdBuildExecute(): TaskInterface
    {
        $cb = $this->collectionBuilder();
        $cb->addTaskList($this->getTasksBuildImage($cb));
        $cb->addTaskList($this->getTasksBuildPhpstan($cb));

        return $cb;
    }

    #[Command(name: 'build:image')]
    public function cmdBuildImageExecute(): TaskInterface
    {
        $cb = $this->collectionBuilder();
        $cb->addTaskList($this->getTasksBuildImage($cb));

        return $cb;
    }

    /**
     * @return array<string, \Robo\Contract\TaskInterface>
     */
    protected function getTasksBuildImage(CollectionBuilder $cb): array
    {
        $inkscapeExecutable = 'inkscape';
        $inkscapeExecutableSafe = escapeshellcmd($inkscapeExecutable);

        $pngFiles = [
            './resources/images/open-graph.png',
            './resources/images/icon-square.png',
            './resources/images/logo.png',
        ];

        $tasks = [];
        foreach ($pngFiles as $pngFile) {
            if (!$this->fs->exists($pngFile)) {
                continue;
            }

            $tasks["build:image {$pngFile}"] = $this->taskExec(sprintf(
                '%s --export-overwrite --export-filename=%s %s',
                $inkscapeExecutableSafe,
                $pngFile,
                preg_replace('/\.png$/', '.svg', $pngFile),
            ));
        }

        return $tasks;
    }

    #[Command(name: 'build:phpstan')]
    public function cmdBuildPhpstanGenerateExecute(): TaskInterface
    {
        $cb = $this->collectionBuilder();
        $cb->addTaskList($this->getTasksBuildPhpstan($cb));

        return $cb;
    }

    /**
     * @return array<string, \Robo\Contract\TaskInterface>
     */
    protected function getTasksBuildPhpstan(CollectionBuilder $cb): array
    {
        $scopes = [
            'prod' => [],
            'dev' => [],
        ];

        $tasks = [];
        $composerFileName = getenv('COMPOSER') ?: 'composer.json';
        $composer = array_replace_recursive(
            [
                'autoload' => [
                    'psr-4' => [],
                ],
                'autoload-dev' => [
                    'psr-4' => [],
                ],
            ],
            json_decode($this->fs->readFile($composerFileName), true),
        );
        foreach ($scopes as $scope => $options) {
            $alKey = $scope === 'prod' ? 'autoload' : 'autoload-dev';
            $dstDirCandidates = [
                'src/',
                'src-dev/',
                'tests/src/',
            ];
            $dstDir = '';
            foreach ($dstDirCandidates as $dstDirCandidate) {
                $namespace = array_search($dstDirCandidate, $composer[$alKey]['psr-4']);
                if ($namespace === false) {
                    continue;
                }

                $namespace = trim($namespace, '\\');
                $dstDir = trim($dstDirCandidate, '/');

                break;
            }

            $options['dstFilePath'] = Path::join($dstDir, 'Phpstan.php');
            $options['namespace'] = $namespace;
            $options['srcFiles'] = (new Finder())
                ->in('./.phpstan')
                ->files()
                ->name("parameters.typeAliases.$scope.neon");

            // @phpstan-ignore-next-line
            $tasks["generate.PhpstanPhp.$scope"] = $this
                ->taskPhpstanGeneratePhp()
                ->setOptions($options);
        }

        return $tasks;
    }
}
