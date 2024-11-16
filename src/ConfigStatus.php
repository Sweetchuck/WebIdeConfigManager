<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager;

enum ConfigStatus: int
{

    case Orphan = 1;

    case New = 2;

    case Changed = 3;

    case UpToDate = 4;

    /**
     * @return array<int, bool>
     */
    public static function createFilter(string $text): array
    {
        $cases = self::cases();
        $return = [];
        foreach ($cases as $case) {
            $return[$case->value] = $text === 'all';
        }

        if ($text === 'all') {
            return $return;
        }

        $mapping = [];
        foreach ($cases as $case) {
            $char = mb_strtolower(mb_substr($case->name, 0, 1));
            $mapping[$char] = $case;
        }

        $chars = implode('', array_keys($mapping));
        $pattern = '/^[' . $chars . ']+$/ui';
        if (preg_match($pattern, $text) === 1) {
            foreach (mb_str_split($text) as $char) {
                $char = mb_strtolower($char);
                $return[$mapping[$char]->value] = true;
            }

            return $return;
        }

        $validNames = [];
        $mapping = [];
        foreach ($cases as $case) {
            $validNames[] = $case->name;
            $mapping[mb_strtolower($case->name)] = $case;
        }
        $parts = array_filter(
            explode(',', mb_strtolower($text)),
            fn($part) => mb_strlen($part) > 0,
        );
        foreach ($parts as $part) {
            if (!isset($mapping[$part])) {
                throw new \InvalidArgumentException(sprintf(
                    'Unknown config status filter part: "%s"; Valid names are: %s',
                    $part,
                    implode(', ', $validNames),
                ));
            }

            $return[$mapping[$part]->value] = true;
        }

        return $return;
    }
}
