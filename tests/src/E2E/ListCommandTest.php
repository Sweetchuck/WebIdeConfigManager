<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\E2E;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

class ListCommandTest extends TestBase
{

    #[Test]
    public function testListCommand(): void
    {
        $command = array_merge(
            $this->getJbcmCommand(),
            [
                '--format=json',
                'list',
            ],
        );
        $process = new Process($command);
        $exitCode = $process->run();
        static::assertSame('', $process->getErrorOutput());
        static::assertSame(0, $exitCode);

        $json = json_decode($process->getOutput(), true);
        static::assertIsArray($json);
        $expectedCommandNames = [
            '_complete',
            'completion',
            'help',
            'list',
            'config:adopt',
            'config:diff',
            'config:pull',
            'config:push',
            'config:status',
            'self:config:export',
            'self:config:validate',
        ];
        $actualCommandNames = [];
        foreach ($json['commands'] ?? [] as $item) {
            $actualCommandNames[] = $item['name'];
        }
        static::assertSame($expectedCommandNames, $actualCommandNames);
    }
}
