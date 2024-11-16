<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Product;

/**
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatus from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
interface StatusReporterInterface
{

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array;

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): static;

    /**
     * @phpstan-param JbcmProduct $product
     * @phpstan-param JbcmProductStatus $status
     */
    public function generate(array $product, array $status): static;
}
