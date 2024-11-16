<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Component\Template;

use Sweetchuck\WebIdeConfigManager\Component\BaseHandler;
use Sweetchuck\WebIdeConfigManager\ConfigStatus;
use Sweetchuck\WebIdeConfigManager\Util\Helper;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * @phpstan-import-type JbcmTidy from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmRepository from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmProduct from \Sweetchuck\WebIdeConfigManager\Phpstan
 * @phpstan-import-type JbcmTemplateChangedPairs from \Sweetchuck\WebIdeConfigManager\Phpstan
 */
class Handler extends BaseHandler
{

    // region Tidy
    protected bool $isTidyEnabled = true;

    public function isTidyEnabled(): bool
    {
        return $this->isTidyEnabled;
    }

    public function setTidyEnabled(bool $value): static
    {
        $this->isTidyEnabled = $value;

        return $this;
    }

    /**
     * @var array<string, mixed>
     */
    protected array $tidyOptions = [
        'input-xml' => true,
        'output-xml' => true,
        'indent' => true,
        'indent-spaces' => 4,
        'indent-attributes'=> true,
    ];

    /**
     * @return array<string, mixed>
     */
    public function getTidyOptions(): array
    {
        return $this->tidyOptions;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setTidyOptions(array $options): static
    {
        $this->tidyOptions = $options;

        return $this;
    }

    /**
     * @phpstan-param JbcmTidy $tidy
     */
    public function setTidy(array $tidy): static
    {
        if (array_key_exists('enabled', $tidy)) {
            $this->setTidyEnabled($tidy['enabled']);
        }

        if (array_key_exists('options', $tidy)) {
            $this->setTidyOptions($tidy['options']);
        }

        return $this;
    }
    // endregion

    public function __construct(
        protected Helper $helper,
    ) {
    }

    public function getConfigSubDir(): string
    {
        return 'templates';
    }

    /**
     * @phpstan-param JbcmProduct $product
     *
     * @return array<string, mixed>
     */
    public function getStatus(array $product): array
    {
        $status = [
            'files' => [],
            'filesByStatus' => [
                ConfigStatus::Orphan->value => [],
                ConfigStatus::New->value => [],
                ConfigStatus::Changed->value => [],
                ConfigStatus::UpToDate->value => [],
            ],
            'filesFromMultipleRepositories' => [],
        ];

        // Add every official file as "orphan".
        // Their status will be updated later if it is found in any repository.
        foreach ($this->collectItemsFromProduct($product) as $file) {
            $relativePathname = $file->getRelativePathname();
            $status['files'][$relativePathname] = [
                'officialFile' => $file,
                'status' => ConfigStatus::Orphan,
                'repositories' => [],
            ];
        }

        /** @var JbcmRepository $repository */
        foreach ($product['repositories'] as $repository) {
            foreach ($this->collectItemsFromRepository($repository) as $localFile) {
                $relativePathname = $localFile->getRelativePathname();
                $status['files'][$relativePathname]['repositories'][$repository['key']] = [
                    'localFile' => $localFile,
                    'changedPairs' => $this->getChangedPairsFromFiles(
                        $localFile,
                        $status['files'][$relativePathname]['officialFile'] ?? null,
                    ),
                ];

                $status['files'][$relativePathname] += [
                    'officialFile' => null,
                ];
            }
        }

        /** @var string $relativePathname */
        foreach ($status['files'] as $relativePathname => $entry) {
            if ($entry['officialFile'] === null) {
                $configStatus = ConfigStatus::New;
            } elseif (count($entry['repositories']) === 0) {
                $configStatus = ConfigStatus::Orphan;
            } else {
                $firstRepositoryKey = array_key_first($entry['repositories']);
                $configStatus = empty($entry['repositories'][$firstRepositoryKey]['changedPairs'])
                    ? ConfigStatus::UpToDate
                    : ConfigStatus::Changed;
            }

            $status['files'][$relativePathname]['status'] = $configStatus;
            $status['filesByStatus'][$configStatus->value][] = $relativePathname;

            if (count($entry['repositories']) > 1) {
                $status['filesFromMultipleRepositories'][] = $relativePathname;
            }
        }

        ksort($status['files']);

        return $status;
    }

    /**
     * @return iterable<\Symfony\Component\Finder\SplFileInfo>
     */
    public function collectItemsFromConfigDir(string $configDir): iterable
    {
        $dir = Path::join($configDir, $this->getConfigSubDir());
        if (!is_dir($dir)) {
            return [];
        }

        return (new Finder())
            ->in($dir)
            ->files()
            ->name('*.xml');
    }

    public function convertToHumanReadable(string $official): string
    {
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($official);
        /** @var \DOMElement $template */
        foreach ($dom->getElementsByTagName('template') as $template) {
            if ($template->hasAttribute('deactivated')) {
                $template->removeAttribute('deactivated');
            }

            if (!$template->hasAttribute('value')) {
                // XML in "official" format always has "value" attribute.
                // In this case we can assume that the $official is already in
                // "humanReadable" format.
                // Maybe check that if the <value> tag is exists or not.
                return $official;
            }

            $value = $template->getAttribute('value');
            $template->removeAttribute('value');
            $valueElement = $dom->createElement('value');
            // Adds before-after new lines to make it Git diff friendly.
            $data = $dom->createCDATASection("\n$value\n");
            $valueElement->appendChild($data);
            $template->appendChild($valueElement);
        }

        $xml = $this->applyTidy((string) $dom->saveXML());

        return $this->helper->removeXmlHeader($xml);
    }

    /**
     * @param array<string, bool> $deactivatedStates
     */
    public function convertToOfficial(string $human, array $deactivatedStates = []): string
    {
        $dom = new \DOMDocument();
        $dom->loadXML($human);
        /** @var \DOMElement $template */
        foreach ($dom->getElementsByTagName('template') as $template) {
            // Make sure the attributes are in the "official" order.
            // name, value, ... and so on.
            // Attribute "name" first.
            $attributes = [
                'name' => $template->getAttribute('name'),
            ];

            // Attribute "value" second.
            $valueElements = $template->getElementsByTagName('value');
            if ($valueElements->count() > 0) {
                $attributes['value'] = $this->fetchContentFromValueElements($valueElements);
                foreach ($valueElements as $valueElement) {
                    $valueElement->remove();
                }
            } elseif ($template->hasAttribute('value')) {
                $attributes['value'] = $template->getAttribute('value');
            }

            // Every other attributes.
            /** @var \DOMAttr $attribute */
            foreach ($template->attributes as $attribute) {
                $attributes[$attribute->name] = $attribute->value;
            }

            // Restore the "deactivated" state.
            // This is the last attribute.
            if (!empty($deactivatedStates[$template->getAttribute('name')])) {
                $attributes['deactivated'] = 'true';
            }

            // Remove every attribute, because they might be in the wrong order.
            while ($template->hasAttributes()) {
                /** @var \DOMAttr $attribute */
                $attribute = $template->attributes->item(0);
                $template->removeAttribute($attribute->name);
            }

            // Add every attribute in the correct order.
            foreach ($attributes as $attrName => $attrValue) {
                $template->setAttribute($attrName, $attrValue);
            }
        }

        return $this->helper->removeXmlHeader((string) $dom->saveXML());
    }

    /**
     * @phpstan-return JbcmTemplateChangedPairs
     */
    public function getChangedPairsFromFiles(
        ?SplFileInfo $localFile,
        ?SplFileInfo $officialFile,
    ): array {
        if (!$localFile || !$officialFile) {
            return [];
        }

        return $this->getChangedPairs(
            $this->convertToHumanReadable($localFile->getContents()),
            $this->convertToHumanReadable($officialFile->getContents()),
        );
    }

    /**
     * Both $localXml and $officialXml must be in humanReadable format.
     *
     * @phpstan-return JbcmTemplateChangedPairs
     */
    public function getChangedPairs(?string $localXml, ?string $officialXml): array
    {
        if (!$localXml || !$officialXml) {
            return [];
        }

        $docs = [
            'local' => new \DOMDocument(),
            'official' => new \DOMDocument(),
        ];
        $docs['local']->loadXML($localXml);
        $docs['official']->loadXML($officialXml);

        $pairs = [];
        foreach ($docs as $source => $doc) {
            /** @var \DOMElement $template */
            foreach ($doc->getElementsByTagName('template') as $template) {
                $name = $template->getAttribute('name');
                if (!array_key_exists($name, $pairs)) {
                    $pairs[$name] = [
                        'local' => null,
                        'official' => null,
                    ];
                }

                $pairs[$name][$source] = (string) $template->ownerDocument->saveXML($template);
            }
        }

        $status = [];
        foreach ($pairs as $name => $pair) {
            if ($pair['local'] === $pair['official']) {
                continue;
            }

            $status[$name] = $pair;
        }

        return $status;
    }

    /**
     * @return array<string, bool>
     */
    public function getDeactivatedStates(string $officialXml): array
    {
        $dom = new \DOMDocument();
        if (!$dom->loadXML($officialXml)) {
            return [];
        }

        $states = [];
        /** @var \DOMElement $template */
        foreach ($dom->getElementsByTagName('template') as $template) {
            $name = $template->getAttribute('name');
            if ($name === '') {
                continue;
            }

            $states[$name] = $template->getAttribute('deactivated') === 'true';
        }

        return $states;
    }

    protected function applyTidy(string $xml): string
    {
        if (!$this->isTidyEnabled()
            || !extension_loaded('tidy')
            || !class_exists(\tidy::class)
        ) {
            return $xml;
        }

        $options = array_filter(
            $this->getTidyOptions(),
            fn (mixed $value): bool => $value !== null,
        );
        $tidy = new \tidy();
        $tidy->parseString($xml, $options);
        $tidy->cleanRepair();

        // @phpstan-ignore-next-line
        return ($tidy->root()?->value) . "\n";
    }

    /**
     * @param \DOMNodeList<\DOMElement> $valueElements
     */
    protected function fetchContentFromValueElements(\DOMNodeList $valueElements): string
    {
        if ($valueElements->count() === 0) {
            return '';
        }

        foreach ($valueElements->item(0)->childNodes as $child) {
            if ($child->nodeType === \XML_CDATA_SECTION_NODE) {
                // Removes the Git diff friendly before-after new lines.
                /** @var \DOMCdataSection $child */
                return mb_substr($child->nodeValue, 1, -1);
            }
        }

        return '';
    }
}
