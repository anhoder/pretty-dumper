<?php

declare(strict_types=1);

namespace Anhoder\PrettyDumper\Renderer;

use Anhoder\PrettyDumper\Formatter\DumpRenderRequest;
use Anhoder\PrettyDumper\Formatter\PrettyFormatter;
use Anhoder\PrettyDumper\Formatter\RenderedSegment;
use Anhoder\PrettyDumper\Support\ThemeRegistry;

final class WebRenderer
{
    private int $indentSize;
    private string $indentStyle;
    private bool $showExpressionMeta = false;
    private bool $themeToggleRendered = false;

    public function __construct(
        private PrettyFormatter $formatter,
        private ThemeRegistry $themes,
    ) {
        $config = $this->formatter->configuration();
        $this->indentSize = $config->indentSize();
        $this->indentStyle = $config->indentStyle();
    }

    /**
     * @param array<string, mixed> $options
     */
    public function render(DumpRenderRequest $request, array $options = []): string
    {
        $options = $this->sanitizeOptions($options);
        $preferJavascript = $this->evaluateBool($options['preferJavascript'] ?? null, true);
        $this->themeToggleRendered = false;
        $segment = $this->formatter->format($request);
        $metadata = $segment->metadata();
        $currentThemeMeta = $metadata['theme'] ?? null;
        $currentTheme = is_string($currentThemeMeta) && $currentThemeMeta !== '' ? $currentThemeMeta : 'auto';

        $themePreference = $this->stringOption($request->option('theme'), $this->formatter->configuration()->theme());
        if ($themePreference === '') {
            $themePreference = 'auto';
        }

        $showTableMeta = $this->evaluateBool(
            $request->option('showTableVariableMeta'),
            $this->formatter->configuration()->showTableVariableMeta(),
        );

        $this->showExpressionMeta = $showTableMeta;

        $themesMarkup = $this->renderThemePalette();
        $cssStyles = $this->renderCssStyles($currentTheme);

        $childrenMarkup = '';
        foreach ($segment->children() as $index => $child) {
            $childrenMarkup .= $this->renderNode($child, 0, $preferJavascript);
        }

        return sprintf(
            "<style>\n%s</style><div class=\"pretty-dump\" role=\"tree\" aria-label=\"Dump output\" data-theme=\"%s\" data-theme-preference=\"%s\" data-table-meta=\"%s\">%s%s</div>%s",
            $cssStyles,
            htmlspecialchars($currentTheme, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            htmlspecialchars($themePreference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $showTableMeta ? '1' : '0',
            $themesMarkup,
            $childrenMarkup,
            $this->interactionScript(),
        );
    }

    private function renderThemePalette(): string
    {
        $markup = '';
        foreach ($this->themes->all() as $profile) {
            $markup .= sprintf(
                '<span class="theme-profile" data-theme="%s" data-contrast="%0.2f"></span>',
                htmlspecialchars($profile->name(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                $profile->contrastRatio(),
            );
        }

        if ($markup === '') {
            return '';
        }

        return sprintf('<div class="theme-palette-meta" aria-hidden="true">%s</div>', $markup);
    }

    private function renderCssStyles(string $theme): string
    {
        $css = <<<'CSS'
            .pretty-dump {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 0.8rem;
                line-height: 1.45;
                background-color: __BACKGROUND__;
                color: __TEXT__;
                border-radius: 4px;
                padding: 0.75rem;
                border: 1px solid __BORDER__;
                max-width: 100%;
                overflow-x: auto;
            }

            .pretty-dump .node-type-array { color: __ARRAY__; font-weight: 600; }
            .pretty-dump .node-type-object { color: __OBJECT__; font-weight: 600; }
            .pretty-dump .node-type-string { color: __STRING__; }
            .pretty-dump .node-type-number { color: __NUMBER__; }
            .pretty-dump .node-type-bool { color: __BOOL__; font-weight: 600; }
            .pretty-dump .node-type-null { color: __NULL__; font-style: italic; }
            .pretty-dump .node-type-unknown { color: __UNKNOWN__; }
            .pretty-dump .node-type-notice { color: __NOTICE__; font-style: italic; }
            .pretty-dump .node-type-circular { color: __CIRCULAR__; font-style: italic; }
            .pretty-dump .node-type-context { color: __CONTEXT__; font-weight: 600; }
            .pretty-dump .node-type-performance { color: __PERFORMANCE__; font-size: 0.72rem; }
            .pretty-dump .node-type-exception { color: __EXCEPTION__; }

            .pretty-dump details {
                margin: 0.25rem 0;
                padding: 0;
                border: none;
                background: transparent;
            }

            .pretty-dump summary {
                position: relative;
                cursor: pointer;
                display: flex;
                align-items: center;
                gap: 0.4rem;
                font-weight: 600;
                padding: 0.15rem 0 0.15rem 1rem;
                list-style: none;
                color: inherit;
                width: 100%;
                box-sizing: border-box;
            }

            .pretty-dump summary::-webkit-details-marker {
                display: none;
            }

            .pretty-dump details > summary::before {
                content: "▸";
                display: inline-block;
                font-size: 0.7rem;
                transition: transform 0.2s ease;
                color: __ACCENT__;
                position: absolute;
                left: 0;
            }

            .pretty-dump details[open] > summary::before {
                content: "▾";
                transform: none;
            }

            .pretty-dump details[open] > summary {
                color: __ACCENT__;
            }

            .pretty-dump .node-children {
                margin-left: __INDENT__;
                border-left: 1px solid __BORDER__;
                padding-left: __INDENT__;
            }

            .pretty-dump .node-summary-label,
            .pretty-dump .node-label {
                /* flex: 1; */
                min-width: 0;
                /* white-space: nowrap; */
                /* overflow: hidden; */
                /* text-overflow: ellipsis; */
            }

            .pretty-dump [data-node-type="array"] > summary .node-summary-label,
            .pretty-dump [data-node-type="array"].node-inline .node-label {
                color: __ARRAY__;
            }

            .pretty-dump [data-node-type="object"] > summary .node-summary-label,
            .pretty-dump [data-node-type="object"].node-inline .node-label {
                color: __OBJECT__;
            }

            .pretty-dump [data-node-type="string"] > summary .node-summary-label,
            .pretty-dump [data-node-type="string"].node-inline .node-label {
                color: __STRING__;
            }

            .pretty-dump [data-node-type="array-item"].node-inline .node-label {
                color: __CONTEXT__;
            }

            .pretty-dump [data-node-type="number"] > summary .node-summary-label,
            .pretty-dump [data-node-type="number"].node-inline .node-label {
                color: __NUMBER__;
            }

            .pretty-dump [data-node-type="bool"] > summary .node-summary-label,
            .pretty-dump [data-node-type="bool"].node-inline .node-label {
                color: __BOOL__;
            }

            .pretty-dump [data-node-type="null"] > summary .node-summary-label,
            .pretty-dump [data-node-type="null"].node-inline .node-label {
                color: __NULL__;
            }

            .pretty-dump [data-node-type="unknown"] > summary .node-summary-label,
            .pretty-dump [data-node-type="unknown"].node-inline .node-label {
                color: __UNKNOWN__;
            }

            .pretty-dump [data-node-type="context"] > summary .node-summary-label,
            .pretty-dump [data-node-type="context"].node-inline .node-label {
                color: __CONTEXT__;
            }

            .pretty-dump [data-node-type="performance"] > summary .node-summary-label,
            .pretty-dump [data-node-type="performance"].node-inline .node-label {
                color: __PERFORMANCE__;
            }

            .pretty-dump .node-inline {
                position: relative;
                display: flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.15rem 0 0.15rem 1rem;
                width: 100%;
                box-sizing: border-box;
            }

            .pretty-dump .node-key {
                color: __UNKNOWN__;
                font-weight: 600;
            }

            .pretty-dump .node-separator {
                color: __NOTICE__;
                opacity: 0.8;
                margin: 0 0.25rem;
            }

            .pretty-dump .node-value {
                font-family: inherit;
            }

            .pretty-dump .node-type-label {
                color: __STACK_INDEX__;
            }

            .pretty-dump .node-value-string { color: __STRING__; }
            .pretty-dump .node-value-number { color: __NUMBER__; }
            .pretty-dump .node-value-bool { color: __BOOL__; }
            .pretty-dump .node-value-null { color: __NULL__; font-style: italic; }
            .pretty-dump .node-value-unknown { color: __UNKNOWN__; }
            .pretty-dump .node-value-array { color: __ARRAY__; font-weight: 600; }
            .pretty-dump .node-value-object { color: __OBJECT__; font-weight: 600; }
            .pretty-dump .node-value-context { color: __CONTEXT__; }
            .pretty-dump .node-value-performance {
                color: __PERFORMANCE__;
                font-size: 0.65rem;
            }
            .pretty-dump .node-value-exception { color: __EXCEPTION__; }

            .pretty-dump .node-expression {
                color: __STACK_INDEX__;
                font-style: italic;
                margin-left: 0.35rem;
            }

            .pretty-dump .node-key {
                color: __UNKNOWN__;
                font-weight: 600;
            }

            .pretty-dump .node-separator {
                color: __NOTICE__;
                opacity: 0.8;
                margin: 0 0.25rem;
            }

            .pretty-dump .node-value {
                font-family: inherit;
            }

            .pretty-dump .node-value-string { color: __STRING__; }
            .pretty-dump .node-value-number { color: __NUMBER__; }
            .pretty-dump .node-value-bool { color: __BOOL__; }
            .pretty-dump .node-value-null { color: __NULL__; font-style: italic; }
            .pretty-dump .node-value-unknown { color: __UNKNOWN__; }

            .pretty-dump .node-actions {
                display: flex;
                gap: 0.25rem;
                align-items: center;
                margin-left: 0.4rem;
                opacity: 0;
                visibility: hidden;
                pointer-events: none;
                transition: opacity 0.15s ease-in-out;
            }

            .pretty-dump summary:hover .node-actions,
            .pretty-dump summary:focus-within .node-actions,
            .pretty-dump .node-inline:hover .node-actions,
            .pretty-dump .node-inline:focus-within .node-actions {
                opacity: 1;
                visibility: visible;
                pointer-events: auto;
            }

            .pretty-dump .node-action {
                border: none;
                background: transparent;
                color: __STACK_INDEX__;
                cursor: pointer;
                padding: 0.1rem;
                border-radius: 3px;
            }

            .pretty-dump .node-action:hover,
            .pretty-dump .node-action:focus {
                background-color: rgba(37, 99, 235, 0.12);
                background-color: color-mix(in srgb, __ACCENT__ 12%, transparent 88%);
                color: __ACCENT__;
            }


            .pretty-dump .node-action.theme-toggle {
                font-size: 0.9rem;
            }

            .pretty-dump [data-node-type].search-result-target > summary,
            .pretty-dump [data-node-type].search-result-target.node-inline {
                border-radius: 6px;
                box-shadow: inset 0 0 0 2px __ACCENT__, 0 0 0 4px rgba(37, 99, 235, 0.22);
                box-shadow: inset 0 0 0 2px __ACCENT__, 0 0 0 4px color-mix(in srgb, __ACCENT__ 22%, transparent 78%);
            }

            .pretty-dump [data-node-type].search-result-target > summary .node-summary-label,
            .pretty-dump [data-node-type].search-result-target.node-inline .node-label {
                color: __ACCENT__;
                font-weight: 700;
            }

            .pretty-dump .search-highlight {
                background-color: rgba(37, 99, 235, 0.28);
                background-color: color-mix(in srgb, __ACCENT__ 28%, transparent 72%);
                border-radius: 4px;
                padding: 0.05rem 0.15rem;
            }

            .pretty-dump .pretty-dump-table-panel {
                margin-top: 1rem;
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }

            .pretty-dump .pretty-dump-table-card {
                border: 1px solid __BORDER__;
                border-radius: 8px;
                background: __BACKGROUND__;
                box-shadow: 0 12px 35px rgba(15, 23, 42, 0.2);
                overflow: hidden;
            }

            .pretty-dump .pretty-dump-table-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 0.75rem 1rem;
                background: __PANEL_BG__;
                border-bottom: 1px solid __BORDER__;
            }

            .pretty-dump .pretty-dump-table-heading {
                display: flex;
                flex-direction: column;
                gap: 0.15rem;
                min-width: 0;
            }

            .pretty-dump .pretty-dump-table-title {
                font-weight: 600;
                font-size: 0.9rem;
                /* white-space: nowrap; */
                /* overflow: hidden; */
                /* text-overflow: ellipsis; */
            }

            .pretty-dump .pretty-dump-table-meta {
                font-size: 0.75rem;
                color: __STACK_INDEX__;
                opacity: 0.9;
                /* white-space: nowrap; */
                /* overflow: hidden; */
                /* text-overflow: ellipsis; */
            }

            .pretty-dump .pretty-dump-table-close {
                border: none;
                background: transparent;
                color: __STACK_INDEX__;
                cursor: pointer;
                font-size: 0.95rem;
                line-height: 1;
                padding: 0.25rem;
                border-radius: 4px;
            }

            .pretty-dump .pretty-dump-table-close:hover,
            .pretty-dump .pretty-dump-table-close:focus {
                background: rgba(37, 99, 235, 0.12);
                background: color-mix(in srgb, __ACCENT__ 12%, transparent 88%);
                color: __ACCENT__;
            }

            .pretty-dump .pretty-dump-table-container {
                padding: 1rem;
                overflow: auto;
                background: __BACKGROUND__;
                max-height: 400px;
            }

            .pretty-dump .pretty-dump-table {
                border-collapse: collapse;
                width: 100%;
                font-family: inherit;
                font-size: 0.78rem;
                color: inherit;
            }

            .pretty-dump .pretty-dump-table th,
            .pretty-dump .pretty-dump-table td {
                border: 1px solid __BORDER__;
                padding: 0.35rem 0.6rem;
                text-align: left;
                vertical-align: top;
                max-width: 320px;
                /* white-space: nowrap; */
                /* overflow: hidden; */
                /* text-overflow: ellipsis; */
            }

            .pretty-dump .pretty-dump-table thead th {
                background: rgba(37, 99, 235, 0.12);
                background: color-mix(in srgb, __ACCENT__ 16%, transparent 84%);
                color: __ACCENT__;
                position: sticky;
                top: 0;
                z-index: 1;
            }

            .pretty-dump .pretty-dump-table tbody tr:nth-child(even) td {
                background: rgba(148, 163, 184, 0.08);
                background: color-mix(in srgb, __PANEL_BG__ 65%, transparent 35%);
            }

            .pretty-dump .pretty-dump-table tbody tr:hover td {
                background: rgba(37, 99, 235, 0.14);
                background: color-mix(in srgb, __ACCENT__ 14%, transparent 86%);
            }

            .pretty-dump .truncate-notice {
                color: __NOTICE__;
                font-style: italic;
                padding-left: 1.4rem;
            }

            .pretty-dump pre {
                margin: 0.4rem 0;
                padding: 0.5rem;
                background-color: __PANEL_BG__;
                border-radius: 4px;
                font-family: inherit;
                font-size: inherit;
                line-height: inherit;
                white-space: pre-wrap;
                word-break: break-word;
            }

            .pretty-dump .exception-trace {
                border: 1px solid __BORDER__;
                border-radius: 4px;
                margin: 0.75rem 0;
                background: __PANEL_BG__;
            }

            .pretty-dump .exception-trace summary {
                padding: 0.5rem 0.75rem;
                font-size: 0.78rem;
            }

            .pretty-dump .exception-details {
                padding: 0.6rem 0.75rem;
                border-bottom: 1px solid __BORDER__;
                display: grid;
                gap: 0.75rem;
            }

            .pretty-dump .exception-info {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.72rem;
            }

            .pretty-dump .exception-info th,
            .pretty-dump .exception-info td {
                padding: 0.2rem 0.3rem;
                text-align: left;
                vertical-align: top;
                border-bottom: 1px solid __BORDER__;
            }

            .pretty-dump .exception-info th {
                font-weight: 600;
                color: __STACK_FUNCTION__;
                width: 120px;
            }

            .pretty-dump .exception-info td {
                color: __STACK_FUNCTION__;
                word-break: break-word;
            }

            .pretty-dump .exception-summary {
                gap: 0.25rem;
                flex-wrap: wrap;
                align-items: baseline;
            }

            .pretty-dump .exception-summary-item {
                display: inline-flex;
                gap: 0.25rem;
                align-items: baseline;
                font-size: 0.72rem;
                background: rgba(37, 99, 235, 0.08);
                background: color-mix(in srgb, __ACCENT__ 8%, transparent 92%);
                color: __ACCENT__;
                border-radius: 4px;
                padding: 0.1rem 0.35rem;
            }

            .pretty-dump .exception-summary-label {
                font-weight: 600;
            }

            .pretty-dump .exception-summary-value {
                color: __STACK_FUNCTION__;
            }

            .pretty-dump .stack-frames {
                max-height: 320px;
                overflow-y: auto;
                background: transparent;
            }

            .pretty-dump .stack-frame {
                padding: 0.35rem 0.75rem;
                border-top: 1px solid __BORDER__;
                font-size: 0.65rem;
                display: flex;
                flex-direction: column;
                gap: 0.15rem;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                color: __STACK_FUNCTION__;
            }

            .pretty-dump .stack-frame:first-child {
                border-top: none;
            }

            .pretty-dump .stack-index {
                font-weight: 500;
                color: __STACK_INDEX__;
                font-size: 0.6rem;
                opacity: 0.9;
            }

            .pretty-dump .stack-function {
                color: __STACK_FUNCTION__;
                font-family: inherit;
                font-size: 0.68rem;
                font-weight: 500;
            }

            .pretty-dump .stack-location {
                color: __STACK_LOCATION__;
                font-size: 0.6rem;
                opacity: 0.85;
                font-family: inherit;
            }

            .pretty-dump .context-block {
                border: 1px solid __BORDER__;
                border-radius: 4px;
                color: __MUTED__;
                font-size: 0.68rem;
            }

            .pretty-dump .context-header {
                background: __PANEL_BG__;
                border-bottom: 1px solid __BORDER__;
                padding: 0.5rem;
                font-weight: 600;
            }

            .pretty-dump .context-content {
                padding: 0.25rem 0.75rem 0.75rem;
            }

            .pretty-dump .context-pre {
                margin: 0;
                padding: 0.5rem;
                background-color: __PANEL_BG__;
                border-radius: 4px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 0.68rem;
                line-height: 1.4;
                color: __STACK_FUNCTION__;
                white-space: pre-wrap;
                word-break: break-word;
            }

            .pretty-dump .theme-profile {
                display: none;
            }

            .pretty-dump .theme-palette-meta {
                display: none;
            }

            .pretty-dump .json-content {
                margin: 0.4rem 0 0.4rem 1rem;
                padding: 0.5rem;
                background-color: __PANEL_BG__;
                border-radius: 4px;
                font-family: inherit;
                font-size: inherit;
                line-height: inherit;
                white-space: pre-wrap;
                word-break: break-word;
            }

            .pretty-dump .json-key {
                color: __UNKNOWN__;
                font-weight: 600;
            }

            .pretty-dump .json-string {
                color: __STRING__;
            }

            .pretty-dump .json-number {
                color: __NUMBER__;
            }

            .pretty-dump .json-bool {
                color: __BOOL__;
                font-weight: 600;
            }

            .pretty-dump .json-null {
                color: __NULL__;
                font-style: italic;
            }
        CSS;

        $css = strtr($css, [
            '__BACKGROUND__' => 'var(--pd-background)',
            '__TEXT__' => 'var(--pd-text)',
            '__BORDER__' => 'var(--pd-border)',
            '__ARRAY__' => 'var(--pd-array)',
            '__OBJECT__' => 'var(--pd-object)',
            '__STRING__' => 'var(--pd-string)',
            '__NUMBER__' => 'var(--pd-number)',
            '__BOOL__' => 'var(--pd-bool)',
            '__NULL__' => 'var(--pd-null)',
            '__UNKNOWN__' => 'var(--pd-unknown)',
            '__NOTICE__' => 'var(--pd-notice)',
            '__CIRCULAR__' => 'var(--pd-circular)',
            '__CONTEXT__' => 'var(--pd-context)',
            '__PERFORMANCE__' => 'var(--pd-performance)',
            '__EXCEPTION__' => 'var(--pd-exception)',
            '__ACCENT__' => 'var(--pd-accent)',
            '__INDENT__' => $this->getIndentSpacing(),
            '__PANEL_BG__' => 'var(--pd-panel-bg)',
            '__STACK_FUNCTION__' => 'var(--pd-stack-function)',
            '__STACK_LOCATION__' => 'var(--pd-stack-location)',
            '__STACK_INDEX__' => 'var(--pd-stack-index)',
            '__MUTED__' => 'var(--pd-muted)',
        ]);

        $lightVariables = $this->themeVariablesFor('light');
        $darkVariables = $this->themeVariablesFor('dark');

        // auto 模式：默认使用浅色主题
        $css .= sprintf(
            "\n.pretty-dump[data-theme=\"auto\"], .pretty-dump[data-theme=\"auto\"] .pretty-dump-table-panel {%s}\n",
            $this->formatThemeVariables($lightVariables),
        );

        // auto 模式：当系统为深色模式时使用深色主题
        $css .= sprintf(
            "@media (prefers-color-scheme: dark) {\n  .pretty-dump[data-theme=\"auto\"], .pretty-dump[data-theme=\"auto\"] .pretty-dump-table-panel {%s}\n}\n",
            $this->formatThemeVariables($darkVariables),
        );

        $css .= sprintf(
            ".pretty-dump[data-theme=\"light\"], .pretty-dump[data-theme=\"light\"] .pretty-dump-table-panel {%s}\n",
            $this->formatThemeVariables($lightVariables),
        );

        $css .= sprintf(
            ".pretty-dump[data-theme=\"dark\"], .pretty-dump[data-theme=\"dark\"] .pretty-dump-table-panel {%s}\n",
            $this->formatThemeVariables($darkVariables),
        );

        // 全局页面背景色：默认浅色
        $css .= "\nbody:has(.pretty-dump[data-theme=\"auto\"]), body:has(.pretty-dump[data-theme=\"light\"]) {\n";
        $css .= "  background-color: #ffffff;\n";
        $css .= "  color: #1f2933;\n";
        $css .= "  transition: background-color 0.2s ease, color 0.2s ease;\n";
        $css .= "}\n";

        // 全局页面背景色：深色模式
        $css .= "\nbody:has(.pretty-dump[data-theme=\"dark\"]) {\n";
        $css .= "  background-color: #1f2933;\n";
        $css .= "  color: #f9fafb;\n";
        $css .= "  transition: background-color 0.2s ease, color 0.2s ease;\n";
        $css .= "}\n";

        // auto 模式下跟随系统主题
        $css .= "\n@media (prefers-color-scheme: dark) {\n";
        $css .= "  body:has(.pretty-dump[data-theme=\"auto\"]) {\n";
        $css .= "    background-color: #1f2933;\n";
        $css .= "    color: #f9fafb;\n";
        $css .= "  }\n";
        $css .= "}\n";

        return $css;
    }

    /**
     * @return array<string, string>
     */
    private function themeVariablesFor(string $theme): array
    {
        $normalizedTheme = $theme === 'dark' ? 'dark' : 'light';
        $colors = $this->getThemeColors($normalizedTheme);
        $isLightTheme = $normalizedTheme !== 'dark';

        $backgroundColor = $isLightTheme ? '#ffffff' : '#1f2933';
        $borderColor = $isLightTheme ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.16)';
        $textColor = $isLightTheme ? '#1f2933' : '#f9fafb';
        $panelBackground = $isLightTheme ? '#f8fafc' : '#27303f';
        $mutedColor = $isLightTheme ? '#64748b' : '#cbd5f5';
        $stackFunctionColor = $isLightTheme ? '#475569' : '#cbd5f5';
        $stackIndexColor = $isLightTheme ? '#94a3b8' : '#7f8ea9';
        $stackLocationColor = $isLightTheme ? '#a8b4cc' : '#6e809f';
        $keyColor = $colors['key'] ?? $colors['array'];
        $performanceColor = $colors['performance'] ?? $mutedColor;
        $exceptionColor = $colors['exception'] ?? $colors['object'];

        return [
            '--pd-background' => $backgroundColor,
            '--pd-text' => $textColor,
            '--pd-border' => $borderColor,
            '--pd-panel-bg' => $panelBackground,
            '--pd-array' => $colors['array'],
            '--pd-object' => $colors['object'],
            '--pd-string' => $colors['string'],
            '--pd-number' => $colors['number'],
            '--pd-bool' => $colors['bool'],
            '--pd-null' => $colors['null'],
            '--pd-unknown' => $colors['unknown'],
            '--pd-notice' => $colors['notice'],
            '--pd-circular' => $colors['circular'],
            '--pd-context' => $keyColor,
            '--pd-performance' => $performanceColor,
            '--pd-exception' => $exceptionColor,
            '--pd-accent' => $colors['array'],
            '--pd-stack-function' => $stackFunctionColor,
            '--pd-stack-index' => $stackIndexColor,
            '--pd-stack-location' => $stackLocationColor,
            '--pd-muted' => $mutedColor,
        ];
    }

    /**
     * @param array<string, string> $variables
     */
    private function formatThemeVariables(array $variables): string
    {
        $segments = [];
        foreach ($variables as $name => $value) {
            $segments[] = sprintf('%s:%s', $name, $value);
        }

        return implode(';', $segments) . ';';
    }

    /**
     * @return array<string, string>
     */
    private function getThemeColors(string $theme): array
    {
        // 根据主题返回颜色配置，与CLI端保持一致
        $isLightTheme = $theme !== 'dark';

        if ($isLightTheme) {
            return [
                'array' => '#2563eb',      // 蓝色 - 数组
                'object' => '#dc2626',     // 红色 - 对象
                'string' => '#059669',     // 绿色 - 字符串
                'number' => '#7c3aed',     // 紫色 - 数字
                'bool' => '#0891b2',       // 青色 - 布尔值
                'null' => '#6b7280',       // 灰色 - null
                'unknown' => '#f59e0b',    // 黄色 - unknown
                'notice' => '#9ca3af',     // 浅灰色 - 通知
                'circular' => '#9ca3af',   // 浅灰色 - 循环引用
                'key' => '#059669',        // 绿色 - 键名
                'performance' => '#6b7280', // 灰色 - 性能信息
                'exception' => '#dc2626',  // 红色 - 异常
            ];
        }

        return [
            'array' => '#60a5fa',      // 浅蓝色 - 数组
            'object' => '#f87171',     // 浅红色 - 对象
            'string' => '#34d399',     // 浅绿色 - 字符串
            'number' => '#a78bfa',     // 浅紫色 - 数字
            'bool' => '#22d3ee',       // 浅青色 - 布尔值
            'null' => '#9ca3af',       // 浅灰色 - null
            'unknown' => '#fbbf24',    // 浅黄色 - unknown
            'notice' => '#9ca3af',     // 浅灰色 - 通知
            'circular' => '#9ca3af',   // 浅灰色 - 循环引用
            'key' => '#34d399',        // 浅绿色 - 键名
            'performance' => '#9ca3af', // 浅灰色 - 性能信息
            'exception' => '#f87171',  // 浅红色 - 异常
        ];
    }

    private function getIndentSpacing(): string
    {
        if ($this->indentStyle === 'tabs') {
            return '2rem'; // Tab对应更大的缩进
        }

        // 根据空格数量计算缩进（1rem ≈ 4空格）
        $remSize = $this->indentSize / 4;
        return sprintf('%srem', $remSize);
    }

    private function renderNode(RenderedSegment $segment, int $depth, bool $preferJavascript): string
    {
        $metadata = $segment->metadata();

        $attributes = [
            'data-node-type' => $segment->type(),
            'data-depth' => (string) $depth,
            'class' => sprintf('node-type-%s', $segment->type()),
        ];

        if ($this->evaluateBool($metadata['truncated'] ?? false, false)) {
            $attributes['data-truncated'] = 'true';
        }

        $expression = $this->expressionMeta($segment);
        if ($expression !== null) {
            $attributes['data-expression'] = $expression;
        }

        if (array_key_exists('jsonValue', $metadata)) {
            $jsonAttribute = $this->encodeJsonAttribute($metadata['jsonValue']);
            if ($jsonAttribute !== null) {
                $attributes['data-json'] = $jsonAttribute;
            }
        }

        if ($segment->type() === 'exception') {
            $attributes['aria-label'] = 'Exception trace';
            $attributes['class'] .= ' exception-trace';
        }

        $attrString = $this->attributesToString($attributes);
        $content = htmlspecialchars($segment->content(), ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if ($segment->type() === 'exception') {
            return $this->renderExceptionTrace($segment, $attrString, $content);
        }

        if ($segment->type() === 'json') {
            return $this->renderJsonNode($segment, $depth, $attrString, $preferJavascript);
        }

        $children = $segment->children();

        if ($children === []) {
            if ($segment->type() === 'context') {
                $summaryClass = $preferJavascript ? 'node-summary' : 'node-summary keyboard-focusable';
                $headerContent = 'Context Information';
                if ($this->evaluateBool($metadata['truncatedStack'] ?? false, false)) {
                    $headerContent .= ' (truncated)';
                }

                $formattedLines = array_map(
                    fn (string $line): string => $this->escape($line),
                    explode("\n", $segment->content()),
                );
                $formattedContent = sprintf('<pre class="context-pre">%s</pre>', implode("\n", $formattedLines));

                $contextAttributes = $attributes;
                $contextAttributes['class'] .= ' context-block';
                $contextAttrString = $this->attributesToString($contextAttributes);

                return sprintf(
                    '<details %s><summary class="%s">%s</summary><div class="context-content">%s</div></details>',
                    $contextAttrString,
                    $summaryClass,
                    $this->escape($headerContent),
                    $formattedContent,
                );
            }

            $attributes['class'] .= ' node-inline';
            $attrString = $this->attributesToString($attributes);

            return sprintf('<div %s>%s%s</div>', $attrString, $this->renderValueSpan($segment), $this->renderNodeActions($segment, $depth));
        }

        if ($segment->type() === 'array-item') {
            $valueSegment = $children[0];

            if ($valueSegment->type() === 'exception') {
                $attributes['class'] .= ' node-branch';
                $attrString = $this->attributesToString($attributes);
                $summaryHtml = $this->renderArrayItemInlineContent($segment, $valueSegment, $depth);
                $childMarkup = $this->renderNode($valueSegment, $depth + 1, $preferJavascript);

                return sprintf(
                    '<details %s><summary class="node-summary">%s%s</summary><div class="node-children">%s</div></details>',
                    $attrString,
                    $summaryHtml,
                    $this->renderNodeActions($segment, $depth),
                    $childMarkup,
                );
            }

            if ($valueSegment->children() === []) {
                $attributes['class'] .= ' node-inline';
                $attrString = $this->attributesToString($attributes);

                return sprintf(
                    '<div %s>%s%s</div>',
                    $attrString,
                    $this->renderArrayItemInlineContent($segment, $valueSegment, $depth),
                    $this->renderNodeActions($segment, $depth),
                );
            }

            $attributes['class'] .= ' node-branch';
            if ($this->evaluateBool($valueSegment->metadata()['truncated'] ?? false, false)) {
                $attributes['data-truncated'] = 'true';
            }

            $attrString = $this->attributesToString($attributes);
            $summaryHtml = $this->renderArrayItemInlineContent($segment, $valueSegment, $depth);
            $childMarkup = $this->renderSegmentChildren($valueSegment, $depth + 1, $preferJavascript);

            return sprintf(
                '<details %s><summary class="node-summary">%s%s</summary><div class="node-children">%s</div></details>',
                $attrString,
                $summaryHtml,
                $this->renderNodeActions($segment, $depth),
                $childMarkup,
            );
        }

        $summaryClass = $preferJavascript ? 'node-summary' : 'node-summary keyboard-focusable';
        $childMarkup = '';
        foreach ($children as $child) {
            if (in_array($child->type(), ['notice', 'circular'], true)) {
                $childMarkup .= sprintf(
                    '<div class="truncate-notice" aria-live="polite">%s</div>',
                    $this->escape($child->content()),
                );

                continue;
            }

            $childMarkup .= $this->renderNode($child, $depth + 1, $preferJavascript);
        }

        $attrString = $this->attributesToString($attributes);

        return sprintf(
            '<details %s><summary class="%s"><span class="node-summary-label">%s</span>%s</summary><div class="node-children">%s</div></details>',
            $attrString,
            $summaryClass,
            $this->renderSummaryLabel($segment, $depth),
            $this->renderNodeActions($segment, $depth),
            $childMarkup,
        );
    }

    /**
     * @param array<string, string> $attributes
     */
    private function attributesToString(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = sprintf('%s="%s"', $key, htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        }

        return implode(' ', $parts);
    }

    private function renderSummaryLabel(RenderedSegment $segment, int $depth): string
    {
        $expression = $this->expressionMeta($segment);

        if ($segment->type() === 'array-item') {
            $valueSegment = $segment->children()[0] ?? null;
            $keyHtml = sprintf('<span class="node-key">%s</span>', $this->escape($segment->content()));

            if ($valueSegment === null) {
                return $keyHtml . $this->renderExpressionHtml($expression);
            }

            $separator = '<span class="node-separator">⇒</span>';
            $valueHtml = $this->renderValueSpan($valueSegment);

            return $keyHtml . ' ' . $separator . ' ' . $valueHtml;
        }

        return $this->renderValueSpan($segment);
    }

    /*
    private function truncateLabel(string $label, int $limit = 96): string
    {
        if (mb_strlen($label) <= $limit) {
            return $label;
        }

        return rtrim(mb_strimwidth($label, 0, $limit, '…', 'UTF-8'));
    }
    */

    private function renderNodeActions(RenderedSegment $segment, int $depth): string
    {
        $metadata = $segment->metadata();
        if (!array_key_exists('jsonValue', $metadata)) {
            return '';
        }

        $expressionValue = $this->expressionMeta($segment) ?? $segment->content();
        // $searchLabel = $this->escapeAttr(sprintf('Search within %s', $this->truncateLabel($expressionValue, 40)));
        // $copyLabel = $this->escapeAttr(sprintf('Copy %s as JSON', $this->truncateLabel($expressionValue, 40)));
        $searchLabel = $this->escapeAttr(sprintf('Search within %s', $expressionValue));
        $copyLabel = $this->escapeAttr(sprintf('Copy %s as JSON', $expressionValue));

        $buttons = [
            sprintf('<button type="button" class="node-action" data-action="search" aria-label="%s" title="Search">🔍</button>', $searchLabel),
            sprintf('<button type="button" class="node-action" data-action="copy" aria-label="%s" title="Copy as JSON">📋</button>', $copyLabel),
        ];

        if ($this->isTabularJsonStructure($metadata['jsonValue'])) {
            // $tableLabel = $this->escapeAttr(sprintf('Render %s as table', $this->truncateLabel($expressionValue, 40)));
            $tableLabel = $this->escapeAttr(sprintf('Render %s as table', $expressionValue));
            $buttons[] = sprintf('<button type="button" class="node-action" data-action="table" aria-label="%s" title="Render as table">📊</button>', $tableLabel);
        }

        if ($depth === 0 && !$this->themeToggleRendered) {
            $toggleLabel = $this->escapeAttr('切换主题');
            $buttons[] = sprintf('<button type="button" class="node-action theme-toggle" data-theme-action="toggle" data-theme-next="dark" aria-label="%s" title="%s">🌓</button>', $toggleLabel, $toggleLabel);
            $this->themeToggleRendered = true;
        }

        return sprintf('<span class="node-actions">%s</span>', implode('', $buttons));
    }

    private function isTabularJsonStructure(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (isset($value['__items__']) && is_array($value['__items__'])) {
            $value = $value['__items__'];
        }

        $rows = [];
        foreach ($value as $key => $row) {
            if (is_string($key) && \str_starts_with($key, '__')) {
                continue;
            }
            $rows[] = $row;
        }

        if ($rows === []) {
            return false;
        }

        $columns = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                return false;
            }

            $normalised = $this->normaliseTabularRow($row);
            if ($normalised === null) {
                return false;
            }

            foreach ($normalised as $columnKey => $cell) {
                if (!$this->isTabularCellValue($cell)) {
                    return false;
                }

                $columns[$columnKey] = true;
            }
        }

        return $columns !== [];
    }

    /**
     * @param array<array-key, mixed> $row
     * @return array<string, mixed>|null
     */
    private function normaliseTabularRow(array $row): ?array
    {
        if (isset($row['__items__']) && is_array($row['__items__'])) {
            $row = $row['__items__'];
        }

        $result = [];

        if (isset($row['properties']) && is_array($row['properties'])) {
            foreach ($row['properties'] as $key => $value) {
                if (is_string($key) && \str_starts_with($key, '__')) {
                    continue;
                }

                $result[(string) $key] = $value;
            }
        }

        foreach ($row as $key => $value) {
            if ($key === 'properties') {
                continue;
            }

            if (is_string($key) && \str_starts_with($key, '__')) {
                continue;
            }

            $result[(string) $key] = $value;
        }

        if ($result === []) {
            return null;
        }

        return $result;
    }

    private function isTabularCellValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_scalar($value)) {
            return true;
        }

        if (is_array($value)) {
            return true;
        }

        if (is_object($value)) {
            return true;
        }

        return false;
    }

    private function encodeJsonAttribute(mixed $value): ?string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderArrayItemInlineContent(RenderedSegment $segment, RenderedSegment $valueSegment, int $depth): string
    {
        $key = sprintf('<span class="node-key">%s</span>', $this->escape($segment->content()));
        $separator = '<span class="node-separator">⇒</span>';

        if ($valueSegment->type() === 'exception') {
            $firstLine = explode("\n", $valueSegment->content())[0] ?? '';
            $classes = ['node-value', 'node-value-exception'];
            $valueHtml = sprintf('<span class="%s">%s</span>', implode(' ', $classes), $this->escape($firstLine));
            $expression = $this->expressionMeta($valueSegment);
            $expressionHtml = $this->renderExpressionHtml($expression);

            return $key . ' ' . $separator . ' ' . $valueHtml . $expressionHtml;
        }

        $value = $this->renderValueSpan($valueSegment);

        return $key . ' ' . $separator . ' ' . $value;
    }

    private function renderSegmentChildren(RenderedSegment $segment, int $depth, bool $preferJavascript): string
    {
        if ($segment->children() === []) {
            return '';
        }

        $childMarkup = '';
        foreach ($segment->children() as $child) {
            if (in_array($child->type(), ['notice', 'circular'], true)) {
                $childMarkup .= sprintf(
                    '<div class="truncate-notice" aria-live="polite">%s</div>',
                    $this->escape($child->content()),
                );

                continue;
            }

            $childMarkup .= $this->renderNode($child, $depth, $preferJavascript);
        }

        return $childMarkup;
    }

    private function renderValueSpan(RenderedSegment $segment): string
    {
        $classes = ['node-value'];
        $typeClass = match ($segment->type()) {
            'string' => 'node-value-string',
            'number' => 'node-value-number',
            'bool' => 'node-value-bool',
            'null' => 'node-value-null',
            'unknown' => 'node-value-unknown',
            'array' => 'node-value-array',
            'object' => 'node-value-object',
            'context' => 'node-value-context',
            'performance' => 'node-value-performance',
            'exception' => 'node-value-exception',
            default => null,
        };

        if ($typeClass !== null) {
            $classes[] = $typeClass;
        }

        $content = $segment->content();
        $html = '';

        if (preg_match('/^(?<type>[a-z]+\(.*?\))\s+(?<value>.+)$/i', $content, $match)) {
            $typeLabel = $match['type'];
            $valueContent = $match['value'];
            $html .= sprintf('<span class="node-type-label">%s</span> ', $this->escape($typeLabel));
            $content = $valueContent;
        }

        $html .= sprintf('<span class="%s">%s</span>', implode(' ', $classes), $this->escape($content));

        $expression = $this->expressionMeta($segment);
        $htmlExpression = $this->renderExpressionHtml($expression);
        if ($htmlExpression !== '') {
            $html .= $htmlExpression;
        }

        return $html;
    }

    private function renderExpressionHtml(?string $expression): string
    {
        if (!$this->showExpressionMeta) {
            return '';
        }

        if ($expression === null || $expression === '') {
            return '';
        }

        return sprintf('<span class="node-expression">(%s)</span>', $this->escape($expression));
    }

    /**
     * 格式化函数参数显示
     * @param array<int|string, mixed> $args
     */
    private function formatArguments(array $args): string
    {
        if ($args === []) {
            return '';
        }

        $formatted = [];
        foreach ($args as $arg) {
            if (is_string($arg)) {
                $formatted[] = sprintf('\'%s\'', $arg);
            } elseif (is_numeric($arg)) {
                $formatted[] = (string)$arg;
            } elseif (is_bool($arg)) {
                $formatted[] = $arg ? 'true' : 'false';
            } elseif (is_null($arg)) {
                $formatted[] = 'null';
            } elseif (is_array($arg)) {
                $formatted[] = 'Array';
            } elseif (is_object($arg)) {
                $formatted[] = get_class($arg);
            } else {
                $formatted[] = gettype($arg);
            }
        }

        return implode(', ', $formatted);
    }

    /**
     * 渲染异常跟踪信息 - 使用完整的堆栈帧数据
     */
    private function renderExceptionTrace(RenderedSegment $segment, string $attrString, string $content): string
    {
        $stackFramesMeta = $segment->metadata()['stackFrames'] ?? [];
        $framesMarkup = '';

        if (is_array($stackFramesMeta) && $stackFramesMeta !== []) {
            $framesMarkup = '<div class="stack-frames">';

            foreach ($stackFramesMeta as $index => $frame) {
                if (!is_array($frame)) {
                    continue;
                }

                $functionName = isset($frame['function']) && is_string($frame['function']) ? $frame['function'] : 'unknown';
                $fileName = isset($frame['file']) && is_string($frame['file']) ? $frame['file'] : 'unknown';
                $lineNumber = isset($frame['line']) && is_numeric($frame['line']) ? (int) $frame['line'] : 0;
                $className = isset($frame['class']) && is_string($frame['class']) ? $frame['class'] : null;
                $typeSeparator = isset($frame['type']) && is_string($frame['type']) ? $frame['type'] : '';

                $fullFunction = $className !== null
                    ? sprintf('%s%s%s', $className, $typeSeparator, $functionName)
                    : $functionName;

                $args = isset($frame['args']) && is_array($frame['args']) ? $frame['args'] : [];
                $argsStr = $this->formatArguments($args);

                $framesMarkup .= sprintf(
                    '<div class="stack-frame">' .
                    '<div class="stack-index">#%d</div>' .
                    '<div class="stack-function">%s(%s)</div>' .
                    '<div class="stack-location">%s:%s</div>' .
                    '</div>',
                    $index,
                    $fullFunction,
                    $argsStr,
                    $fileName,
                    (string) $lineNumber,
                );
            }

            $framesMarkup .= '</div>';
        }

        $headerLines = explode("\n", $content);
        $filteredLines = [];
        $skippingTrace = false;

        foreach ($headerLines as $line) {
            if (str_starts_with($line, 'Trace')) {
                $skippingTrace = true;
                continue;
            }

            if ($skippingTrace) {
                $trimmed = ltrim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                    continue;
                }

                $skippingTrace = false;
            }

            $trimmedLine = trim($line);
            if ($trimmedLine === '') {
                continue;
            }

            $filteredLines[] = $trimmedLine;
        }

        $infoItems = [];
        foreach ($filteredLines as $line) {
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches) === 1) {
                $label = trim($matches[1]);
                $value = trim($matches[2]);
            } else {
                $label = 'Info';
                $value = $line;
            }

            $infoItems[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        $summaryItems = array_slice($infoItems, 0, 3);
        $summaryParts = [];
        foreach ($summaryItems as $item) {
            $summaryParts[] = sprintf(
                '<span class="exception-summary-item"><span class="exception-summary-label">%s</span><span class="exception-summary-value">%s</span></span>',
                $item['label'],
                $item['value']
            );
        }

        if ($summaryParts === []) {
            $summaryHtml = sprintf(
                '<span class="exception-summary-item"><span class="exception-summary-label">%s</span><span class="exception-summary-value">%s</span></span>',
                'Exception',
                'Details'
            );
        } else {
            $summaryHtml = implode('', $summaryParts);
        }

        $infoRows = '';
        foreach ($infoItems as $item) {
            $infoRows .= sprintf(
                '<tr><th>%s</th><td>%s</td></tr>',
                $item['label'],
                $item['value']
            );
        }

        $infoSection = $infoRows === ''
            ? ''
            : sprintf('<div class="exception-details"><table class="exception-info">%s</table></div>', $infoRows);

        return sprintf(
            '<details %s>' .
            '<summary class="node-summary exception-summary">%s</summary>' .
            '%s' .
            '%s' .
            '</details>',
            $attrString,
            $summaryHtml,
            $infoSection,
            $framesMarkup
        );
    }

    /**
     * Render a JSON node with syntax highlighting
     */
    private function renderJsonNode(RenderedSegment $segment, int $depth, string $attrString, bool $preferJavascript): string
    {
        $summaryClass = $preferJavascript ? 'node-summary' : 'node-summary keyboard-focusable';
        $headerContent = $this->escape($segment->content());

        $children = $segment->children();
        $jsonBodyContent = '';

        foreach ($children as $child) {
            if ($child->type() === 'json-body') {
                $jsonContent = $child->content();
                if ($jsonContent !== '') {
                    $highlightedJson = $this->highlightJsonForWeb($jsonContent);
                    $jsonBodyContent = sprintf('<pre class="json-content">%s</pre>', $highlightedJson);
                }
            }
        }

        return sprintf(
            '<details %s><summary class="%s"><span class="node-summary-label">%s</span>%s</summary><div class="node-children">%s</div></details>',
            $attrString,
            $summaryClass,
            $headerContent,
            $this->renderNodeActions($segment, $depth),
            $jsonBodyContent
        );
    }

    /**
     * Highlight JSON syntax for HTML output using single-pass scanning
     */
    private function highlightJsonForWeb(string $json): string
    {
        // Escape HTML first
        $json = htmlspecialchars($json, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $result = '';
        $len = strlen($json);
        $i = 0;

        while ($i < $len) {
            // Check for HTML entity &quot; (escaped double quote)
            if ($i + 6 <= $len && substr($json, $i, 6) === '&quot;') {
                $stringStart = $i;
                $i += 6; // Skip opening &quot;

                // Find closing &quot;, handling escaped content
                while ($i < $len) {
                    if ($i + 6 <= $len && substr($json, $i, 6) === '&quot;') {
                        $i += 6; // Include closing &quot;
                        break;
                    }
                    $i++;
                }

                $string = substr($json, $stringStart, $i - $stringStart);

                // Check if this is a key (followed by colon)
                $nextNonSpace = $i;
                while ($nextNonSpace < $len && $json[$nextNonSpace] === ' ') {
                    $nextNonSpace++;
                }

                $isKey = $nextNonSpace < $len && $json[$nextNonSpace] === ':';

                if ($isKey) {
                    $result .= '<span class="json-key">' . $string . '</span>';
                } else {
                    $result .= '<span class="json-string">' . $string . '</span>';
                }
                continue;
            }

            $char = $json[$i];

            // Handle numbers
            if (($char >= '0' && $char <= '9') || ($char === '-' && $i + 1 < $len && $json[$i + 1] >= '0' && $json[$i + 1] <= '9')) {
                $numberStart = $i;

                if ($char === '-') {
                    $i++;
                }

                while ($i < $len && $json[$i] >= '0' && $json[$i] <= '9') {
                    $i++;
                }

                if ($i < $len && $json[$i] === '.') {
                    $i++;
                    while ($i < $len && $json[$i] >= '0' && $json[$i] <= '9') {
                        $i++;
                    }
                }

                if ($i < $len && ($json[$i] === 'e' || $json[$i] === 'E')) {
                    $i++;
                    if ($i < $len && ($json[$i] === '+' || $json[$i] === '-')) {
                        $i++;
                    }
                    while ($i < $len && $json[$i] >= '0' && $json[$i] <= '9') {
                        $i++;
                    }
                }

                $number = substr($json, $numberStart, $i - $numberStart);
                $result .= '<span class="json-number">' . $number . '</span>';
                continue;
            }

            // Handle boolean true
            if ($i + 4 <= $len && substr($json, $i, 4) === 'true') {
                $result .= '<span class="json-bool">true</span>';
                $i += 4;
                continue;
            }

            // Handle boolean false
            if ($i + 5 <= $len && substr($json, $i, 5) === 'false') {
                $result .= '<span class="json-bool">false</span>';
                $i += 5;
                continue;
            }

            // Handle null
            if ($i + 4 <= $len && substr($json, $i, 4) === 'null') {
                $result .= '<span class="json-null">null</span>';
                $i += 4;
                continue;
            }

            // Everything else (brackets, braces, colons, commas, spaces, newlines)
            $result .= $char;
            $i++;
        }

        return $result;
    }

    /**
     * @param array<int|string, mixed> $options
     * @return array<string, mixed>
     */
    private function sanitizeOptions(array $options): array
    {
        $sanitized = [];

        foreach ($options as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function expressionMeta(RenderedSegment $segment): ?string
    {
        $metadata = $segment->metadata();
        $expression = $metadata['expression'] ?? null;

        return is_string($expression) && $expression !== '' ? $expression : null;
    }

    private function evaluateBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        return $default;
    }

    private function stringOption(mixed $value, string $default): string
    {
        return is_string($value) ? $value : $default;
    }

    private function interactionScript(): string
    {
        return <<<'SCRIPT'
<script>(function(){
  var dumps=document.querySelectorAll('.pretty-dump');
  var promptOverlay=null;
  var promptLabel=null;
  var promptInput=null;
  var promptConfirm=null;
  var promptCancel=null;
  var promptResolve=null;
  var toastContainer=null;
  var lastSearchTerm='';
  var themeToggleLabels={dark:'切换为深色主题',light:'切换为浅色主题'};
  var themeStorageKey='pretty-dumper.theme.preference';
  var storedThemePreference=readStoredTheme();

  dumps.forEach(function(dump){
    applyTheme(dump);
    dump._tableMetaEnabled=dump.getAttribute('data-table-meta')==='1';
    dump.addEventListener('click',function(event){
      var target=event.target instanceof Element?event.target:null;
      if(!target){return;}

      var themeControl=target.closest('[data-theme-action]');
      if(themeControl){
        var themeAction=themeControl.getAttribute('data-theme-action');
        if(themeAction==='toggle'){
          event.preventDefault();
          event.stopPropagation();
          event.stopImmediatePropagation();
          toggleTheme(dump);
        }
        return;
      }

      var button=target.closest('.node-action');
      if(!button){return;}
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();

      var node=button.closest('[data-node-type]');
      if(!node){return;}

      var action=button.getAttribute('data-action');
      if(action==='search'){
        showPrompt('Search within value','Enter search text',lastSearchTerm).then(function(term){
          if(typeof term!=='string'||term.trim()===''){
            return;
          }
          lastSearchTerm=term;
          performSearch(dump,node,term);
        });
        return;
      }

      if(action==='copy'){
        copyNodeJson(node);
        return;
      }

      if(action==='table'){
        renderNodeTable(node);
        return;
      }
    },true);
  });

  function applyTheme(dump){
    var palette=dump.querySelectorAll('.theme-profile');
    if(storedThemePreference==='light'||storedThemePreference==='dark'){
      setExplicitTheme(dump,storedThemePreference,{persist:false,broadcast:false});
      return;
    }

    if(!palette.length){return;}
    var preference=dump.getAttribute('data-theme-preference')||'auto';
    if(preference!=='auto'){
      dump.setAttribute('data-theme',preference);
      if(dump._tablePanel){
        dump._tablePanel.setAttribute('data-theme',preference);
      }
      updateThemeToggle(dump);
      ensureThemeObserver(dump);
      return;
    }

    var rootTheme=document.documentElement.getAttribute('data-theme');
    var prefersDark=window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches;
    var targetTheme=rootTheme==='dark'||rootTheme==='light'?rootTheme:(prefersDark?'dark':'light');
    dump.setAttribute('data-theme',targetTheme);
    if(dump._tablePanel){
      dump._tablePanel.setAttribute('data-theme',targetTheme);
    }
    ensureThemeObserver(dump);
    updateThemeToggle(dump);
  }

  function toggleTheme(dump){
    var preference=dump.getAttribute('data-theme-preference')||'auto';
    var currentTheme=(preference!=='auto'?preference:(dump.getAttribute('data-theme')||'light')).toLowerCase();
    if(currentTheme!=='dark'&&currentTheme!=='light'){currentTheme='light';}
    var nextTheme=currentTheme==='dark'?'light':'dark';
    setExplicitTheme(dump,nextTheme);
  }

  function setExplicitTheme(dump,theme,options){
    if(options===undefined){options={};}
    var shouldPersist=options.persist!==false;
    var shouldBroadcast=options.broadcast!==false;

    dump.setAttribute('data-theme-preference',theme);
    dump.setAttribute('data-theme',theme);
    if(dump._tablePanel){
      dump._tablePanel.setAttribute('data-theme',theme);
    }

    if(shouldPersist){
      persistStoredTheme(theme);
    }

    updateThemeToggle(dump);
    ensureThemeObserver(dump);

    if(shouldBroadcast){
      dumps.forEach(function(other){
        if(other===dump){return;}
        setExplicitTheme(other,theme,{persist:false,broadcast:false});
      });
    }
  }

  function updateThemeToggle(dump){
    var button=dump.querySelector('[data-theme-action="toggle"]');
    if(!button){return;}
    var current=(dump.getAttribute('data-theme')||'').toLowerCase();
    if(current!=='dark'&&current!=='light'){current='light';}
    var next=current==='dark'?'light':'dark';
    var label=themeToggleLabels[next]||'切换主题';
    button.setAttribute('aria-label',label);
    button.setAttribute('title',label);
    button.setAttribute('data-theme-next',next);
  }

  function readStoredTheme(){
    try{
      var value=window.localStorage?window.localStorage.getItem(themeStorageKey):null;
      if(value==='light'||value==='dark'||value==='auto'){
        return value;
      }
    }catch(error){
      // ignore
    }

    return null;
  }

  function persistStoredTheme(theme){
    storedThemePreference=theme;
    if(!window.localStorage){return;}
    try{
      if(theme==='auto'||theme===null){
        window.localStorage.removeItem(themeStorageKey);
      }else{
        window.localStorage.setItem(themeStorageKey,theme);
      }
    }catch(error){
      // ignore
    }
  }

  function ensurePrompt(){
    if(promptOverlay){return;}
    promptOverlay=document.createElement('div');
    promptOverlay.style.position='fixed';
    promptOverlay.style.inset='0';
    promptOverlay.style.background='rgba(15,23,42,0.35)';
    promptOverlay.style.display='none';
    promptOverlay.style.alignItems='center';
    promptOverlay.style.justifyContent='center';
    promptOverlay.style.zIndex='9999';

    var dialog=document.createElement('div');
    dialog.style.background='#ffffff';
    dialog.style.borderRadius='8px';
    dialog.style.boxShadow='0 10px 40px rgba(15,23,42,0.25)';
    dialog.style.padding='20px';
    dialog.style.width='min(90vw,320px)';
    dialog.style.display='flex';
    dialog.style.flexDirection='column';
    dialog.style.gap='12px';
    dialog.style.fontFamily='inherit';

    promptLabel=document.createElement('div');
    promptLabel.style.fontWeight='600';
    promptLabel.style.fontSize='0.95rem';

    promptInput=document.createElement('input');
    promptInput.type='text';
    promptInput.style.padding='8px 10px';
    promptInput.style.border='1px solid rgba(148,163,184,0.6)';
    promptInput.style.borderRadius='6px';
    promptInput.style.fontSize='0.9rem';
    promptInput.style.fontFamily='inherit';

    var buttonRow=document.createElement('div');
    buttonRow.style.display='flex';
    buttonRow.style.justifyContent='flex-end';
    buttonRow.style.gap='8px';

    promptCancel=document.createElement('button');
    promptCancel.type='button';
    promptCancel.textContent='Cancel';
    promptCancel.style.padding='6px 12px';
    promptCancel.style.border='1px solid rgba(148,163,184,0.6)';
    promptCancel.style.background='#fff';
    promptCancel.style.borderRadius='6px';
    promptCancel.style.cursor='pointer';

    promptConfirm=document.createElement('button');
    promptConfirm.type='button';
    promptConfirm.textContent='Search';
    promptConfirm.style.padding='6px 12px';
    promptConfirm.style.border='none';
    promptConfirm.style.background='#2563eb';
    promptConfirm.style.color='#fff';
    promptConfirm.style.borderRadius='6px';
    promptConfirm.style.cursor='pointer';

    buttonRow.appendChild(promptCancel);
    buttonRow.appendChild(promptConfirm);

    dialog.appendChild(promptLabel);
    dialog.appendChild(promptInput);
    dialog.appendChild(buttonRow);
    promptOverlay.appendChild(dialog);
    document.body.appendChild(promptOverlay);

    promptOverlay.addEventListener('click',function(event){
      if(event.target===promptOverlay){
        closePrompt(null);
      }
    });

    promptCancel.addEventListener('click',function(){
      closePrompt(null);
    });

    promptConfirm.addEventListener('click',function(){
      closePrompt(promptInput.value);
    });

    promptInput.addEventListener('keydown',function(event){
      if(event.key==='Enter'){
        event.preventDefault();
        closePrompt(promptInput.value);
      }
      if(event.key==='Escape'){
        event.preventDefault();
        closePrompt(null);
      }
    });
  }

  function showPrompt(message,placeholder,defaultValue){
    ensurePrompt();
    promptLabel.textContent=message;
    promptInput.placeholder=placeholder||'';
    promptInput.value=defaultValue||'';
    promptOverlay.style.display='flex';
    setTimeout(function(){promptInput.focus();promptInput.select();},0);

    return new Promise(function(resolve){
      promptResolve=resolve;
    });
  }

  function closePrompt(value){
    if(!promptOverlay){return;}
    promptOverlay.style.display='none';
    if(typeof promptResolve==='function'){
      var resolver=promptResolve;
      promptResolve=null;
      resolver(value);
    }
  }

  function ensureToastContainer(){
    if(toastContainer){return;}
    toastContainer=document.createElement('div');
    toastContainer.style.position='fixed';
    toastContainer.style.bottom='16px';
    toastContainer.style.right='16px';
    toastContainer.style.display='flex';
    toastContainer.style.flexDirection='column';
    toastContainer.style.gap='8px';
    toastContainer.style.zIndex='10000';
    document.body.appendChild(toastContainer);
  }

  function showToast(message,type){
    ensureToastContainer();
    var toast=document.createElement('div');
    toast.textContent=message;
    toast.style.padding='10px 14px';
    toast.style.borderRadius='6px';
    toast.style.fontSize='0.85rem';
    toast.style.color='#f8fafc';
    toast.style.background=type==='error'?'#dc2626':'#2563eb';
    toast.style.boxShadow='0 8px 20px rgba(15,23,42,0.25)';
    toast.style.opacity='0';
    toast.style.transition='opacity 0.25s ease';
    toastContainer.appendChild(toast);
    requestAnimationFrame(function(){toast.style.opacity='1';});
    setTimeout(function(){
      toast.style.opacity='0';
      setTimeout(function(){
        if(toast.parentNode){toast.parentNode.removeChild(toast);}
      },250);
    },2500);
  }

  function renderNodeTable(node){
    var raw=node.getAttribute('data-json');
    if(!raw){
      showToast('Table view unavailable for this value.','error');
      return;
    }

    var dataset=buildTabularDataset(raw);
    if(!dataset){
      showToast('Not a compatible 2D array structure.','error');
      return;
    }

    var dump=node.closest('.pretty-dump');
    if(!dump){
      return;
    }

    var panel=ensureTablePanel(dump);
    var showMeta=dump._tableMetaEnabled===true;

    var label=node.querySelector('.node-summary-label, .node-label');
    var title=label&&label.textContent?label.textContent.trim():'Array';
    var expression=node.getAttribute('data-expression')||title;

    var existingCards=panel.querySelectorAll('.pretty-dump-table-card');
    var card=null;
    for(var i=0;i<existingCards.length;i+=1){
      if(existingCards[i].dataset.expression===expression){
        card=existingCards[i];
        break;
      }
    }

    if(!card){
      card=document.createElement('section');
      card.className='pretty-dump-table-card';
      card.dataset.expression=expression;

      var header=document.createElement('div');
      header.className='pretty-dump-table-header';

      var heading=document.createElement('div');
      heading.className='pretty-dump-table-heading';

      var titleEl=document.createElement('div');
      titleEl.className='pretty-dump-table-title';
      heading.appendChild(titleEl);

      var metaEl=null;
      if(showMeta){
        metaEl=document.createElement('div');
        metaEl.className='pretty-dump-table-meta';
        heading.appendChild(metaEl);
      }

      header.appendChild(heading);

      var closeButton=document.createElement('button');
      closeButton.type='button';
      closeButton.className='pretty-dump-table-close';
      closeButton.setAttribute('aria-label','Remove table view');
      closeButton.textContent='✕';
      closeButton.addEventListener('click',function(){
        if(card.parentNode){
          card.parentNode.removeChild(card);
        }
        if(dump._tablePanel && !dump._tablePanel.children.length){
          dump._tablePanel.remove();
          dump._tablePanel=null;
        }
      });
      header.appendChild(closeButton);

      var container=document.createElement('div');
      container.className='pretty-dump-table-container';

      card.appendChild(header);
      card.appendChild(container);
      panel.appendChild(card);

      card._headingEl=heading;
      card._titleEl=titleEl;
      card._metaEl=metaEl;
    } else {
      if(!card._headingEl){card._headingEl=card.querySelector('.pretty-dump-table-heading');}
      if(!card._titleEl){card._titleEl=card.querySelector('.pretty-dump-table-title');}

      if(showMeta){
        if(!card._metaEl){
          var metaHolder=card._headingEl||card.querySelector('.pretty-dump-table-heading');
          if(metaHolder){
            card._metaEl=document.createElement('div');
            card._metaEl.className='pretty-dump-table-meta';
            metaHolder.appendChild(card._metaEl);
          }
        }
      } else if(card._metaEl){
        if(card._metaEl.parentNode){
          card._metaEl.parentNode.removeChild(card._metaEl);
        }
        card._metaEl=null;
      }
    }

    if(card._titleEl){
      card._titleEl.textContent=title;
    }

    if(card._metaEl){
      card._metaEl.textContent=expression||'';
    }

    var containerNode=card.querySelector('.pretty-dump-table-container');
    if(!containerNode){
      containerNode=document.createElement('div');
      containerNode.className='pretty-dump-table-container';
      card.appendChild(containerNode);
    }
    containerNode.innerHTML='';

    var table=document.createElement('table');
    table.className='pretty-dump-table';

    var thead=document.createElement('thead');
    var headerRow=document.createElement('tr');
    for(var colIndex=0;colIndex<dataset.columns.length;colIndex+=1){
      var th=document.createElement('th');
      th.textContent=dataset.columns[colIndex];
      headerRow.appendChild(th);
    }
    thead.appendChild(headerRow);

    var tbody=document.createElement('tbody');
    for(var rowIndex=0;rowIndex<dataset.rows.length;rowIndex+=1){
      var tr=document.createElement('tr');
      var rowValues=dataset.rows[rowIndex];
      for(var cellIndex=0;cellIndex<rowValues.length;cellIndex+=1){
        var td=document.createElement('td');
        td.textContent=rowValues[cellIndex];
        tr.appendChild(td);
      }
      tbody.appendChild(tr);
    }

    table.appendChild(thead);
    table.appendChild(tbody);
    containerNode.appendChild(table);

    panel.appendChild(card);
    requestAnimationFrame(function(){
      card.scrollIntoView({behavior:'smooth',block:'nearest'});
    });
  }

  function ensureTablePanel(dump){
    if(dump._tablePanel && dump._tablePanel.parentNode){
      return dump._tablePanel;
    }

    var panel=document.createElement('div');
    panel.className='pretty-dump-table-panel';
    var currentTheme=dump.getAttribute('data-theme');
    var preference=dump.getAttribute('data-theme-preference');
    if(currentTheme){
      panel.setAttribute('data-theme',currentTheme);
    } else if(preference){
      panel.setAttribute('data-theme',preference);
    }
    dump.appendChild(panel);
    dump._tablePanel=panel;
    ensureThemeObserver(dump);
    return panel;
  }

  function ensureThemeObserver(dump){
    if(dump._themeObserver){return;}
    var observer=new MutationObserver(function(mutations){
      mutations.forEach(function(mutation){
        if(mutation.type==='attributes' && mutation.attributeName==='data-theme' && dump._tablePanel){
          var theme=dump.getAttribute('data-theme');
          if(theme){
            dump._tablePanel.setAttribute('data-theme',theme);
          } else {
            dump._tablePanel.removeAttribute('data-theme');
          }
        }
      });
    });
    observer.observe(dump,{attributes:true,attributeFilter:['data-theme']});
    dump._themeObserver=observer;
  }

  var rootThemeObserver=new MutationObserver(function(){
    var theme=document.documentElement.getAttribute('data-theme');
    if(!theme){return;}
    dumps.forEach(function(dump){
      var preference=dump.getAttribute('data-theme-preference')||'auto';
      if(preference!=='auto'){return;}
      dump.setAttribute('data-theme',theme);
      if(dump._tablePanel){
        dump._tablePanel.setAttribute('data-theme',theme);
      }
      updateThemeToggle(dump);
    });
  });

  rootThemeObserver.observe(document.documentElement,{attributes:true,attributeFilter:['data-theme']});

  // 监听系统主题变化
  if(window.matchMedia){
    var systemThemeMediaQuery=window.matchMedia('(prefers-color-scheme: dark)');
    var handleSystemThemeChange=function(event){
      var prefersDark=event.matches;
      var targetTheme=prefersDark?'dark':'light';

      dumps.forEach(function(dump){
        var preference=dump.getAttribute('data-theme-preference')||'auto';
        if(preference!=='auto'){return;}

        var rootTheme=document.documentElement.getAttribute('data-theme');
        if(rootTheme==='dark'||rootTheme==='light'){return;}

        dump.setAttribute('data-theme',targetTheme);
        if(dump._tablePanel){
          dump._tablePanel.setAttribute('data-theme',targetTheme);
        }
        updateThemeToggle(dump);
      });
    };

    // 使用 addEventListener 如果可用，否则使用 addListener
    if(systemThemeMediaQuery.addEventListener){
      systemThemeMediaQuery.addEventListener('change',handleSystemThemeChange);
    }else if(systemThemeMediaQuery.addListener){
      systemThemeMediaQuery.addListener(handleSystemThemeChange);
    }
  }

  function buildTabularDataset(raw){
    var parsed;
    try{
      parsed=JSON.parse(raw);
    }catch(error){
      return null;
    }

    var rows=normaliseRows(parsed);
    if(!rows||!rows.length){
      return null;
    }

    var columnOrder=[];
    var columnSet={};
    var normalisedRows=[];

    for(var i=0;i<rows.length;i+=1){
      var record=normaliseRow(rows[i]);
      if(!record){
        return null;
      }
      normalisedRows.push(record);

      for(var key in record){
        if(Object.prototype.hasOwnProperty.call(record,key) && !columnSet[key]){
          columnSet[key]=true;
          columnOrder.push(key);
        }
      }
    }

    if(!columnOrder.length){
      return null;
    }

    var tableRows=[];
    for(var r=0;r<normalisedRows.length;r+=1){
      var recordRow=normalisedRows[r];
      var rowValues=[];
      for(var c=0;c<columnOrder.length;c+=1){
        var column=columnOrder[c];
        rowValues.push(formatCellValue(recordRow[column]));
      }
      tableRows.push(rowValues);
    }

    return {columns:columnOrder,rows:tableRows};
  }

  function normaliseRows(value){
    if(Array.isArray(value)){
      return value;
    }

    if(value && typeof value==='object'){
      if(Array.isArray(value.__items__)){
        return value.__items__;
      }

      var result=[];
      for(var key in value){
        if(!Object.prototype.hasOwnProperty.call(value,key)){
          continue;
        }
        if(typeof key==='string' && key.indexOf('__')===0){
          continue;
        }
        result.push(value[key]);
      }
      return result;
    }

    return null;
  }

  function normaliseRow(row){
    var workingRow=row;

    if(workingRow && typeof workingRow==='object' && !Array.isArray(workingRow) && Array.isArray(workingRow.__items__)){
      workingRow=workingRow.__items__;
    }

    var record={};

    if(Array.isArray(workingRow)){
      for(var index=0;index<workingRow.length;index+=1){
        record[String(index)]=workingRow[index];
      }
    } else if(workingRow && typeof workingRow==='object'){
      if(workingRow.properties && typeof workingRow.properties==='object' && !Array.isArray(workingRow.properties)){
        for(var key in workingRow.properties){
          if(!Object.prototype.hasOwnProperty.call(workingRow.properties,key)){
            continue;
          }
          if(key.indexOf('__')===0){
            continue;
          }
          record[key]=workingRow.properties[key];
        }
      }

      for(var ownKey in workingRow){
        if(!Object.prototype.hasOwnProperty.call(workingRow,ownKey)){
          continue;
        }
        if(ownKey==='properties'||ownKey.indexOf('__')===0){
          continue;
        }
        record[ownKey]=workingRow[ownKey];
      }
    } else {
      return null;
    }

    return Object.keys(record).length?record:null;
  }

  function formatCellValue(value){
    if(typeof value==='undefined'){
      return '';
    }
    if(value===null){
      return 'null';
    }
    if(typeof value==='boolean'){
      return value?'true':'false';
    }
    if(typeof value==='object'){
      try{
        return JSON.stringify(value);
      }catch(error){
        return Object.prototype.toString.call(value);
      }
    }
    return String(value);
  }

  function clearSearchHighlights(root){
    root.querySelectorAll('.search-highlight').forEach(function(element){
      var parent=element.parentNode;
      var text=element.textContent||'';
      element.replaceWith(text);
      if(parent && typeof parent.normalize==='function'){
        parent.normalize();
      }
    });
  }

  function highlightMatches(container,term){
    if(!container||!term){
      return;
    }

    var search=term.toLowerCase();
    var showTextFilter=typeof NodeFilter!=='undefined'?NodeFilter.SHOW_TEXT:4;
    var walker=document.createTreeWalker(container,showTextFilter,null);
    var targets=[];

    while(walker.nextNode()){
      var node=walker.currentNode;
      if(!node||!node.nodeValue){
        continue;
      }
      if(node.nodeValue.toLowerCase().includes(search)){
        targets.push(node);
      }
    }

    targets.forEach(function(textNode){
      wrapMatches(textNode,term,search);
    });
  }

  function wrapMatches(textNode,term,search){
    var original=textNode.nodeValue||'';
    var lower=original.toLowerCase();
    var lastIndex=0;
    var index=lower.indexOf(search);

    if(index===-1){
      return;
    }

    var fragment=document.createDocumentFragment();

    while(index!==-1){
      if(index>lastIndex){
        fragment.append(original.slice(lastIndex,index));
      }

      var matchText=original.slice(index,index+term.length);
      var span=document.createElement('span');
      span.className='search-highlight';
      span.textContent=matchText;
      fragment.append(span);

      lastIndex=index+term.length;
      index=lower.indexOf(search,lastIndex);
    }

    if(lastIndex<original.length){
      fragment.append(original.slice(lastIndex));
    }

    textNode.replaceWith(fragment);
  }

  function normalizeExpressionSearchTerm(term){
    return term
      .replace(/^[\[\]\s'"`]+/,'')
      .replace(/[\[\]\s'"`]+$/,'')
      .replace(/^->/,'')
      .replace(/^\./,'');
  }

  function expressionMatchesNode(expression,search){
    if(!expression||!search){
      return false;
    }

    var trimmedExpression=expression.trim();
    if(trimmedExpression===''){
      return false;
    }

    var searchToken=normalizeExpressionSearchTerm(search);
    var segments=[];

    var bracketMatch=trimmedExpression.match(/\[['"]?([^'"\]]+)['"]?\]\s*$/);
    if(bracketMatch&&bracketMatch[1]){
      segments.push(bracketMatch[1]);
    }

    var arrowMatch=trimmedExpression.match(/->\s*([a-zA-Z_][\w]*)\s*$/);
    if(arrowMatch&&arrowMatch[1]){
      segments.push(arrowMatch[1]);
    }

    var dotMatch=trimmedExpression.match(/\.([a-zA-Z_][\w]*)\s*$/);
    if(dotMatch&&dotMatch[1]){
      segments.push(dotMatch[1]);
    }

    if(!segments.length){
      var fallback=trimmedExpression.replace(/^\$+/,'');
      if(fallback!==''){
        segments.push(fallback);
      }
    }

    return segments.some(function(segment){
      var lower=segment.toLowerCase();
      if(lower.includes(search)){
        return true;
      }

      if(searchToken!==''&&lower.includes(searchToken)){
        return true;
      }

      return false;
    });
  }

  function performSearch(root,scopeNode,term){
    clearSearchHighlights(root);
    root.querySelectorAll('[data-node-type].search-result-target').forEach(function(el){
      el.classList.remove('search-result-target');
    });
    root.querySelectorAll('[data-node-type].search-result-context').forEach(function(el){
      el.classList.remove('search-result-context');
    });

    if(typeof term!=='string'){
      return;
    }

    var trimmed=term.trim();
    if(trimmed===''){
      return;
    }

    var search=trimmed.toLowerCase();
    var candidates=[scopeNode].concat(Array.from(scopeNode.querySelectorAll('[data-node-type]')));
    var firstHit=null;

    candidates.forEach(function(candidate){
      var label=candidate.querySelector('.node-summary-label')||candidate.querySelector('.node-label');
      var labelText=label&&label.textContent?label.textContent.toLowerCase():'';
      var expressionAttr=candidate.getAttribute('data-expression')||'';
      var json=(candidate.getAttribute('data-json')||'').toLowerCase();

      if(labelText===''&&expressionAttr===''&&json===''){
        return;
      }

      var labelMatch=labelText.includes(search);
      var expressionMatch=expressionMatchesNode(expressionAttr,search);
      var jsonMatch=json.includes(search);
      var isBranch=candidate.matches('details[data-node-type], [data-node-type].node-branch');
      var isDirectMatch=labelMatch||expressionMatch||(jsonMatch&&!isBranch);

      if(!isDirectMatch&&!jsonMatch){
        return;
      }

      if(candidate.tagName==='DETAILS'){
        candidate.open=true;
      }

      if(isDirectMatch){
        candidate.classList.add('search-result-target');

        var highlightTargets=new Set();
        var highlightSelectors=[
          '.node-summary-label',
          '.node-label',
          '.node-value',
          '.node-key',
          '.node-type-label',
          '.node-expression'
        ];

        var highlightContainers=[];
        if(candidate.tagName==='DETAILS'){
          var summary=candidate.querySelector('summary');
          if(summary){
            highlightContainers.push(summary);
          }
        } else {
          highlightContainers.push(candidate);
        }

        highlightSelectors.forEach(function(selector){
          highlightContainers.forEach(function(container){
            container.querySelectorAll(selector).forEach(function(element){
              var text=element.textContent?element.textContent.toLowerCase():'';
              if(text.includes(search)){
                highlightTargets.add(element);
              }
            });
          });
        });

        if(highlightTargets.size===0 && label && labelMatch){
          highlightTargets.add(label);
        }

        highlightTargets.forEach(function(element){
          highlightMatches(element,trimmed);
        });
      } else {
        candidate.classList.add('search-result-context');
      }

      var parent=candidate.parentElement;
      while(parent){
        if(parent.tagName==='DETAILS'){
          parent.open=true;
        }
        if(parent instanceof HTMLElement && parent.hasAttribute('data-node-type') && !parent.classList.contains('search-result-target')){
          parent.classList.add('search-result-context');
        }
        parent=parent.parentElement;
      }

      if(isDirectMatch&&!firstHit){
        firstHit=candidate;
      }
    });

    if(firstHit){
      firstHit.scrollIntoView({behavior:'smooth',block:'center'});
      showToast('Search results highlighted');
    } else {
      showToast('No matches found for "'+trimmed+'"','error');
    }
  }

  function copyNodeJson(node){
    var raw=node.getAttribute('data-json');
    if(!raw){
      showToast('This value cannot be copied as JSON.','error');
      return;
    }

    var payload=raw;
    try{
      var parsed=JSON.parse(raw);
      payload=JSON.stringify(parsed,null,2);
    }catch(error){/* ignore */}

    if(navigator.clipboard && typeof navigator.clipboard.writeText==='function'){
      navigator.clipboard.writeText(payload)
        .then(function(){showToast('Copied JSON to clipboard.','success');})
        .catch(function(){fallbackCopy(payload);});
      return;
    }

    fallbackCopy(payload);
  }

  function fallbackCopy(text){
    var textarea=document.createElement('textarea');
    textarea.value=text;
    textarea.setAttribute('readonly','');
    textarea.style.position='absolute';
    textarea.style.left='-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    var copied=false;
    try{
      copied=document.execCommand('copy');
    }catch(error){
      copied=false;
    }
    document.body.removeChild(textarea);
    if(copied){
      showToast('Copied JSON to clipboard.','success');
    } else {
      showToast('Unable to copy value.','error');
    }
  }
})();</script>
SCRIPT;
    }
}
