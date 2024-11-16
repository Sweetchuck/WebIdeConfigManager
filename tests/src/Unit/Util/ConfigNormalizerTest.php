<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\Unit\Util;

use Consolidation\Config\Config;
use Consolidation\Config\Util\ConfigOverlay;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sweetchuck\WebIdeConfigManager\Tests\Unit\TestBase;
use Sweetchuck\WebIdeConfigManager\Util\ConfigNormalizer;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(ConfigNormalizer::class)]
class ConfigNormalizerTest extends TestBase
{

    /**
     * @return array<string, mixed>
     */
    public static function casesNormalize(): array
    {
        $fixturesDir = Path::join(static::fixturesDir(), 'Util', 'ConfigNormalizer');
        $files = (new Finder())
            ->in($fixturesDir)
            ->files()
            ->name('*.yml');

        $cases = [];
        foreach ($files as $file) {
            $name = $file->getFilenameWithoutExtension();
            $cases[$name] = Yaml::parseFile($file->getPathname());
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $base
     * @param array<string, mixed> $vfsStructure
     */
    #[Test]
    #[DataProvider('casesNormalize')]
    public function testNormalize(array $expected, array $base, array $vfsStructure): void
    {
        vfsStream::setup(
            'root',
            0777,
            $vfsStructure,
        );

        $config = new ConfigOverlay();
        $config->addContext('default', new Config($base));
        $normalizer = $this->createConfigNormalizer();
        $normalizer->normalize($config);
        static:: assertSame($expected, $config->export());
    }

    protected function createConfigNormalizer(): ConfigNormalizer
    {
        return new ConfigNormalizer(
            $this->createHelper(),
        );
    }
}
