<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sweetchuck\WebIdeConfigManager\Util\Helper;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\SplFileInfo;

class TestBase extends TestCase
{

    protected static function fixturesDir(): string
    {
        return Path::join('tests', 'fixtures');
    }

    protected function createHelper(): Helper
    {
        return new Helper();
    }

    protected function createSplFileInfo(string $body): SplFileInfo
    {
        $content = <<< XML
            <templateSet group="Dummy">
                <template name="dummy_trigger_01" description="my desc" toReformat="true" toShortenFQNames="true">
                <context>
                  <option name="PHP Class Member" value="true"/>
                  <option name="PHP Trait Member" value="true"/>
                </context>
                <value><![CDATA[
            $body

            ]]></value>
                </template>
            </templateSet>
            XML;

        $file = $this->createMock(SplFileInfo::class);
        $file
            ->expects(self::atLeast(0))
            ->method('getContents')
            ->willReturn($content);

        return $file;
    }
}
