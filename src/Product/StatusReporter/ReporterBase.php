<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Product\StatusReporter;

use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Sweetchuck\WebIdeConfigManager\Product\StatusReporterInterface;

/**
 * @phpstan-import-type JbcmProductStatus from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
abstract class ReporterBase implements StatusReporterInterface
{

    // region StatusFilter
    /**
     * @var array<int, bool>
     */
    protected array $statusFilter = [
        ConfigStatus::Orphan->value => false,
        ConfigStatus::New->value => true,
        ConfigStatus::Changed->value => true,
        ConfigStatus::UpToDate->value => false,
    ];

    /**
     * @return array<int, bool>
     */
    public function getStatusFilter(): array
    {
        return $this->statusFilter;
    }

    /**
     * @param array<int, bool> $statusFilter
     */
    public function setStatusFilter(array $statusFilter): static
    {
        $this->statusFilter = $statusFilter;

        return $this;
    }
    // endregion

    /**
     * {@inheritdoc}
     */
    public function getOptions(): array
    {
        return [
            'statusFilter' => $this->getStatusFilter(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options): static
    {
        if (array_key_exists('statusFilter', $options)) {
            $this->setStatusFilter($options['statusFilter']);
        }

        return $this;
    }

    /**
     * @phpstan-param JbcmProductStatus $status
     */
    protected function filterByStatus(array &$status): static
    {
        $filter = $this->getStatusFilter();
        $callback = fn ($entry): bool => $filter[$entry['status']->value];
        if (!empty($status['templates']['files'])) {
            $status['templates']['files'] = array_filter($status['templates']['files'], $callback);
        }

        if (!empty($status['fileTemplates']['files'])) {
            $status['fileTemplates']['files'] = array_filter($status['fileTemplates']['files'], $callback);
        }

        if (!empty($status['colors']['files'])) {
            $status['colors']['files'] = array_filter($status['colors']['files'], $callback);
        }

        return $this;
    }
}
