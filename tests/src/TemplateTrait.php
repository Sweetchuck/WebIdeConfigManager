<?php

declare(strict_types = 1);

namespace Sweetchuck\WebIdeConfigManager\Tests;

trait TemplateTrait
{
    /**
     * @phpstan-param "official"|"local" $type
     * @phpstan-param array<array<string, mixed>> $templates
     */
    protected static function generateTemplateFileContent(
        string $type,
        string $group,
        array $templates,
    ): string {
        /** @noinspection HtmlUnknownAttribute */
        $pattern = match ($type) {
            'official' => implode(
                "\n",
                [
                    '    <template name="{{ name }}" value="{{ value }}"{{ deactivated }}>',
                    '        <context>',
                    '            <option name="PHP Class Member" value="true"/>',
                    '        </context>',
                    '        ',
                    '    </template>',
                ],
            ),
            default => <<< 'TEXT'
                    <template name="{{ name }}">
                        <context>
                            <option name="PHP Class Member" value="true"/>
                        </context>
                        <value>
                <![CDATA[
                {{ value }}
                ]]>
                        </value>
                    </template>
                TEXT,
        };
        $content = sprintf('<templateSet group="%s">', $group);
        foreach ($templates as $template) {
            $content .= strtr(
                $pattern,
                [
                    '{{ name }}' => $template['name'],
                    '{{ value }}' => $template['value'],
                    '{{ deactivated }}' => !empty($template['deactivated']) ? ' deactivated="true"' : '',
                ],
            );
        }
        $content .= '</templateSet>' . "\n";

        return $content;
    }
}
