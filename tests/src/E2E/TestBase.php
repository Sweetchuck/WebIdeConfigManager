<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Sweetchuck\WebIdeConfigManager\Tests\TemplateTrait;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class TestBase extends TestCase
{

    use TemplateTrait;

    protected Filesystem $fs;

    protected string $rootDir = '';

    /**
     * @var null|array<string>
     */
    protected ?array $jbcmCommand = null;

    /**
     * @return array<string>
     */
    protected function getJbcmCommand(): array
    {
        $this->initJbcmCommand();

        return $this->jbcmCommand;
    }

    protected function initJbcmCommand(): static
    {
        if ($this->jbcmCommand !== null) {
            return $this;
        }

        $commandJson = (string) getenv('JBCM_COMMAND');
        if ($commandJson) {
            $this->jbcmCommand = Yaml::parse($commandJson);

            return $this;
        }

        $this->jbcmCommand = [\PHP_BINARY];
        if (!$this->isPhpExtensionEnabled($this->jbcmCommand[0], 'tidy')) {
            $this->jbcmCommand[] = '-d';
            $this->jbcmCommand[] = 'extension=tidy';
        }
        $this->jbcmCommand[] = 'artifacts/jbcm.phar';
        $this->jbcmCommand[] = '--no-ansi';

        return $this;
    }

    protected function isPhpExtensionEnabled(string $phpExecutable, string $extension): bool
    {
        $command = [
            $phpExecutable,
            '-r',
            'exit(extension_loaded($argv[1]) ? 0 : 1);',
            $extension,
        ];

        $exitCode = new Process($command)->run();

        return $exitCode === 0;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->fs = new Filesystem();
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->deleteRootDir();
    }

    protected function initRootDir(): static
    {
        $this->rootDir = $this->fs->tempnam(sys_get_temp_dir(), 'deleteme-jbcm-');
        $this->fs->remove($this->rootDir);
        $this->fs->mkdir($this->rootDir, 0777 - umask());

        return $this;
    }

    protected function deleteRootDir(): static
    {
        if ($this->fs->exists($this->rootDir)) {
            $this->fs->remove($this->rootDir);
        }

        return $this;
    }

    protected static function fixturesDir(): string
    {
        return Path::join('tests', 'fixtures');
    }
}
