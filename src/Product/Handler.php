<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Product;

use Sweetchuck\WebIdeConfigManager\Component\Color\Handler as ColorHandler;
use Sweetchuck\WebIdeConfigManager\Component\FileTemplate\Handler as FileTemplateHandler;
use Sweetchuck\WebIdeConfigManager\Component\Template\Handler as TemplateHandler;

/**
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class Handler
{

    public function __construct(
        protected TemplateHandler $templateHandler,
        protected FileTemplateHandler $fileTemplateHandler,
        protected ColorHandler $colorHandler,
    ) {
    }

    /**
     * @phpstan-param JbcmProduct $product
     *
     * @return array<string, mixed>
     */
    public function getStatus(array $product): array
    {
        return [
            'templates' => $this->templateHandler->getStatus($product),
            'fileTemplates' => $this->fileTemplateHandler->getStatus($product),
            'colors' => $this->colorHandler->getStatus($product),
        ];
    }
}
