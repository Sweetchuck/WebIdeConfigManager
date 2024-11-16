<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Commands;

use Consolidation\AnnotatedCommand\Attributes as Cli;
use Consolidation\AnnotatedCommand\CommandResult;
use Sweetchuck\WebIdeConfigManager\Util\ConfigValidator;

class SelfConfigCommand extends CommandBase
{

    protected ConfigValidator $configValidator;

    protected function initInjectDependencies(): static
    {
        parent::initInjectDependencies();
        $this->configValidator = $this->container->get('app.config.validator');

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    #[Cli\Command(
        name: 'self:config:export',
        aliases: ['sce'],
    )]
    #[Cli\Help(
        description: 'Exports Application configuration.',
    )]
    #[Cli\Option(
        name: 'format',
        description: 'Output format',
    )]
    #[Cli\Usage(
        name:  "| yq eval 'del(.env)'",
        description: 'Without environment variables.',
    )]
    public function cmdSelfConfigExportExecute(
        array $options = [
            'format' => 'yaml',
        ],
    ): CommandResult {
        return CommandResult::data($this->getConfig()->export());
    }

    #[Cli\Command(
        name: 'self:config:validate',
        aliases: ['scv'],
    )]
    #[Cli\Help(
        description: 'Validates the Application configuration.',
    )]
    public function cmdSelfConfigValidateExecute(): CommandResult
    {
        $data = $this->getConfig()->export();
        $result = $this->configValidator->validate($data);

        return CommandResult::data($result);
    }
}
