<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\Unit\Product\StatusReporter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Sweetchuck\WebIdeConfigManager\Product\StatusReporter\DiffReporter;
use Sweetchuck\WebIdeConfigManager\Component\Template\Handler as TemplateHandler;
use Sweetchuck\WebIdeConfigManager\Tests\Unit\TestBase;
use Sweetchuck\WebIdeConfigManager\Util\Helper;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @phpstan-import-type JbcmProduct       from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatus from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
#[CoversClass(DiffReporter::class)]
class DiffReporterTest extends TestBase
{

    #[Test]
    public function testGenerate(): void
    {
        $output = new BufferedOutput();
        $helper = new Helper();
        $templateHandler = new TemplateHandler($helper);
        $reporterOptions = [
            'output' => $output,
            'statusFilter' => [
                ConfigStatus::Orphan->value => true,
                ConfigStatus::New->value => true,
                ConfigStatus::Changed->value => true,
                ConfigStatus::UpToDate->value => true,
            ],
        ];
        $reporter = new DiffReporter($templateHandler);
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
        ];

        $reporter->generate($product, $status);

        $expectedOrphan = <<< 'DIFF'
            ---null
            +++official://phpstorm/templates/myFile01.xml
            @@ @@
            +<templateSet group="Dummy">
            +    <template name="dummy_trigger_01" description="my desc" toReformat="true" toShortenFQNames="true">
            +    <context>
            +      <option name="PHP Class Member" value="true"/>
            +      <option name="PHP Trait Member" value="true"/>
            +    </context>
            +    <value><![CDATA[
            +official Content
            +
            +]]></value>
            +    </template>
            +</templateSet>

            DIFF;

        $expectedNew = <<< 'DIFF'
            ---repository://phpstorm/myRepo02/templates/myFile02.xml
            +++null
            @@ @@
            -<templateSet group="Dummy">
            -    <template name="dummy_trigger_01" description="my desc" toReformat="true" toShortenFQNames="true">
            -    <context>
            -      <option name="PHP Class Member" value="true"/>
            -      <option name="PHP Trait Member" value="true"/>
            -    </context>
            -    <value><![CDATA[
            -local Content
            -
            -]]></value>
            -    </template>
            -</templateSet>

            DIFF;

        $expectedChanged = <<< 'DIFF'
            ---repository://phpstorm/myRepo03/templates/myFile03.xml/myTemplate03
            +++official://phpstorm/templates/myFile03.xml/myTemplate03
            @@ @@
            -local myTemplate03
            +official myTemplate03

            DIFF;

        $expectedUpToDate = '';

        $expected = $expectedOrphan . $expectedNew . $expectedChanged . $expectedUpToDate;

        static::assertSame($expected, $output->fetch());
    }
}
