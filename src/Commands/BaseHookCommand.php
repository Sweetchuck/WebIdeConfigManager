<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Consolidation\AnnotatedCommand\Attributes\Hook;
use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\CommandError;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateComponentName;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateProductDir;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateProductName;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateProductRepositories;
use Sweetchuck\WebIdeConfigManager\Attributes\ValidateConfigStatusFilter;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Sweetchuck\WebIdeConfigManager\Util\ConfigNormalizer;
use Sweetchuck\WebIdeConfigManager\Util\Helper;
use Symfony\Component\Console\Input\InputInterface;

class BaseHookCommand extends CommandBase
{
    protected ConfigNormalizer $configNormalizer;

    protected Helper $helper;

    protected function init(InputInterface $input, AnnotationData $annotationData): static
    {
        parent::init($input, $annotationData);
        $this->initNormalizeConfig();

        return $this;
    }

    protected function initInjectDependencies(): static
    {
        parent::initInjectDependencies();
        $this->configNormalizer = $this->getContainer()->get('app.configNormalizer');
        $this->helper = $this->getContainer()->get('app.helper');

        return $this;
    }

    protected function initNormalizeConfig(): static
    {
        $this->configNormalizer->normalize($this->getConfig());

        return $this;
    }

    #[Hook(
        type: HookManager::PRE_ARGUMENT_VALIDATOR,
        selector: ValidateProductName::AC_SELECTOR,
    )]
    public function onHookPreValidateProductName(CommandData $commandData): ?CommandError
    {
        $annotationKey = ValidateProductName::AC_SELECTOR;
        $annotationData = $commandData->annotationData();
        if (!$annotationData->has($annotationKey)) {
            return null;
        }

        $args = json_decode($annotationData->get($annotationKey), true);
        $inputType = $args['type'];
        $inputName = $args['name'];

        $value = $inputType === 'option'
            ? $commandData->input()->getOption($inputName)
            : $commandData->input()->getArgument($inputName);

        $errorMsgPrefix = sprintf(
            '%s %s',
            $inputType,
            $inputType === 'option' ? "--$inputName" : $inputName,
        );

        $value = $this->helper->normalizeProductName($value);
        if (!in_array($value, Helper::SUPPORTED_PRODUCTS)) {
            return new CommandError(
                sprintf(
                    '%s "%s" is not supported. Supported products: %s',
                    $errorMsgPrefix,
                    $value,
                    implode(', ', Helper::SUPPORTED_PRODUCTS),
                ),
                1,
            );
        }

        $inputType === 'option'
            ? $commandData->input()->setOption($inputName, $value)
            : $commandData->input()->setArgument($inputName, $value);

        return null;
    }

    #[Hook(
        type: HookManager::PRE_ARGUMENT_VALIDATOR,
        selector: ValidateComponentName::AC_SELECTOR,
    )]
    public function onHookPreValidateComponentName(CommandData $commandData): ?CommandError
    {
        $annotationKey = ValidateComponentName::AC_SELECTOR;
        $annotationData = $commandData->annotationData();
        if (!$annotationData->has($annotationKey)) {
            return null;
        }

        $args = json_decode($annotationData->get($annotationKey), true);
        $inputType = $args['type'];
        $inputName = $args['name'];

        $value = $inputType === 'option'
            ? $commandData->input()->getOption($inputName)
            : $commandData->input()->getArgument($inputName);

        $errorMsgPrefix = sprintf(
            '%s %s',
            $inputType,
            $inputType === 'option' ? "--$inputName" : $inputName,
        );

        $value = $this->helper->normalizeComponentName($value);
        if (!in_array($value, Helper::SUPPORTED_COMPONENTS)) {
            return new CommandError(
                sprintf(
                    '%s "%s" is not supported. Supported components: %s',
                    $errorMsgPrefix,
                    $value,
                    implode(', ', Helper::SUPPORTED_COMPONENTS),
                ),
                1,
            );
        }

        $inputType === 'option'
            ? $commandData->input()->setOption($inputName, $value)
            : $commandData->input()->setArgument($inputName, $value);

        return null;
    }

    #[Hook(
        type: HookManager::PRE_ARGUMENT_VALIDATOR,
        selector: ValidateProductDir::AC_SELECTOR,
    )]
    public function onHookPreValidateProductDir(CommandData $commandData): ?CommandError
    {
        $annotationData = $commandData->annotationData();
        if (!$annotationData->has(ValidateProductDir::AC_SELECTOR)) {
            return null;
        }

        $args = json_decode($annotationData->get(ValidateProductDir::AC_SELECTOR), true);
        $inputType = $args['type'];
        $inputName = $args['name'];

        $productName = $inputType === 'option'
            ? $commandData->input()->getOption($inputName)
            : $commandData->input()->getArgument($inputName);

        $errorMsgPrefix = sprintf(
            '%s %s',
            $inputType,
            $inputType === 'option' ? "--$inputName" : $inputName,
        );

        $productName = $this->helper->normalizeProductName($productName);
        $productDir = $this->helper->getProductDir((array) $this->getConfig()->get('env'), $productName);
        if (!$productDir) {
            return new CommandError(
                sprintf(
                    '%s "%s" has no directory.',
                    $errorMsgPrefix,
                    $productName,
                ),
                1,
            );
        }

        return null;
    }

    #[Hook(
        type: HookManager::PRE_ARGUMENT_VALIDATOR,
        selector: ValidateProductRepositories::AC_SELECTOR,
    )]
    public function onHookPreValidateProductRepositories(CommandData $commandData): ?CommandError
    {
        $annotationData = $commandData->annotationData();
        if (!$annotationData->has(ValidateProductRepositories::AC_SELECTOR)) {
            return null;
        }

        $args = json_decode($annotationData->get(ValidateProductRepositories::AC_SELECTOR), true);
        $inputType = $args['type'];
        $inputName = $args['name'];

        $productName = $inputType === 'option'
            ? $commandData->input()->getOption($inputName)
            : $commandData->input()->getArgument($inputName);

        $errorMsgPrefix = sprintf(
            '%s %s',
            $inputType,
            $inputType === 'option' ? "--$inputName" : $inputName,
        );

        $repositories = $this->getEnabledRepositories($productName);
        if (!$repositories) {
            return new CommandError(
                sprintf(
                    '%s "%s" has no repositories.',
                    $errorMsgPrefix,
                    $productName,
                ),
                1,
            );
        }

        return null;
    }

    #[Hook(
        type: HookManager::PRE_ARGUMENT_VALIDATOR,
        selector: ValidateConfigStatusFilter::AC_SELECTOR,
    )]
    public function onHookPreValidateConfigStatusFilter(CommandData $commandData): ?CommandError
    {
        $annotationKey = ValidateConfigStatusFilter::AC_SELECTOR;
        $annotationData = $commandData->annotationData();
        if (!$annotationData->has($annotationKey)) {
            return null;
        }

        $args = json_decode($annotationData->get($annotationKey), true);
        $inputType = $args['type'];
        $inputName = $args['name'];

        $value = $inputType === 'option'
            ? $commandData->input()->getOption($inputName)
            : $commandData->input()->getArgument($inputName);

        try {
            ConfigStatus::createFilter($value);
        } catch (\Throwable $exception) {
            $errorMsgPrefix = sprintf(
                '%s %s',
                $inputType,
                $inputType === 'option' ? "--$inputName" : $inputName,
            );

            return new CommandError(
                sprintf(
                    '%s %s',
                    $errorMsgPrefix,
                    $exception->getMessage(),
                ),
                1,
            );
        }

        return null;
    }
}
