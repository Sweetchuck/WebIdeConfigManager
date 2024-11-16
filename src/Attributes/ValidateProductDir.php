<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Attributes;

use Consolidation\AnnotatedCommand\Parser\CommandInfo;

/**
 * Annotated command input validator.
 *
 * @code
 * // Example usage.
 * #[ValidateProductDir(
 *   type: 'argument',
 *   name: 'productName',
 * )]
 * @endcode
 *
 * @see \Sweetchuck\WebIdeConfigManager\Commands\BaseHookCommand::onHookPreValidateProductDir()
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class ValidateProductDir
{

    public const string AC_SELECTOR = 'validate-jbcm-product-dir';

    /**
     * @phpstan-param array<string, mixed> $config
     */
    public function __construct(
        protected string $type,
        protected string $name,
        protected array $config = [],
    ) {
        $this->config += [];
    }

    /**
     * @phpstan-param \ReflectionAttribute<object> $attribute
     */
    public static function handle(
        \ReflectionAttribute $attribute,
        CommandInfo $commandInfo,
    ): void {
        $args = $attribute->getArguments();
        $commandInfo->addAnnotation(
            static::AC_SELECTOR,
            json_encode([
                'type' => $args['type'] ?? $args[0] ?? 'argument',
                'name' => $args['name'] ?? $args[1] ?? 'productName',
                'config' => $args['config'] ?? $args[2] ?? [],
            ]),
        );
    }
}
