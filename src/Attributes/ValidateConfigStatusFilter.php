<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Attributes;

use Consolidation\AnnotatedCommand\Parser\CommandInfo;

/**
 * Annotated command input validator.
 *
 * @code
 * // Example usage.
 * #[ValidateStatusFilter(
 *   type: 'option',
 *   name: 'statusFilter',
 * )]
 * @endcode
 *
 * @see \Sweetchuck\WebIdeConfigManager\Commands\BaseHookCommand::onHookPreValidateConfigStatusFilter()
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class ValidateConfigStatusFilter
{

    public const string AC_SELECTOR = 'validate-jbcm-status-filter';

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
                'name' => $args['name'] ?? $args[1] ?? 'componentName',
                'config' => $args['config'] ?? $args[2] ?? [],
            ]),
        );
    }
}
