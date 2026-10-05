<?php

/**
 * DokuWiki Plugin admidioplugins (Syntax Component: release list)
 *
 *   {{admidioplugins>releases}}
 *   {{admidioplugins>releases en:plugins:someplugin}}
 *
 * Lists the releases of the plugin described on this page - or, on a page without plugin data of
 * its own (de:plugins:x), those of its counterpart in the canonical language (en:plugins:x), or
 * those of an explicitly named page. Releases are always stored on the page holding the data.
 *
 * The list is newest first: version, status, comment and date in the head line, the requirements
 * and the notes below it, each in the language of the page's namespace. Users who may edit the
 * page holding the data additionally get an "Add release" button and edit/delete controls (shown
 * by script.js; the release form is rendered hidden, because the page is cached for all users).
 *
 * @license GPL-2.0-or-later
 */

use dokuwiki\Extension\SyntaxPlugin;

class syntax_plugin_admidioplugins_releases extends SyntaxPlugin
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
        $this->Lexer->addSpecialPattern('\{\{admidioplugins>releases(?:\s+[^}]*)?\}\}', $mode, 'plugin_admidioplugins_releases');
    }

    /** @inheritDoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        return ['page' => trim(substr($match, strlen('{{admidioplugins>releases'), -2))];
    }

    /** @inheritDoc */
    public function render($format, Doku_Renderer $renderer, $data)
    {
        global $ID;

        if ($format !== 'metadata' && $format !== 'xhtml') {
            return false;
        }

        /** @var helper_plugin_admidioplugins $helper */
        $helper = plugin_load('helper', 'admidioplugins');
        if (!$helper->isReady()) {
            if ($format === 'xhtml') {
                $renderer->doc .= '<div class="error">' . hsc($this->getLang('err_not_ready')) . '</div>';
            }
            return true;
        }

        $source = $helper->sourcePage((string)$ID, (string)($data['page'] ?? ''));

        if ($format === 'metadata') {
            // For the page cache (struct data) and for JSINFO (on which pages the user may edit).
            /** @var Doku_Renderer_metadata $renderer */
            $renderer->meta['plugin']['admidioplugins']['releases'] = true;
            $renderer->meta['plugin']['admidioplugins']['sources'][$source] = true;
            return true;
        }

        $renderer->doc .= $this->renderReleases($helper, $source, (string)$ID);
        return true;
    }

    private function renderReleases(helper_plugin_admidioplugins $helper, string $pid, string $current): string
    {
        $language = $helper->displayLanguage($current);
        $releases = $helper->getReleases($pid);

        $html = '<div class="admidioplugins-releases" data-pid="' . hsc($pid) . '">';

        if ($releases === []) {
            $html .= '<p class="admidioplugins-empty">' . hsc($helper->langFor($language, 'releases_none')) . '</p>';
        } else {
            $html .= '<ul class="admidioplugins-release-list">';
            foreach ($releases as $release) {
                $html .= $this->renderRelease($helper, $release, $language);
            }
            $html .= '</ul>';
        }

        $html .= '<div class="admidioplugins-controls" hidden>'
            . '<button type="button" class="button admidioplugins-add">'
            . hsc($helper->langFor($language, 'btn_add')) . '</button>'
            . '</div>';
        $html .= $this->renderForm($helper, $language);

        return $html . '</div>';
    }

    private function renderRelease(helper_plugin_admidioplugins $helper, array $release, string $language): string
    {
        $label = fn(string $key): string => $helper->langFor($language, $key);

        $status = helper_plugin_admidioplugins::normalizeStatus((string)$release['release_status']);
        $version = (string)$release['version'];
        $url = $status === 'withdrawn' ? null : $helper->publicDownloadUrl((string)$release['download']);

        // Everything the edit form needs, so that it can be prefilled without another request.
        $editData = [
            'rid' => $release['rid'],
            'version' => $version,
            'download' => (string)$release['download'],
            'release_status' => $status,
            'release_date' => (string)$release['release_date'],
            'requires_admidio' => (string)$release['requires_admidio'],
            'requires_php' => (string)$release['requires_php'],
            'hide_requires' => helper_plugin_admidioplugins::isFlagSet($release, 'hide_requires') ? 1 : 0,
            'comment' => (string)$release['comment'],
            'comment_de' => (string)$release['comment_de'],
            'notes' => (string)$release['notes'],
            'notes_de' => (string)$release['notes_de'],
        ];

        $html = '<li class="admidioplugins-release status-' . hsc($status) . '"'
            . ' data-release="' . hsc(json_encode($editData)) . '">';

        $html .= '<div class="admidioplugins-release-head">';
        $versionLabel = sprintf($label('release_label'), $version);
        $html .= $url !== null
            ? '<a class="admidioplugins-download" href="' . hsc($url) . '">' . hsc($versionLabel) . '</a>'
            : '<span class="admidioplugins-version">' . hsc($versionLabel) . '</span>';
        if ($status !== 'stable') {
            $html .= ' <span class="admidioplugins-badge badge-' . hsc($status) . '">'
                . hsc($label('status_' . $status) ?: $status) . '</span>';
        }
        $comment = $helper->renderText($helper->localized($release, 'comment', $language));
        if ($comment !== '') {
            $html .= ' <span class="admidioplugins-comment">' . $comment . '</span>';
        }
        $date = trim((string)$release['release_date']);
        if ($date !== '') {
            $html .= ' <span class="admidioplugins-date">(' . hsc(str_replace('/', '-', $date)) . ')</span>';
        }
        $html .= '<span class="admidioplugins-release-actions" hidden>'
            . '<button type="button" class="admidioplugins-edit">' . hsc($label('btn_edit')) . '</button>'
            . '<button type="button" class="admidioplugins-delete">' . hsc($label('btn_delete')) . '</button>'
            . '</span>';
        $html .= '</div>';

        // The catalogue always states the requirements; on the page they can be left out, because
        // they are usually only worth reading when a release needs a newer Admidio than the last.
        $requires = helper_plugin_admidioplugins::isFlagSet($release, 'hide_requires')
            ? '' : $helper->formatRequires($release);
        if ($requires !== '') {
            $html .= '<div class="admidioplugins-requires">' . hsc($label('release_requires')) . ' ' . $requires . '</div>';
        }

        $notes = $helper->renderText($helper->localized($release, 'notes', $language), false);
        if ($notes !== '') {
            $html .= '<div class="admidioplugins-notes">' . $notes . '</div>';
        }

        $sha256 = trim((string)$release['sha256']);
        if ($sha256 !== '') {
            $html .= '<div class="admidioplugins-checksum" title="SHA-256">'
                . '<code>' . hsc($sha256) . '</code>'
                . (ctype_digit(trim((string)$release['size'])) ? ' · ' . hsc(filesize_h((int)$release['size'])) : '')
                . '</div>';
        }

        return $html . '</li>';
    }

    /**
     * The release form, hidden. script.js shows it for adding or editing.
     */
    private function renderForm(helper_plugin_admidioplugins $helper, string $language): string
    {
        $label = fn(string $key): string => $helper->langFor($language, $key);

        $statusOptions = '';
        foreach (helper_plugin_admidioplugins::RELEASE_STATUSES as $status) {
            $statusOptions .= '<option value="' . $status . '">' . hsc($label('status_' . $status)) . '</option>';
        }

        $field = function (string $name, string $input, string $hint = '', string $class = '') use ($label): string {
            return '<label class="admidioplugins-field ' . $class . '">'
                . '<span class="label">' . hsc($label('field_' . $name)) . '</span>'
                . $input
                . ($hint !== '' ? '<span class="hint">' . hsc($hint) . '</span>' : '')
                . '</label>';
        };

        return '<form class="admidioplugins-form" hidden>'
            . '<fieldset>'
            . '<legend></legend>'
            . $field(
                'download',
                '<input type="text" name="download" autocomplete="off" />',
                $label('hint_download')
            )
            . '<div><button type="button" class="button admidioplugins-inspect">'
            . hsc($label('btn_inspect')) . '</button> '
            . '<button type="button" class="button admidioplugins-cancel only-add">' . hsc($label('btn_cancel')) . '</button></div>'
            . '<div class="admidioplugins-derived" hidden></div>'
            . '<div class="admidioplugins-details" hidden>'
            . $field('release_status', '<select name="release_status">' . $statusOptions . '</select>')
            . $field('release_date', '<input type="date" name="release_date" />')
            . $field('comment', '<input type="text" name="comment" />', $label('hint_comment'))
            . $field('comment_de', '<input type="text" name="comment_de" />')
            . $field('notes', '<textarea name="notes" rows="3"></textarea>', $label('hint_notes'))
            . $field('notes_de', '<textarea name="notes_de" rows="3"></textarea>')
            . $field('requires_admidio', '<input type="text" name="requires_admidio" />', $label('hint_requires'), 'only-edit')
            . $field('requires_php', '<input type="text" name="requires_php" />', '', 'only-edit')
            . '<label class="admidioplugins-field admidioplugins-checkbox">'
            . '<input type="checkbox" name="hide_requires" value="1" /> '
            . '<span class="label-inline">' . hsc($label('field_hide_requires')) . '</span>'
            . '<span class="hint">' . hsc($label('hint_hide_requires')) . '</span>'
            . '</label>'
            . '<div class="admidioplugins-form-buttons">'
            . '<button type="submit" class="button admidioplugins-save">' . hsc($label('btn_save')) . '</button> '
            . '<button type="button" class="button admidioplugins-cancel">' . hsc($label('btn_cancel')) . '</button>'
            . '</div>'
            . '</div>'
            . '<div class="admidioplugins-message" role="status"></div>'
            . '</fieldset>'
            . '</form>';
    }
}
