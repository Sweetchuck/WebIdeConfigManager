<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Symfony\Component\Process\Process;

class ConfigCommandTest extends TestBase
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
    public function testConfigStatusCommand(): void
    {
        $envVars = [
            'HOME' => "{$this->rootDir}/home/me",
        ];

        // SetUp - Official.
        // @todo Add fileTemplates.
        $this->fs->dumpFile(
            "{$envVars['HOME']}/.config/JetBrains/PhpStorm2024.3/templates/orphan.xml",
            static::generateTemplateFileContent(
                'official',
                'orphan',
                [
                    [
                        'name' => 'orphan-01',
                        'value' => 'orphan-01-value',
                    ],
                ],
            ),
        );

        // SetUp - Repositories.
        $jetBrainsDir = "{$envVars['HOME']}/Documents/JetBrains";
        $this->fs->mkdir("{$jetBrainsDir}/PhpStorm/config/myRepo01/templates");
        $this->fs->mkdir("{$jetBrainsDir}/PhpStorm/config/myRepo01/fileTemplates");

        // Setup - jbcm config.
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

        $command = array_merge(
            $this->getJbcmCommand(),
            [
                'config:status',
                '--format=json',
                'PhpStorm',
            ],
        );
        $process = new Process($command, null, $envVars);
        $exitCode = $process->run();
        static::assertSame('', $process->getErrorOutput());
        static::assertSame(0, $exitCode);

        $expected = [
            'templates' => [
                'files' => [
                    'orphan.xml' => [
                        'officialFile' => $envVars['HOME'] . '/.config/JetBrains/PhpStorm2024.3/templates/orphan.xml',
                        'status' => ConfigStatus::Orphan->name,
                        'repositories' => [],
                    ],
                ],
                'filesByStatus' => [
                    ConfigStatus::Orphan->value => ['orphan.xml'],
                    ConfigStatus::New->value => [],
                    ConfigStatus::Changed->value => [],
                    ConfigStatus::UpToDate->value => [],
                ],
                'filesFromMultipleRepositories' => [],
            ],
            'fileTemplates' => [
                'files' => [],
                'filesByStatus' => [
                    ConfigStatus::Orphan->value => [],
                    ConfigStatus::New->value => [],
                    ConfigStatus::Changed->value => [],
                    ConfigStatus::UpToDate->value => [],
                ],
                'filesFromMultipleRepositories' => [],
            ],
            'colors' => [
                'files' => [],
                'filesByStatus' => [
                    ConfigStatus::Orphan->value => [],
                    ConfigStatus::New->value => [],
                    ConfigStatus::Changed->value => [],
                    ConfigStatus::UpToDate->value => [],
                ],
                'filesFromMultipleRepositories' => [],
            ],
        ];
        $actual = json_decode($process->getOutput(), true);
        static::assertSame($expected, $actual);
    }

    #[Test]
    public function testConfigDiffCommand(): void
    {
        $envVars = [
            'HOME' => "{$this->rootDir}/home/me",
        ];

        // SetUp - Official.
        $this->fs->dumpFile(
            "{$envVars['HOME']}/.config/JetBrains/PhpStorm2024.3/templates/group-01.xml",
            static::generateTemplateFileContent(
                'official',
                'group-01',
                [
                    [
                        'name' => 'group-01-01',
                        'value' => 'group-01-01-official-value',
                    ],
                ],
            ),
        );

        // SetUp - Repositories.
        $jetBrainsDir = "{$envVars['HOME']}/Documents/JetBrains";
        $this->fs->dumpFile(
            "{$jetBrainsDir}/PhpStorm/config/myRepo01/templates/group-01.xml",
            static::generateTemplateFileContent(
                'local',
                'group-01',
                [
                    [
                        'name' => 'group-01-01',
                        'value' => 'group-01-01-local-value',
                    ],
                ],
            ),
        );

        // Setup - jbcm config.
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

        $command = array_merge(
            $this->getJbcmCommand(),
            [
                'config:diff',
                'PhpStorm',
            ],
        );
        $process = new Process($command, null, $envVars);
        $exitCode = $process->run();
        static::assertSame('', $process->getErrorOutput());
        static::assertSame(0, $exitCode);

        $expected = <<< 'DIFF'
            ---repository://PhpStorm/myRepo01/templates/group-01.xml/group-01-01
            +++official://PhpStorm/templates/group-01.xml/group-01-01
            @@ @@
                     </context>
                     <value>
             <![CDATA[
            -group-01-01-local-value
            +group-01-01-official-value
             ]]>
                     </value>
                 </template>


            DIFF;
        static::assertSame(
            $expected,
            $process->getOutput(),
        );
    }

    #[Test]
    public function testConfigPushCommand(): void
    {
        $envVars = [
            'HOME' => "{$this->rootDir}/home/me",
        ];

        // SetUp - Official.
        $officialGroup01FilePath = "{$envVars['HOME']}/.config/JetBrains/PhpStorm2024.3/templates/group-01.xml";
        $this->fs->dumpFile(
            $officialGroup01FilePath,
            static::generateTemplateFileContent(
                'official',
                'group-01',
                [
                    [
                        'name' => 'group-01-01',
                        'value' => 'group-01-01-official-value',
                        'deactivated' => true,
                    ],
                    [
                        'name' => 'group-01-02',
                        'value' => 'group-01-02-official-value',
                    ],
                ],
            ),
        );

        // SetUp - Repositories.
        $jetBrainsDir = "{$envVars['HOME']}/Documents/JetBrains";
        $localGroup01FilePath = "{$jetBrainsDir}/PhpStorm/config/myRepo01/templates/group-01.xml";
        $localGroup01Content = static::generateTemplateFileContent(
            'local',
            'group-01',
            [
                [
                    'name' => 'group-01-01',
                    'value' => 'group-01-01-local-value',
                ],
                [
                    'name' => 'group-01-02',
                    'value' => 'group-01-02-local-value',
                ],
            ],
        );
        $this->fs->dumpFile($localGroup01FilePath, $localGroup01Content);

        // Setup - jbcm config.
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

        $command = array_merge(
            $this->getJbcmCommand(),
            [
                'config:push',
                'PhpStorm',
            ],
        );
        $process = new Process($command, null, $envVars);
        $exitCode = $process->run();
        static::assertSame(0, $exitCode);
        static::assertStringEqualsFile(
            $officialGroup01FilePath,
            static::generateTemplateFileContent(
                'official',
                'group-01',
                [
                    [
                        'name' => 'group-01-01',
                        'value' => 'group-01-01-local-value',
                        'deactivated' => true,
                    ],
                    [
                        'name' => 'group-01-02',
                        'value' => 'group-01-02-local-value',
                    ],
                ],
            ),
        );
    }

    #[Test]
    public function testConfigPullCommand(): void
    {
        $envVars = [
            'HOME' => "{$this->rootDir}/home/me",
        ];

        // SetUp - Official.
        $officialGroup01FilePath = "{$envVars['HOME']}/.config/JetBrains/PhpStorm2024.3/templates/group-01.xml";
        $this->fs->dumpFile(
            $officialGroup01FilePath,
            static::generateTemplateFileContent(
                'official',
                'group-01',
                [
                    [
                        'name' => 'group-01-01',
                        'value' => 'group-01-01-official-value',
                    ],
                    [
                        'name' => 'group-01-02',
                        'deactivated' => true,
                        'value' => 'group-01-02-official-value',
                    ],
                ],
            ),
        );

        // SetUp - Repositories.
        $jetBrainsDir = "{$envVars['HOME']}/Documents/JetBrains";
        $localGroup01FilePath = "{$jetBrainsDir}/PhpStorm/config/myRepo01/templates/group-01.xml";
        $localGroup01Content = static::generateTemplateFileContent(
            'local',
            'group-01',
            [
                [
                    'name' => 'group-01-01',
                    'value' => 'group-01-01-local-value',
                ],
            ],
        );
        $this->fs->dumpFile($localGroup01FilePath, $localGroup01Content);

        // Setup - jbcm config.
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

        $command = array_merge(
            $this->getJbcmCommand(),
            [
                'config:pull',
                'PhpStorm',
            ],
        );
        $process = new Process($command, null, $envVars);
        $exitCode = $process->run();
        static::assertSame(0, $exitCode);

        /** @noinspection HtmlUnknownAttribute */
        $expected = <<< 'TEXT'
            <templateSet group="group-01">
                <template name="group-01-01">
                    <context>
                        <option name="PHP Class Member"
                                value="true" />
                    </context>
                    <value>
            <![CDATA[
            group-01-01-official-value
            ]]>
                    </value>
                </template>
                <template name="group-01-02">
                    <context>
                        <option name="PHP Class Member"
                                value="true" />
                    </context>
                    <value>
            <![CDATA[
            group-01-02-official-value
            ]]>
                    </value>
                </template>
            </templateSet>

            TEXT;

        static::assertStringEqualsFile(
            $localGroup01FilePath,
            $expected,
        );
    }

    #[Test]
    public function testConfigAdoptTemplatesCommand(): void
    {
        $envVars = [
            'HOME' => "{$this->rootDir}/home/me",
        ];

        // SetUp - Official.
        $officialGroup01FilePath = "{$envVars['HOME']}/.config/JetBrains/PhpStorm2024.3/templates/group-01.xml";
        $this->fs->dumpFile(
            $officialGroup01FilePath,
            static::generateTemplateFileContent(
                'official',
                'group-01',
                [
                    [
                        'name' => 'group-01-01',
                        'value' => 'group-01-01-official-value',
                    ],
                ],
            ),
        );

        // SetUp - Repositories.
        $jetBrainsDir = "{$envVars['HOME']}/Documents/JetBrains";
        $this->fs->mkdir("{$jetBrainsDir}/PhpStorm/config/myRepo01/templates");

        // Setup - jbcm config.
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

        $command = array_merge(
            $this->getJbcmCommand(),
            [
                'config:adopt',
                'PhpStorm',
                'templates',
                'group-01.xml',
                'myRepo01',
            ],
        );
        $process = new Process($command, null, $envVars);
        $exitCode = $process->run();

        // @todo Assert on stdError.
        static::assertSame('', $process->getOutput());
        static::assertSame(0, $exitCode);

        /** @noinspection HtmlUnknownAttribute */
        $expected = <<< 'TEXT'
            <templateSet group="group-01">
                <template name="group-01-01">
                    <context>
                        <option name="PHP Class Member"
                                value="true" />
                    </context>
                    <value>
            <![CDATA[
            group-01-01-official-value
            ]]>
                    </value>
                </template>
            </templateSet>

            TEXT;

        static::assertStringEqualsFile(
            "{$jetBrainsDir}/PhpStorm/config/myRepo01/templates/group-01.xml",
            $expected,
        );
    }
}
