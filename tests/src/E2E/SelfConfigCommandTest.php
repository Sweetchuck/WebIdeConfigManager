<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

class SelfConfigCommandTest extends TestBase
{

    /**
     * {@inheritdoc}
     */
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->initRootDir();
    }

    #[Test]
    public function testSelfConfigExportCommand(): void
    {
        $envVars = [
            'HOME' => "{$this->rootDir}/home/me",
        ];

        $this->fs->dumpFile(
            "{$envVars['HOME']}/.config/jbcm/jbcm.products.yml",
            <<< 'YAML'
                stash:
                    jetBrainsDir: "${env.HOME}/Documents/JetBrains"
                products:
                    PhpStorm:
                        repositories:
                            myRepo01:
                                path: "${stash.jetBrainsDir}/PhpStorm/config/myRepo01"
                YAML,
        );
        $this->fs->mkdir("{$envVars['HOME']}/.config/JetBrains/PhpStorm2024.3/templates");

        $command = array_merge(
            $this->getJbcmCommand(),
            [
                'self:config:export',
                '--format=json',
            ],
        );
        $process = new Process($command, null, $envVars);
        $exitCode = $process->run();
        static::assertSame('', $process->getErrorOutput());
        static::assertSame(0, $exitCode);

        $json = json_decode($process->getOutput(), true);
        static::assertIsArray($json);
        $expected = [
            'stash' => [
                'jetBrainsDir' => "{$envVars['HOME']}/Documents/JetBrains",
            ],
            'products' => [
                'PhpStorm' => [
                    'repositories' => [
                        'myRepo01' => [
                            'path' => "{$envVars['HOME']}/Documents/JetBrains/PhpStorm/config/myRepo01",
                            'key' => 'myRepo01',
                            'enabled' => true,
                            'tidyStrategy' => 'global',
                        ],
                    ],
                    'key' => 'PhpStorm',
                    'dir' => "{$envVars['HOME']}/.config/JetBrains/PhpStorm2024.3",
                ],
            ],
        ];
        foreach ($expected as $key => $value) {
            static::assertArrayHasKey($key, $json);
            static::assertSame($value, $json[$key]);
        }
    }
}
