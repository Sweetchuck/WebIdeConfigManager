<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Product\StatusReporter;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @phpstan-import-type JbcmProduct                       from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatus                 from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatusTemplateFile     from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProductStatusFileTemplateFile from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class TableReporter extends ReporterBase
{
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

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        $options = parent::getOptions();
        $options['output'] = $this->getOutput();

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options): static
    {
        parent::setOptions($options);

        if (array_key_exists('output', $options)) {
            $this->setOutput($options['output']);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function generate(array $product, array $status): static
    {
        $columns = $this->getTableHeaders();
        $table = new Table($this->getOutput());
        $table->setHeaders($columns);
        $table->setRows($this->getTableRows($product, $status));
        $table->render();

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTableHeaders(): array
    {
        return [
            'component' => 'Component',
            'name' => 'Name',
            'status' => 'Status',
            'repositories' => 'Repositories',
        ];
    }

    /**
     * @phpstan-param JbcmProduct $product
     * @phpstan-param JbcmProductStatus $status
     *
     * @return array<array<mixed>>
     */
    public function getTableRows(array $product, array $status): array
    {
        $rows = [];
        $columns = $this->getTableHeaders();
        $this->filterByStatus($status);
        foreach ($status['templates']['files'] as $relativePathname => $entry) {
            $rows[] = $this->getTableRow('templates', $columns, $relativePathname, $entry);
        }

        foreach ($status['fileTemplates']['files'] as $relativePathname => $entry) {
            $rows[] = $this->getTableRow('fileTemplates', $columns, $relativePathname, $entry);
        }

        foreach ($status['colors']['files'] as $relativePathname => $entry) {
            $rows[] = $this->getTableRow('colors', $columns, $relativePathname, $entry);
        }

        return $rows;
    }

    /**
     * @phpstan-param array<string, string> $columns
     * @phpstan-param JbcmProductStatusTemplateFile|JbcmProductStatusFileTemplateFile $entry
     *
     * @return array<string, string>
     */
    public function getTableRow(
        string $componentName,
        array $columns,
        string $relativePathname,
        array $entry,
    ): array {
        $row = array_fill_keys(array_keys($columns), '');
        $row['component'] = $componentName;
        $row['name'] = $relativePathname;
        $row['status'] = $entry['status']->name;
        $row['repositories'] = implode(', ', array_keys($entry['repositories']));

        return $row;
    }
}
