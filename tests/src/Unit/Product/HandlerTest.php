<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\Unit\Product;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\SkippedWithMessageException;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Sweetchuck\WebIdeConfigManager\Product\Handler;
use Sweetchuck\WebIdeConfigManager\Component\Color\Handler as ColorHandler;
use Sweetchuck\WebIdeConfigManager\Component\FileTemplate\Handler as FileTemplateHandler;
use Sweetchuck\WebIdeConfigManager\Component\Template\Handler as TemplateHandler;
use PHPUnit\Framework\TestCase;
use Sweetchuck\WebIdeConfigManager\Tests\TemplateTrait;
use Sweetchuck\WebIdeConfigManager\Util\Helper;
use Symfony\Component\Filesystem\Path;

/**
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
#[CoversClass(Handler::class)]
#[CoversClass(TemplateHandler::class)]
class HandlerTest extends TestCase
{

    use TemplateTrait;

    /**
     * @return array<string, mixed>
     */
    public static function casesGetStatus(): array
    {
        return [
            'empty' => [
                'expected' => [
                    'templates' => [
                        'files' => [],
                        'filesByStatus' => [],
                        'filesFromMultipleRepositories' => [],
                    ],
                ],
                'product' => [
                    'key' => 'phpstorm',
                    'dir' => 'JetBrains/PhpStorm2024.3',
                    'repositories' => [
                        'myRepo01' => [
                            'path' => 'myRepos/PhpStorm/myRepo01',
                        ],
                    ],
                ],
                'vfsStructure' => [],
            ],
            'basic' => [
                'expected' => [
                    'templates' => [
                        'files' => [
                            'changed-01.xml' => [],
                            'fresh-01.xml' => [],
                            'new-01.xml' => [],
                            'orphan-01.xml' => [],
                        ],
                        'filesByStatus' => [
                            ConfigStatus::Orphan->value => ['orphan-01.xml'],
                            ConfigStatus::New->value => ['new-01.xml'],
                            ConfigStatus::Changed->value => ['changed-01.xml'],
                            ConfigStatus::UpToDate->value => ['fresh-01.xml'],
                        ],
                        'filesFromMultipleRepositories' => [
                            'fresh-01.xml',
                        ],
                    ],
                ],
                'product' => [
                    'key' => 'phpstorm',
                    'dir' => 'JetBrains/PhpStorm2024.3',
                    'repositories' => [
                        'myRepo01' => [
                            'key' => 'myRepo01',
                            'path' => 'myRepos/PhpStorm/myRepo01',
                        ],
                        'myRepo02' => [
                            'key' => 'myRepo02',
                            'path' => 'myRepos/PhpStorm/myRepo02',
                        ],
                    ],
                ],
                'vfsStructure' => [
                    'JetBrains' => [
                        'PhpStorm2024.3' => [
                            'templates' => [
                                'orphan-01.xml' => static::generateTemplateFileContent(
                                    'official',
                                    'orphan-01',
                                    [
                                        [
                                            'name' => 'orphan-01-01-name',
                                            'value' => 'orphan-01-01-value',
                                        ],
                                    ],
                                ),
                                'fresh-01.xml' => static::generateTemplateFileContent(
                                    'official',
                                    'fresh-01',
                                    [
                                        [
                                            'name' => 'fresh-01-01-name',
                                            'value' => 'fresh-01-01-value',
                                        ],
                                    ],
                                ),
                                'changed-01.xml' => static::generateTemplateFileContent(
                                    'official',
                                    'changed-01',
                                    [
                                        [
                                            'name' => 'changed-01-01-name',
                                            'value' => 'changed-01-01-value-official',
                                        ],
                                    ],
                                ),
                            ],
                        ],
                    ],
                    'myRepos' => [
                        'PhpStorm' => [
                            'myRepo01' => [
                                'key' => 'myRepo01',
                                'templates' => [
                                    'new-01.xml' => static::generateTemplateFileContent(
                                        'local',
                                        'new-01',
                                        [
                                            [
                                                'name' => 'new-01-01-name',
                                                'value' => 'new-01-01-value',
                                            ],
                                        ],
                                    ),
                                    'fresh-01.xml' => static::generateTemplateFileContent(
                                        'local',
                                        'fresh-01',
                                        [
                                            [
                                                'name' => 'fresh-01-01-name',
                                                'value' => 'fresh-01-01-value',
                                            ],
                                        ],
                                    ),
                                    'changed-01.xml' => static::generateTemplateFileContent(
                                        'local',
                                        'changed-01',
                                        [
                                            [
                                                'name' => 'changed-01-01-name',
                                                'value' => 'changed-01-01-value-local',
                                            ],
                                        ],
                                    ),
                                ],
                            ],
                            'myRepo02' => [
                                'key' => 'myRepo02',
                                'templates' => [
                                    'fresh-01.xml' => static::generateTemplateFileContent(
                                        'local',
                                        'fresh-01',
                                        [
                                            [
                                                'name' => 'fresh-01-01-name',
                                                'value' => 'fresh-01-01-value',
                                            ],
                                        ],
                                    ),
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @phpstan-param array<string, mixed> $expected
     * @phpstan-param JbcmProduct $product
     * @phpstan-param array<string, mixed> $vfsStructure
     */
    #[DataProvider('casesGetStatus')]
    public function testGetStatus(
        array $expected,
        array $product,
        array $vfsStructure,
    ): void {
        if (!extension_loaded('tidy')) {
            throw new SkippedWithMessageException('PHP extension "tidy" is missing');
        }

        $vfs = vfsStream::setup(
            'root',
            0777,
            $vfsStructure,
        );
        $vfsUrl = $vfs->url();

        $product['dir'] = Path::join($vfsUrl, $product['dir']);
        foreach ($product['repositories'] as &$repository) {
            $repository['path'] = Path::join($vfsUrl, $repository['path']);
        }

        $helper = new Helper();
        $templateHandler = new TemplateHandler($helper);
        $fileTemplateHandler = new FileTemplateHandler();
        $colorHandler = new ColorHandler();
        $handler = new Handler(
            $templateHandler,
            $fileTemplateHandler,
            $colorHandler,
        );
        $actual = $handler->getStatus($product);

        if (isset($expected['templates']['files'])) {
            static::assertSame(
                array_keys($expected['templates']['files']),
                array_keys($actual['templates']['files']),
                'Template files mismatch',
            );
        }

        if (isset($expected['templates']['filesByStatus'])) {
            $expected['templates']['filesByStatus'] += [
                ConfigStatus::Orphan->value => [],
                ConfigStatus::New->value => [],
                ConfigStatus::Changed->value => [],
                ConfigStatus::UpToDate->value => [],
            ];

            static::assertSame(
                $expected['templates']['filesByStatus'],
                $actual['templates']['filesByStatus'],
                'Template files by status mismatch',
            );
        }

        if (isset($expected['templates']['filesFromMultipleRepositories'])) {
            static::assertSame(
                $expected['templates']['filesFromMultipleRepositories'],
                $actual['templates']['filesFromMultipleRepositories'],
                'Template files from multiple repositories mismatch',
            );
        }
    }
}
