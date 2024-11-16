<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Util;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Yaml\Yaml;

class ConfigValidator
{

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function validate(array $data): array
    {
        return [];
    }
}
