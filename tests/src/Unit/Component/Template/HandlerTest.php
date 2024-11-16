<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\Unit\Component\Template;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\SkippedWithMessageException;
use Sweetchuck\WebIdeConfigManager\Component\Template\Handler as TemplateHandler;
use Sweetchuck\WebIdeConfigManager\Tests\Unit\TestBase;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

/**
 * @phpstan-import-type JbcmTidy from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmTemplateChangedPairs from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
#[CoversClass(TemplateHandler::class)]
class HandlerTest extends TestBase
{

    /**
     * @return array<string, array{inputXml: string, expected: array<string, bool>}>
     *
     * @noinspection HtmlUnknownAttribute
     */
    public static function casesGetDeactivatedStates(): array
    {
        return [
            'emptyTemplateSet' => [
                'expected' => [],
                'inputXml' => '<templateSet group="Dummy"></templateSet>',
            ],
            'singleTemplateActive' => [
                'expected' => ['template_01' => false],
                'inputXml' => '<templateSet group="Dummy"><template name="template_01" /></templateSet>',
            ],
            'singleTemplateDeactivated' => [
                'expected' => ['template_01' => true],
                'inputXml' => '<templateSet group="Dummy"><template name="template_01" deactivated="true" /></templateSet>',
            ],
            'multipleTemplatesMixed' => [
                'expected' => [
                    'template_01' => false,
                    'template_02' => true,
                    'template_03' => false,
                    'template_04' => true,
                ],
                'inputXml' => '<templateSet group="Dummy">'
                    . '<template name="template_01" />'
                    . '<template name="template_02" deactivated="true" />'
                    . '<template name="template_03" />'
                    . '<template name="template_04" deactivated="true" />'
                    . '</templateSet>',
            ],
        ];
    }

    /**
     * @param array<string, bool> $expected
     */
    #[Test]
    #[DataProvider('casesGetDeactivatedStates')]
    public function testGetDeactivatedStates(
        array $expected,
        string $inputXml,
    ): void {
        $handler = $this->createTemplateHandler();
        $actual = $handler->getDeactivatedStates($inputXml);

        static::assertSame($expected, $actual);
    }

    /**
     * @param array<string> $patterns
     *
     * @return array<string, array<string, mixed>>
     */
    protected static function casesTemplatePairs(array $patterns): array
    {
        $fixturesDir = Path::join(static::fixturesDir(), 'Template', 'Handler', 'pairs');
        $files = (new Finder())
            ->in($fixturesDir)
            ->files()
            ->name($patterns);

        $cases = [];
        foreach ($files as $file) {
            $parts = explode('.', $file->getFilename());
            $cases[$parts[0]][$parts[1]] = $file;
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    public static function casesTemplatePairsNormal(): array
    {
        return static::casesTemplatePairs([
            '*.official.xml',
            '*.humanNormal.xml',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function casesTemplatePairsTidy(): array
    {
        return static::casesTemplatePairs([
            '*.official.xml',
            '*.humanTidy.xml',
        ]);
    }

    #[Test]
    #[DataProvider('casesTemplatePairsNormal')]
    public function testConvertToHumanReadableNormal(
        SplFileInfo $official,
        SplFileInfo $humanNormal,
    ): void {
        $officialContent = $official->getContents();
        $processor = $this->createTemplateHandler();

        $processor->setTidy(['enabled' => false]);
        static::assertSame(
            $humanNormal->getContents(),
            $processor->convertToHumanReadable($officialContent),
            'humanNormal is OK',
        );
    }

    #[Test]
    #[DataProvider('casesTemplatePairsTidy')]
    public function testConvertToHumanReadableTidy(
        SplFileInfo $official,
        SplFileInfo $humanTidy,
    ): void {
        if (!extension_loaded('tidy')) {
            throw new SkippedWithMessageException('PHP extension "tidy" is missing');
        }

        $officialContent = $official->getContents();
        $processor = $this->createTemplateHandler();

        $processor->setTidy([
            'enabled' => true,
            'options' => $this->getDefaultTidyOptions(),
        ]);
        static::assertSame(
            $humanTidy->getContents(),
            $processor->convertToHumanReadable($officialContent),
            'humanTidy is OK',
        );
    }

    #[Test]
    #[DataProvider('casesTemplatePairsNormal')]
    public function testConvertToOfficialNormal(
        SplFileInfo $official,
        SplFileInfo $humanNormal,
    ): void {
        $processor = $this
            ->createTemplateHandler()
            ->setTidyEnabled(true)
            ->setTidyOptions($this->getDefaultTidyOptions());

        $expectedXml = new \DomDocument();
        $expectedXml->preserveWhiteSpace = false;
        $expectedXml->formatOutput = false;
        $expectedXml->loadXML($official->getContents());
        $expected = $expectedXml->saveXML();

        $actualXml = new \DomDocument();
        $actualXml->preserveWhiteSpace = false;
        $actualXml->formatOutput = false;
        $actualXml->loadXML($processor->convertToOfficial($humanNormal->getContents()));
        $actual = $actualXml->saveXML();

        static::assertSame($expected, $actual, 'Official is OK');
    }

    #[Test]
    #[DataProvider('casesTemplatePairsTidy')]
    public function testConvertToOfficialTidy(
        SplFileInfo $official,
        SplFileInfo $humanTidy,
    ): void {
        if (!extension_loaded('tidy')) {
            throw new SkippedWithMessageException('PHP extension "tidy" is missing');
        }

        $processor = $this
            ->createTemplateHandler()
            ->setTidyEnabled(true)
            ->setTidyOptions($this->getDefaultTidyOptions());

        $expectedXml = new \DomDocument();
        $expectedXml->preserveWhiteSpace = false;
        $expectedXml->formatOutput = false;
        $expectedXml->loadXML($official->getContents());
        $expected = $expectedXml->saveXML();

        $actualXml = new \DomDocument();
        $actualXml->preserveWhiteSpace = false;
        $actualXml->formatOutput = false;
        $actualXml->loadXML($processor->convertToOfficial($humanTidy->getContents()));
        $actual = $actualXml->saveXML();

        static::assertSame($expected, $actual, 'Official is OK');
    }

    /**
     * @return array<string, mixed>
     */
    public static function casesGetChangedPairsFromFiles(): array
    {
        $fixturesDir = Path::join(static::fixturesDir(), 'Template', 'Handler', 'changedPairs');
        $files = (new Finder())
            ->in($fixturesDir)
            ->files()
            ->name('*.xml')
            ->name('*.yml');

        $cases = [];
        foreach ($files as $file) {
            $parts = explode('.', $file->getFilename());

            if (!isset($cases[$parts[0]])) {
                $cases[$parts[0]] = [
                    'expected' => [],
                    'local' => null,
                    'official' => null,
                ];
            }

            if ($parts[1] === 'expected') {
                $cases[$parts[0]][$parts[1]] = Yaml::parseFile($file->getPathname());
            } else {
                $cases[$parts[0]][$parts[1]] = $file;
            }
        }

        return $cases;
    }

    /**
     * @phpstan-param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('casesGetChangedPairsFromFiles')]
    public function testGetChangedPairs(
        array $expected,
        ?SplFileInfo $local,
        ?SplFileInfo $official,
    ): void {
        $subject = $this->createTemplateHandler();
        static::assertSame($expected, $subject->getChangedPairsFromFiles($local, $official));
    }

    /**
     * @phpstan-return array<string, mixed>
     */
    public static function casesGetOrphanTemplates(): array
    {
        return [
            'empty' => [
                'expected' => [],
                'productDir' => 'home/me/.config/JetBrains/PhpStorm1001.01',
                'repositories' => [],
                'vfsStructure' => [
                    'home' => [
                        'me' => [
                            '.config' => [
                                'JetBrains' => [
                                    'PhpStorm1001.01' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'productDir not exists' => [
                'expected' => [],
                'productDir' => 'home/me/.config/JetBrains/PhpStorm1001.01',
                'repositories' => [],
                'vfsStructure' => [
                    'home' => [
                        'me' => [
                            '.config' => [
                                'JetBrains' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'no orphans' => [
                'expected' => [],
                'productDir' => 'home/me/JetBrains/PhpStorm1001.01',
                'repositories' => [
                    'a' => [
                        'path' => 'home/me/Documents/JetBrains/config/PhpStorm/a',
                    ],
                    'b' => [
                        'path' => 'home/me/Documents/JetBrains/config/PhpStorm/b',
                    ],
                ],
                'vfsStructure' => [
                    'home' => [
                        'me' => [
                            'JetBrains' => [
                                'PhpStorm1001.01' => [
                                    'templates' => [
                                        'a1.xml' => '',
                                        'a2.xml' => '',
                                        'b1.xml' => '',
                                    ],
                                ],
                            ],
                            'Documents' => [
                                'JetBrains' => [
                                    'config' => [
                                        'PhpStorm' => [
                                            'a' => [
                                                'templates' => [
                                                    'a1.xml' => '',
                                                    'a2.xml' => '',
                                                ],
                                            ],
                                            'b' => [
                                                'templates' => [
                                                    'b1.xml' => '',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'has orphans' => [
                'expected' => [
                    'b2.xml',
                ],
                'productDir' => 'home/me/JetBrains/PhpStorm1001.01',
                'repositories' => [
                    'a' => [
                        'path' => 'home/me/Documents/JetBrains/config/PhpStorm/a',
                    ],
                    'b' => [
                        'path' => 'home/me/Documents/JetBrains/config/PhpStorm/b',
                    ],
                ],
                'vfsStructure' => [
                    'home' => [
                        'me' => [
                            'JetBrains' => [
                                'PhpStorm1001.01' => [
                                    'templates' => [
                                        'a1.xml' => '',
                                        'a2.xml' => '',
                                        'b1.xml' => '',
                                        'b2.xml' => '',
                                    ],
                                ],
                            ],
                            'Documents' => [
                                'JetBrains' => [
                                    'config' => [
                                        'PhpStorm' => [
                                            'a' => [
                                                'templates' => [
                                                    'a1.xml' => '',
                                                    'a2.xml' => '',
                                                ],
                                            ],
                                            'b' => [
                                                'templates' => [
                                                    'b1.xml' => '',
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @phpstan-param array<string, string> $expected
     * @phpstan-param array<string, JbcmRepository> $repositories
     * @phpstan-param array<string, mixed> $vfsStructure
     */
    #[Test]
    #[DataProvider('casesGetOrphanTemplates')]
    public function testGetOrphanTemplates(
        array $expected,
        string $productDir,
        array $repositories,
        array $vfsStructure,
    ): void {
        $vfs = vfsStream::setup(
            __FUNCTION__,
            0777,
            $vfsStructure,
        );
        $vfsUrl = $vfs->url();
        $productDir = "$vfsUrl/$productDir";
        foreach ($repositories as &$repository) {
            $repository['path'] = "$vfsUrl/{$repository['path']}";
        }

        $handler = $this->createTemplateHandler();
        $orphanFiles = $handler->getOrphanItems($productDir, $repositories);
        static::assertSame($expected, array_keys($orphanFiles));
    }

    protected function createTemplateHandler(): TemplateHandler
    {
        return new TemplateHandler(
            $this->createHelper(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getDefaultTidyOptions(): array
    {
        return [
            'input-xml' => true,
            'output-xml' => true,
            'indent' => true,
            'indent-attributes' => true,
            'indent-spaces' => 4,
        ];
    }
}
