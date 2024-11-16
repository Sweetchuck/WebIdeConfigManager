<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Product\StatusReporter;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Sweetchuck\WebIdeConfigManager\Component\Template\Handler as TemplateHandler;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatus from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class DiffReporter extends ReporterBase
{

    public function __construct(
        protected TemplateHandler $templateHandler,
    ) {
    }

    // region Output
    protected OutputInterface $output;

    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    public function setOutput(OutputInterface $output): static
    {
        $this->output = $output;

        return $this;
    }
    // endregion

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return [
            'output' => $this->getOutput(),
            'statusFilter' => $this->getStatusFilter(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options): static
    {
        if (array_key_exists('output', $options)) {
            $this->setOutput($options['output']);
        }

        if (array_key_exists('statusFilter', $options)) {
            $this->setStatusFilter($options['statusFilter']);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $product, array $status): static
    {
        $this
            ->generateTemplates($product, $status)
            ->generateFileTemplates($product, $status);

        return $this;
    }

    /**
     * @phpstan-param JbcmProduct $product
     * @phpstan-param JbcmProductStatus $status
     *
     * @todo Move this method into Template\Handler.
     */
    protected function generateTemplates(array $product, array $status): static
    {
        $output = $this->getOutput();
        $this->filterByStatus($status);
        foreach ($status['templates']['files'] as $relativePath => $entry) {
            $firstRepositoryKey = array_key_first($entry['repositories']);
            $headerPattern = "---%s\n+++%s\n";
            switch ($entry['status']) {
                case ConfigStatus::Orphan:
                    $output->write($this->getDiff(
                        sprintf(
                            $headerPattern,
                            'null',
                            "official://{$product['key']}/templates/$relativePath",
                        ),
                        '',
                        $this->templateHandler->convertToHumanReadable($entry['officialFile']->getContents()),
                    ));
                    break;

                case ConfigStatus::New:
                    $output->write($this->getDiff(
                        sprintf(
                            $headerPattern,
                            "repository://{$product['key']}/$firstRepositoryKey/templates/$relativePath",
                            'null',
                        ),
                        $entry['repositories'][$firstRepositoryKey]['localFile']->getContents(),
                        '',
                    ));
                    break;

                case ConfigStatus::Changed:
                    $firstRepositoryKey = array_key_first($entry['repositories']);
                    foreach ($entry['repositories'][$firstRepositoryKey]['changedPairs'] as $templateName => $changedPair) {
                        $output->write($this->getDiff(
                            sprintf(
                                $headerPattern,
                                "repository://{$product['key']}/$firstRepositoryKey/templates/$relativePath/$templateName",
                                "official://{$product['key']}/templates/$relativePath/$templateName",
                            ),
                            $changedPair['local'],
                            $changedPair['official'],
                        ));
                    }
                    break;
            }
        }

        return $this;
    }

    /**
     * @phpstan-param JbcmProduct $product
     * @phpstan-param JbcmProductStatus $status
     *
     * @todo Move this method into FileTemplate\Handler.
     */
    protected function generateFileTemplates(array $product, array $status): static
    {
        $output = $this->getOutput();
        $this->filterByStatus($status);
        foreach ($status['fileTemplates']['files'] as $relativePath => $entry) {
            $firstRepositoryKey = array_key_first($entry['repositories']);
            $headerPattern = "---%s\n+++%s\n";
            switch ($entry['status']) {
                case ConfigStatus::Orphan:
                    $output->write($this->getDiff(
                        sprintf(
                            $headerPattern,
                            'null',
                            "official://{$product['key']}/fileTemplates/$relativePath",
                        ),
                        '',
                        $entry['officialFile']->getContents(),
                    ));
                    break;

                case ConfigStatus::New:
                    $output->write($this->getDiff(
                        sprintf(
                            $headerPattern,
                            "repository://{$product['key']}/$firstRepositoryKey/fileTemplates/$relativePath",
                            'null',
                        ),
                        $entry['repositories'][$firstRepositoryKey]['localFile']->getContents(),
                        '',
                    ));
                    break;

                case ConfigStatus::Changed:
                    $firstRepositoryKey = array_key_first($entry['repositories']);
                    $output->write($this->getDiff(
                        sprintf(
                            $headerPattern,
                            "repository://{$product['key']}/$firstRepositoryKey/fileTemplates/$relativePath",
                            "official://{$product['key']}/fileTemplates/$relativePath",
                        ),
                        $entry['repositories'][$firstRepositoryKey]['localFile']->getContents(),
                        $entry['officialFile']->getContents(),
                    ));
                    break;
            }
        }

        return $this;
    }

    protected function getDiff(
        string $header,
        ?string $left,
        ?string $right,
    ): string {
        // Template\Handler::getChangedPairs() should not return equal pairs.
        assert(
            is_string($left) || is_string($right),
            'one of them has to be a string',
        );
        $diffBuilder = new UnifiedDiffOutputBuilder($header);
        $differ = new Differ($diffBuilder);

        return $differ->diff((string) $left, (string) $right);
    }
}
