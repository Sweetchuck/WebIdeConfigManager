<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\Unit\Product\StatusReporter;

use PHPUnit\Framework\Attributes\CoversClass;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Sweetchuck\WebIdeConfigManager\Product\StatusReporter\TableReporter;
use Sweetchuck\WebIdeConfigManager\Tests\Unit\TestBase;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @phpstan-import-type JbcmProduct       from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatus from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
#[CoversClass(TableReporter::class)]
class TableReporterTest extends TestBase
{

    public function testGenerate(): void
    {
        $output = new BufferedOutput();
        $reporterOptions = [
            'output' => $output,
            'statusFilter' => [
                ConfigStatus::Orphan->value => true,
                ConfigStatus::New->value => true,
                ConfigStatus::Changed->value => true,
                ConfigStatus::UpToDate->value => true,
            ],
        ];
        $reporter = new TableReporter();
        $reporter->setOptions($reporterOptions);
        $actualOptions = $reporter->getOptions();
        static::assertSame($reporterOptions['output'], $actualOptions['output']);
        static::assertSame($reporterOptions['statusFilter'], $actualOptions['statusFilter']);

        /** @phpstan-var JbcmProduct $product */
        $product = [
            'key' => 'phpstorm',
        ];
        /** @phpstan-var JbcmProductStatus $status */
        $status = [
            'templates' => [
                'files' => [
                    'myFile01.xml' => [
                        'officialFile' => $this->createSplFileInfo('official Content'),
                        'status' => ConfigStatus::Orphan,
                        'repositories' => [
                            'myRepo01' => [
                                'localFile' => null,
                                'changedPairs' => [
                                    'myTemplate01' => [
                                        'official' => 'official myTemplate01',
                                        'local' => '',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'myFile02.xml' => [
                        'officialFile' => null,
                        'status' => ConfigStatus::New,
                        'repositories' => [
                            'myRepo02' => [
                                'localFile' => $this->createSplFileInfo('local Content'),
                                'changedPairs' => [
                                    'myTemplate02' => [
                                        'official' => '',
                                        'local' => 'local myTemplate02',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'myFile03.xml' => [
                        'officialFile' => $this->createSplFileInfo('official Content'),
                        'status' => ConfigStatus::Changed,
                        'repositories' => [
                            'myRepo03' => [
                                'localFile' => $this->createSplFileInfo('local Content'),
                                'changedPairs' => [
                                    'myTemplate03' => [
                                        'official' => 'official myTemplate03',
                                        'local' => 'local myTemplate03',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'myFile04.xml' => [
                        'officialFile' => $this->createSplFileInfo('official Content'),
                        'status' => ConfigStatus::UpToDate,
                        'repositories' => [
                            'myRepo04' => [
                                'localFile' => $this->createSplFileInfo('local Content'),
                                'changedPairs' => [
                                    'myTemplate04' => [
                                        'official' => 'UpToDate myTemplate04',
                                        'local' => 'UpToDate myTemplate04',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            // @todo Test cases.
            'fileTemplates' => [
                'files' => [],
            ],
            // @todo Test cases.
            'colors' => [
                'files' => [],
            ],
        ];

        $reporter->generate($product, $status);

        $expected = <<< 'TABLE'
            +-----------+--------------+----------+--------------+
            | Component | Name         | Status   | Repositories |
            +-----------+--------------+----------+--------------+
            | templates | myFile01.xml | Orphan   | myRepo01     |
            | templates | myFile02.xml | New      | myRepo02     |
            | templates | myFile03.xml | Changed  | myRepo03     |
            | templates | myFile04.xml | UpToDate | myRepo04     |
            +-----------+--------------+----------+--------------+

            TABLE;

        static::assertSame($expected, $output->fetch());
    }
}
