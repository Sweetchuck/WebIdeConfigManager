<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfigStatus::class)]
class ConfigStatusTest extends TestCase
{

    /**
     * @phpstan-return array<string, array{expected: array<int, bool>, text: string}>
     */
    public static function createFilterSuccessCases(): array
    {
        return [
            'empty' => [
                'expected' => [
                    ConfigStatus::Orphan->value => false,
                    ConfigStatus::New->value => false,
                    ConfigStatus::Changed->value => false,
                    ConfigStatus::UpToDate->value => false,
                ],
                'text' => '',
            ],
            'all' => [
                'expected' => [
                    ConfigStatus::Orphan->value => true,
                    ConfigStatus::New->value => true,
                    ConfigStatus::Changed->value => true,
                    ConfigStatus::UpToDate->value => true,
                ],
                'text' => 'all',
            ],
            'first char o' => [
                'expected' => [
                    ConfigStatus::Orphan->value => true,
                    ConfigStatus::New->value => false,
                    ConfigStatus::Changed->value => false,
                    ConfigStatus::UpToDate->value => false,
                ],
                'text' => 'o',
            ],
            'first char O' => [
                'expected' => [
                    ConfigStatus::Orphan->value => true,
                    ConfigStatus::New->value => false,
                    ConfigStatus::Changed->value => false,
                    ConfigStatus::UpToDate->value => false,
                ],
                'text' => 'O',
            ],
            'first chars nu' => [
                'expected' => [
                    ConfigStatus::Orphan->value => false,
                    ConfigStatus::New->value => true,
                    ConfigStatus::Changed->value => false,
                    ConfigStatus::UpToDate->value => true,
                ],
                'text' => 'nu',
            ],
            'names single lower' => [
                'expected' => [
                    ConfigStatus::Orphan->value => false,
                    ConfigStatus::New->value => true,
                    ConfigStatus::Changed->value => false,
                    ConfigStatus::UpToDate->value => false,
                ],
                'text' => 'new',
            ],
            'names single upper' => [
                'expected' => [
                    ConfigStatus::Orphan->value => false,
                    ConfigStatus::New->value => true,
                    ConfigStatus::Changed->value => false,
                    ConfigStatus::UpToDate->value => false,
                ],
                'text' => 'NEW',
            ],
            'names multiple lower' => [
                'expected' => [
                    ConfigStatus::Orphan->value => false,
                    ConfigStatus::New->value => true,
                    ConfigStatus::Changed->value => false,
                    ConfigStatus::UpToDate->value => true,
                ],
                'text' => 'new,upToDate',
            ],
        ];
    }

    /**
     * @param array<int, bool> $expected
     */
    #[Test]
    #[DataProvider('createFilterSuccessCases')]
    public function createFilterSuccessTest(array $expected, string $text): void
    {
        static::assertSame($expected, ConfigStatus::createFilter($text));
    }

    /**
     * @phpstan-return array<string, mixed>
     */
    public static function createFilterFailCases(): array
    {
        return [
            'invalid char' => [
                'expected' => [],
                'text' => 'x',
            ],
            'invalid list' => [
                'expected' => [],
                'text' => 'new,upToDate,unknown',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('createFilterFailCases')]
    public function createFilterFailTest(array $expected, string $text): void
    {
        $this->expectException($expected['exception'] ?? \InvalidArgumentException::class);

        if (array_key_exists('message', $expected)) {
            $this->expectExceptionMessage($expected['message']);
        }

        if (array_key_exists('code', $expected)) {
            $this->expectExceptionCode($expected['code']);
        }

        ConfigStatus::createFilter($text);
    }
}
