<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\Unit\Util;

use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sweetchuck\WebIdeConfigManager\Tests\Unit\TestBase;
use Sweetchuck\WebIdeConfigManager\Util\Helper;

#[CoversClass(Helper::class)]
class HelperTest extends TestBase
{

    #[Test]
    public function testNormalizeProductName(): void
    {
        $helper = $this->createHelper();
        static::assertSame('PhpStorm', $helper->normalizeProductName('PhpStorm'));
        static::assertSame('Ping', $helper->normalizeProductName('Ping'));
    }

    /**
     * @return array<string, mixed>
     */
    public static function casesJetBrainsDir(): array
    {
        return [
            'linux:with-home' => [
                'expected' => '/home/me/.config/JetBrains',
                'osFamily' => 'Linux',
                'envVars' => [
                    'HOME' => '/home/me',
                ],
            ],
            'linux:without-home' => [
                'expected' => null,
                'osFamily' => 'Linux',
                'envVars' => [],
            ],
            'darwin:with-home' => [
                'expected' => '/home/me/Library/Application Support/JetBrains',
                'osFamily' => 'Darwin',
                'envVars' => [
                    'HOME' => '/home/me',
                ],
            ],
            'darwin:without-home' => [
                'expected' => null,
                'osFamily' => 'Darwin',
                'envVars' => [],
            ],
            'windows:appdata' => [
                'expected' => '/home/me/MyAppData/JetBrains',
                'osFamily' => 'Windows',
                'envVars' => [
                    'APPDATA' => '/home/me/MyAppData',
                    'HOME' => '/home/me',
                ],
            ],
            'windows:home' => [
                'expected' => '/home/me/AppData/Roaming/JetBrains',
                'osFamily' => 'Windows',
                'envVars' => [
                    'HOME' => '/home/me',
                ],
            ],
            'windows:nothing' => [
                'expected' => null,
                'osFamily' => 'Windows',
                'envVars' => [],
            ],
        ];
    }

    /**
     * @phpstan-param array<string, mixed> $envVars
     *
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    #[Test]
    #[DataProvider('casesJetBrainsDir')]
    public function testJetBrainsDir(?string $expected, string $osFamily, array $envVars): void
    {
        $helper = $this->createPartialMock(
            Helper::class,
            [
                'osFamily',
            ],
        );
        $helper
            ->expects(static::once())
            ->method('osFamily')
            ->willReturn($osFamily);

        static::assertSame($expected, $helper->jetBrainsDir($envVars));
    }

    #[Test]
    public function testGetProductDir(): void
    {
        $vfsStructure = [
            'home' => [
                'me' => [
                    '.config' => [
                        'JetBrainsFile' => 'abc',
                        'JetBrains' => [
                            'PhpStorm1001.01' => [],
                            'PhpStorm1003.01' => [],
                            'PhpStorm1002.01' => [],
                            'TextFile1001.01' => 'abc',
                        ],
                    ],
                ],
            ],
        ];
        $vfs = vfsStream::setup(
            'root',
            0777,
            $vfsStructure,
        );

        $productName = 'PhpStorm';
        $jetBrainsDir = $vfs->url() . '/home/me/.config/JetBrains';
        $jetBrainsFile = $vfs->url() . '/home/me/.config/JetBrainsFile';
        $expected = "$jetBrainsDir/{$productName}1003.01";
        $envVars = [];

        /** @noinspection PhpUnhandledExceptionInspection */
        $helper = $this->createPartialMock(
            Helper::class,
            [
                'jetBrainsDir',
            ],
        );
        $helper
            ->expects(static::atLeast(1))
            ->method('jetBrainsDir')
            ->willReturn(null, $jetBrainsFile, $jetBrainsDir, $jetBrainsDir);

        static::assertSame(
            null,
            $helper->getProductDir($envVars, $productName),
            'JetBrains dir is not set, default product dir is exists.',
        );
        static::assertSame(
            null,
            $helper->getProductDir($envVars, $productName),
            'JetBrainsDir is exists, but the path is a file.',
        );
        static::assertSame(
            $expected,
            $helper->getProductDir($envVars, $productName),
            'Everything OK',
        );
        static::assertSame(
            null,
            $helper->getProductDir($envVars, 'Unknown'),
            'JetBrainsDir is exists, but the product dir is not.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function casesGetFinalTidy(): array
    {
        return [
            'global' => [
                'expected' => [
                    'enabled' => true,
                    'options' => [
                        'a' => 'global',
                    ],
                ],
                'strategy' => 'global',
                'global' => [
                    'enabled' => true,
                    'options' => [
                        'a' => 'global',
                    ],
                ],
                'local' => [
                    'enabled' => false,
                    'options' => [
                        'a' => 'local',
                        'b' => 'local',
                    ],
                ],
            ],
            'merge' => [
                'expected' => [
                    'enabled' => false,
                    'options' => [
                        'a' => 'local',
                        'b' => 'local',
                        'd' => 'local',
                        'c' => 'global',
                    ],
                ],
                'strategy' => 'merge',
                'global' => [
                    'enabled' => true,
                    'options' => [
                        'a' => 'global',
                        'b' => 'global',
                        'c' => 'global',
                    ],
                ],
                'local' => [
                    'enabled' => false,
                    'options' => [
                        'a' => 'local',
                        'b' => 'local',
                        'd' => 'local',
                    ],
                ],
            ],
            'override' => [
                'expected' => [
                    'enabled' => false,
                    'options' => [
                        'a' => 'local',
                        'b' => 'local',
                        'd' => 'local',
                    ],
                ],
                'strategy' => 'override',
                'global' => [
                    'enabled' => true,
                    'options' => [
                        'a' => 'global',
                        'b' => 'global',
                        'c' => 'global',
                    ],
                ],
                'local' => [
                    'enabled' => false,
                    'options' => [
                        'a' => 'local',
                        'b' => 'local',
                        'd' => 'local',
                    ],
                ],
            ],
        ];
    }

    /**
     * @phpstan-param array<string, mixed> $expected
     * @phpstan-param array<string, mixed> $global
     * @phpstan-param array<string, mixed> $local
     */
    #[Test]
    #[DataProvider('casesGetFinalTidy')]
    public function testGetFinalTidy(
        array $expected,
        string $strategy,
        array $global,
        array $local,
    ): void {
        $helper = $this->createHelper();
        static::assertSame(
            $expected,
            $helper->getFinalTidy($strategy, $global, $local),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function casesRemoveXmlHeader(): array
    {
        // @todo Cases with Mac/Windows line endings.
        return [
            'basic with trailing space' => [
                'expected' => <<< 'XML'
                    <root>
                    </root>
                    XML,
                'original' => <<< 'XML'
                    <?xml version="1.0" ?>
                    <root>
                    </root>
                    XML,
            ],
            'basic without trailing space' => [
                'expected' => <<< 'XML'
                    <root>
                    </root>
                    XML,
                'original' => <<< 'XML'
                    <?xml version="1.0"?>
                    <root>
                    </root>
                    XML,
            ],
            'encoding' => [
                'expected' => <<< 'XML'
                    <root>
                    </root>
                    XML,
                'original' => <<< 'XML'
                    <?xml version="1.0" encoding="UTF-8"?>
                    <root>
                    </root>
                    XML,
            ],
            'without header' => [
                'expected' => <<< 'XML'
                    <root>
                    </root>
                    XML,
                'original' => <<< 'XML'
                    <root>
                    </root>
                    XML,
            ],
        ];
    }

    #[Test]
    #[DataProvider('casesRemoveXmlHeader')]
    public function testRemoveXmlHeader(string $expected, string $original): void
    {
        $helper = $this->createHelper();
        static::assertSame(
            $expected,
            $helper->removeXmlHeader($original),
        );
    }
}
