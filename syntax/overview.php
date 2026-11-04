<?php

/**
 * DokuWiki Plugin admidioplugins (Syntax Component: plugin overview)
 *
 *   {{admidioplugins>overview}}
 *   {{admidioplugins>sidebar}}
 *
 * A searchable overview of every plugin, grouped into three tables by the Admidio version its
 * newest release requires: Admidio 6, Admidio 5, and Admidio 4 or older (see
 * helper_plugin_admidioplugins::getPluginsByAdmidioVersion() for exactly how a plugin is
 * classified, and which plugins are left out). A plain text box above the tables filters their
 * rows as the visitor types - a small jQuery handler in script.js, not a new library, because the
 * plugin list is short enough that a live substring filter is all it needs.
 *
 * "sidebar" renders the same grouping as plain headed link lists (name only, no filter box or
 * descriptions), for the wiki's sidebar page.
 *
 * @license GPL-2.0-or-later
 */

use dokuwiki\Extension\SyntaxPlugin;

class syntax_plugin_admidioplugins_overview extends SyntaxPlugin
{
    /** @inheritDoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritDoc */
    public function getPType()
    {
        return 'block';
    }

    /** @inheritDoc */
    public function getSort()
    {
        return 150;
    }

    /** @inheritDoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('\{\{admidioplugins>(?:overview|sidebar)\}\}', $mode, 'plugin_admidioplugins_overview');
    }

    /** @inheritDoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        return ['mode' => str_contains($match, '>sidebar') ? 'sidebar' : 'overview'];
    }

    /** @inheritDoc */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        global $ID;

        if ($format === 'metadata') {
            // The overview reads every plugin's data and releases, so its cache has to depend on
            // any of them changing - the same blanket dependency the release list uses.
            /** @var Doku_Renderer_metadata $renderer */
            $renderer->meta['plugin']['admidioplugins']['releases'] = true;
            return true;
        }
        if ($format !== 'xhtml') {
            return false;
        }

        /** @var helper_plugin_admidioplugins $helper */
        $helper = plugin_load('helper', 'admidioplugins');
        if (!$helper->isReady()) {
            $renderer->doc .= '<div class="error">' . hsc($this->getLang('err_not_ready')) . '</div>';
            return true;
        }

        $language = $helper->displayLanguage((string)$ID);
        $renderer->doc .= ($data['mode'] ?? '') === 'sidebar'
            ? $this->renderSidebar($helper, $language)
            : $this->renderOverview($helper, $language);
        return true;
    }

    private function renderSidebar(helper_plugin_admidioplugins $helper, string $language): string
    {
        $groups = $helper->getPluginsByAdmidioVersion();

        $html = '<div class="admidioplugins-sidebar">';
        foreach (['admidio6' => 'sidebar_admidio6', 'admidio5' => 'sidebar_admidio5', 'admidio4' => 'sidebar_admidio4'] as $group => $headingKey) {
            if ($groups[$group] === []) {
                continue;
            }

            $html .= '<h5>' . hsc($helper->langFor($language, $headingKey)) . '</h5><ul>';
            foreach ($groups[$group] as $entry) {
                $name = trim((string)($entry['data']['name'] ?? '')) ?: (string)($entry['data']['plugin_id'] ?? $entry['pid']);
                $html .= '<li class="level1"><div class="li">'
                    . html_wikilink(':' . $this->pageInLanguage($entry['pid'], $language), $name)
                    . '</div></li>';
            }
            $html .= '</ul>';
        }

        return $html . '</div>';
    }

    private function renderOverview(helper_plugin_admidioplugins $helper, string $language): string
    {
        $label = fn(string $key): string => $helper->langFor($language, $key);
        $groups = $helper->getPluginsByAdmidioVersion();

        $html = '<div class="admidioplugins-overview">';
        $html .= '<input type="text" class="admidioplugins-overview-filter" autocomplete="off"'
            . ' placeholder="' . hsc($label('overview_filter')) . '" aria-label="' . hsc($label('overview_filter')) . '" />';

        foreach (['admidio6' => 'overview_admidio6', 'admidio5' => 'overview_admidio5', 'admidio4' => 'overview_admidio4'] as $group => $headingKey) {
            if ($groups[$group] === []) {
                continue;
            }

            $html .= '<h3>' . hsc($label($headingKey)) . '</h3>';
            $html .= '<table class="inline admidioplugins-overview-table">';
            foreach ($groups[$group] as $entry) {
                $html .= $this->renderRow($helper, $entry['pid'], $entry['data'], $language);
            }
            $html .= '</table>';
        }

        return $html . '</div>';
    }

    /**
     * The plugin's page in the language being shown: plugin data lives on the canonical-language
     * page (en:plugins:x), so on a German overview the link has to go to de:plugins:x when that
     * page exists, and stay on the canonical one otherwise.
     */
    private function pageInLanguage(string $pid, string $language): string
    {
        if (preg_match('/^[a-z]{2}(?:-[a-z]+)?:(.+)$/', $pid, $match)) {
            $translated = $language . ':' . $match[1];
            if ($translated !== $pid && page_exists($translated) && auth_quickaclcheck($translated) >= AUTH_READ) {
                return $translated;
            }
        }

        return $pid;
    }

    private function renderRow(helper_plugin_admidioplugins $helper, string $pid, array $data, string $language): string
    {
        $name = trim((string)($data['name'] ?? '')) ?: (string)($data['plugin_id'] ?? $pid);
        $description = $helper->localized($data, 'description', $language);
        // The filter box matches against this row's own text, so the name and description it
        // searches have to be in it somewhere - html_wikilink() already escapes the name.
        $html = '<tr><td>' . html_wikilink(':' . $this->pageInLanguage($pid, $language), $name) . '</td>';
        $html .= '<td>' . hsc($description) . '</td></tr>';

        return $html;
    }
}
