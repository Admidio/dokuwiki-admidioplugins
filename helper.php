<?php

/**
 * DokuWiki Plugin admidioplugins (Helper Component)
 *
 * Everything the other components share: reading the two struct schemas, the version rules of
 * Admidio's plugin manager, inspecting a plugin archive and writing release rows.
 *
 * @license GPL-2.0-or-later
 */

use dokuwiki\Extension\Plugin;
use dokuwiki\Logger;
use dokuwiki\plugin\admidioplugins\SafeHttpClient;
use dokuwiki\plugin\struct\meta\AccessTable;
use dokuwiki\plugin\struct\meta\ConfigParser;
use dokuwiki\plugin\struct\meta\Schema;
use dokuwiki\plugin\struct\meta\SearchConfig;
use splitbrain\PHPArchive\Zip;

class helper_plugin_admidioplugins extends Plugin
{
    public const SCHEMA_PLUGIN = 'admidio_plugin';
    public const SCHEMA_RELEASE = 'admidio_plugin_release';

    /** Release statuses, from most to least stable. */
    public const RELEASE_STATUSES = ['stable', 'rc', 'beta', 'alpha', 'withdrawn'];

    /** Which release statuses a channel admits. Channels are cumulative. */
    public const CHANNELS = [
        'stable' => ['stable'],
        'rc' => ['stable', 'rc'],
        'beta' => ['stable', 'rc', 'beta'],
        'alpha' => ['stable', 'rc', 'beta', 'alpha'],
    ];

    /** Plugin statuses whose plugins are never part of the catalogue. */
    public const HIDDEN_PLUGIN_STATUSES = ['legacy', 'archived'];

    /** Same limits as Admidio's PluginPackage, so a release we accept is one Admidio accepts. */
    public const MAX_ENTRIES = 5000;
    public const MAX_EXTRACTED_BYTES = 134217728;
    public const MANIFEST_FILE = 'plugin.json';
    public const ENTRY_FILE = 'plugin.php';

    /**
     * Archive entries Admidio's installer skips (PluginPackage::SKIPPED): a path with one of these
     * names as any segment does not count for the layout - in particular the __MACOSX folder the
     * ZIP tool of macOS puts next to the plugin folder.
     */
    public const SKIPPED = ['__MACOSX', '.git', '.github', '.svn', '.hg',
        'tests', 'phpunit.xml', 'phpunit.xml.dist', '.phpunit.result.cache',
        '.DS_Store', 'Thumbs.db', '.gitignore', '.gitattributes'];

    /** The first Admidio version with the plugin manager. A legacy release must not accept it. */
    public const PLUGIN_MANAGER_VERSION = '6.0.0';

    /**
     * Native display name per language code, for the "Supported languages" fact of the info box.
     * The catalogue itself keeps the raw code (languages/*.xml naming) - this is display only.
     * Taken from Admidio's own languages/languages.php, the native half of its "English - Native"
     * name. A code this does not know is shown as entered rather than hidden.
     */
    public const LANGUAGE_NAMES = [
        'bg' => 'Български',
        'cs' => 'Český',
        'da' => 'Dansk',
        'de' => 'Deutsch (du)',
        'de-DE' => 'Deutsch (Sie)',
        'el' => 'Ελληνικά',
        'en' => 'English',
        'es' => 'Español',
        'et' => 'Eesti keel',
        'fi' => 'Suomalainen',
        'fr' => 'Français',
        'hu' => 'Magyar',
        'id' => 'bahasa Indonesia',
        'it' => 'Italiano',
        'nb' => 'Norsk Bokmål',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'pt' => 'Português',
        'pt-BR' => 'Português (Brasil)',
        'ro' => 'Română',
        'ru' => 'Русский',
        'sv' => 'Svenska',
        'ta' => 'தமிழ்',
        'th' => 'แบบไทย',
        'uk' => 'український',
        'zh' => '中文',
    ];

    // ------------------------------------------------------------------------------------------
    // Rules shared with Admidio
    // ------------------------------------------------------------------------------------------

    /**
     * The plugin ID rule of Admidio (Plugin::isValidId): lowercase letters and digits, with single
     * hyphens or underscores as separators. It is the plugin's directory name.
     */
    public static function isValidId(string $id): bool
    {
        return (bool)preg_match('/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/', $id);
    }

    /**
     * A version as Admidio's constraint matcher can read it.
     */
    public static function isValidVersion(string $version): bool
    {
        return (bool)preg_match('/^\d[\w.\-+]*$/', $version);
    }

    /**
     * Admidio's constraint matcher (Plugin::versionMatches), unchanged in behaviour.
     *
     * A constraint is a list of terms separated by blanks or commas; all of them must hold. A term
     * is an optional operator (>=, <=, !=, >, <, =) and a version; without an operator it means >=.
     * An empty constraint or "*" matches everything. An unreadable term never matches.
     */
    public static function versionMatches(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        foreach (preg_split('/[\s,]+/', $constraint) as $term) {
            if ($term === '') {
                continue;
            }
            if (preg_match('/^(>=|<=|!=|>|<|=)?\s*(\d[\w.\-]*)$/', $term, $matches) !== 1) {
                return false;
            }
            $operator = ($matches[1] ?? '') === '' ? '>=' : $matches[1];
            if (!version_compare($version, $matches[2], $operator)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether every term of a constraint can be read at all.
     */
    public static function isReadableConstraint(string $constraint): bool
    {
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*') {
            return true;
        }
        foreach (preg_split('/[\s,]+/', $constraint) as $term) {
            if ($term !== '' && !preg_match('/^(>=|<=|!=|>|<|=)?\s*(\d[\w.\-]*)$/', $term)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The status of a release, with the defaults the catalogue format defines.
     */
    public static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if ($status === '' || $status === 'final') {
            return 'stable';
        }
        return $status;
    }

    // ------------------------------------------------------------------------------------------
    // Reading struct data
    // ------------------------------------------------------------------------------------------

    /**
     * Whether struct and both schemas are there.
     */
    public function isReady(): bool
    {
        if (!plugin_load('helper', 'struct')) {
            return false;
        }
        return (new Schema(self::SCHEMA_PLUGIN))->getId() && (new Schema(self::SCHEMA_RELEASE))->getId();
    }

    /**
     * The struct database file, for cache dependencies. Null while struct has no database.
     */
    public function getStructDbFile(): ?string
    {
        /** @var helper_plugin_struct_db $db */
        $db = plugin_load('helper', 'struct_db');
        if (!$db) {
            return null;
        }
        $sqlite = $db->getDB(false);
        return $sqlite ? $sqlite->getDbFile() : null;
    }

    /**
     * Page data of one plugin page: column label => raw value. Empty when the page has none.
     *
     * @return array<string,mixed>
     */
    public function getPluginData(string $pid): array
    {
        $access = AccessTable::getPageAccess(self::SCHEMA_PLUGIN, $pid);
        return $access->getDataArray();
    }

    /**
     * Page data of every plugin page, keyed by page ID.
     *
     * Only real page data (row ID 0) counts. Struct's row editor can be tricked into writing
     * serial rows into a page schema; such rows are ignored here.
     *
     * @return array<string,array<string,mixed>>
     */
    public function getAllPlugins(): array
    {
        $plugins = [];
        foreach ($this->search(self::SCHEMA_PLUGIN) as $row) {
            if ((int)$row['%rowid%'] !== 0) {
                continue;
            }
            $plugins[$row['%pageid%']] = $row;
        }
        return $plugins;
    }

    /**
     * The plugins that may be listed publicly, keyed by page ID: a valid plugin_id, a status that
     * is not legacy/archived, and a page every anonymous visitor may read. When a plugin_id is
     * claimed by more than one page, only the one created first is kept - a second page cannot
     * "take over" somebody else's plugin ID this way.
     *
     * Shared by the JSON catalogue (action/export.php) and the plugin overview (syntax/overview.php),
     * so what one lists and what the other lists can never drift apart.
     *
     * @param string[] $onlyIds Plugin IDs to limit the result to; empty means every plugin.
     * @return array<string,array<string,mixed>> page ID => plugin data, including 'plugin_id'.
     */
    public function getPublicPlugins(array $onlyIds = []): array
    {
        $candidates = [];
        foreach ($this->getAllPlugins() as $pid => $data) {
            $id = trim((string)($data['plugin_id'] ?? ''));
            if (!self::isValidId($id)) {
                continue;
            }
            if ($onlyIds !== [] && !in_array($id, $onlyIds, true)) {
                continue;
            }
            $status = strtolower(trim((string)($data['plugin_status'] ?? '')));
            if (in_array($status, self::HIDDEN_PLUGIN_STATUSES, true)) {
                continue;
            }
            // Public means every anonymous visitor may read the page, whoever happens to request
            // this (and so fill the cache, or render the overview).
            if (auth_aclcheck($pid, '', []) < AUTH_READ) {
                continue;
            }
            $created = (int)p_get_metadata($pid, 'date created', METADATA_DONT_RENDER);
            $candidates[$id][] = ['pid' => $pid, 'created' => $created ?: PHP_INT_MAX, 'data' => $data];
        }

        $plugins = [];
        foreach ($candidates as $id => $claims) {
            usort($claims, static fn(array $a, array $b): int => [$a['created'], $a['pid']] <=> [$b['created'], $b['pid']]);
            if (count($claims) > 1) {
                Logger::debug("admidioplugins: plugin id '$id' is claimed by several pages; using {$claims[0]['pid']}");
            }
            $plugins[$claims[0]['pid']] = $claims[0]['data'];
        }

        return $plugins;
    }

    /**
     * Every public plugin grouped by the Admidio version its newest release requires, for the
     * overview page: 'admidio6', 'admidio5', 'admidio4' (meaning "4 or older", or no readable
     * constraint at all). A plugin with no usable release is left out - there is nothing to
     * classify it by, and the catalogue would not offer it either.
     *
     * Classification reads only the newest release (getReleases() already sorts newest first),
     * not necessarily a stable one: an actively maintained plugin with an old 4.x release and a
     * current 5.x+ one is listed under 5, not 4, because that is what a visitor on current Admidio
     * needs to know. A release that states no requires_admidio at all matches every version, by
     * the same rule PluginStore::pickRelease() uses in Admidio itself - it is listed under 6.
     *
     * @return array{admidio6: array<int,array{pid:string,data:array<string,mixed>}>, admidio5: array<int,array{pid:string,data:array<string,mixed>}>, admidio4: array<int,array{pid:string,data:array<string,mixed>}>}
     */
    public function getPluginsByAdmidioVersion(): array
    {
        $groups = ['admidio6' => [], 'admidio5' => [], 'admidio4' => []];

        foreach ($this->getPublicPlugins() as $pid => $data) {
            $releases = $this->getReleases($pid);
            if ($releases === []) {
                continue;
            }

            $constraint = (string)($releases[0]['requires_admidio'] ?? '');
            if (self::versionMatches('6.0.0', $constraint)) {
                $group = 'admidio6';
            } elseif (self::versionMatches('5.0.0', $constraint)) {
                $group = 'admidio5';
            } else {
                $group = 'admidio4';
            }

            $groups[$group][] = ['pid' => $pid, 'data' => $data];
        }

        foreach ($groups as &$group) {
            usort($group, static function (array $a, array $b): int {
                $nameOf = static fn(array $entry): string => (string)($entry['data']['name'] ?? '') ?: $entry['pid'];
                return strnatcasecmp($nameOf($a), $nameOf($b));
            });
        }
        unset($group);

        return $groups;
    }

    /**
     * Release rows, newest version first. With a page ID only those of that page.
     *
     * Each row carries its column values plus **pid** and **rid**.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getReleases(?string $pid = null): array
    {
        $filters = $pid === null ? [] : ['%pageid% = ' . $pid];
        $releases = [];
        foreach ($this->search(self::SCHEMA_RELEASE, $filters) as $row) {
            $row['pid'] = $row['%pageid%'];
            $row['rid'] = (int)$row['%rowid%'];
            unset($row['%pageid%'], $row['%rowid%']);
            if ($row['rid'] === 0 || trim((string)$row['version']) === '') {
                continue;
            }
            $releases[] = $row;
        }

        usort($releases, static function (array $a, array $b): int {
            return version_compare((string)$b['version'], (string)$a['version'])
                ?: strcmp((string)$b['release_date'], (string)$a['release_date']);
        });

        return $releases;
    }

    /**
     * One release row of one page, or null.
     */
    public function findRelease(string $pid, int $rid): ?array
    {
        foreach ($this->getReleases($pid) as $release) {
            if ($release['rid'] === $rid) {
                return $release;
            }
        }
        return null;
    }

    /**
     * Query one schema with every column plus page ID and row ID, as raw values.
     *
     * @param string[] $filters struct filter lines, e.g. "%pageid% = en:plugins:x"
     * @return array<int,array<string,mixed>>
     */
    private function search(string $schema, array $filters = []): array
    {
        $cols = ['%pageid%', '%rowid%'];
        foreach ((new Schema($schema))->getColumns(false) as $column) {
            $cols[] = $column->getLabel();
        }

        $lines = ['schema: ' . $schema, 'cols: ' . implode(', ', $cols)];
        foreach ($filters as $filter) {
            $lines[] = 'filter: ' . $filter;
        }

        // Not dynamic: request parameters must not be able to add filters to our queries.
        $search = new SearchConfig((new ConfigParser($lines))->getConfig(), false);

        $rows = [];
        foreach ($search->getRows() as $values) {
            $row = [];
            foreach ($values as $value) {
                $row[$value->getColumn()->getLabel()] = $value->getRawValue();
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * The page whose plugin data and releases a page shows.
     *
     * Plugin data lives on one page per plugin, in the canonical language (en:plugins:x). A page in
     * another language (de:plugins:x) has no data of its own and shows that of its counterpart, so
     * both share one release list. An explicit page always wins.
     */
    public function sourcePage(string $pid, string $explicit = ''): string
    {
        $explicit = cleanID($explicit);
        if ($explicit !== '') {
            return $explicit;
        }
        if ($this->hasPluginData($pid)) {
            return $pid;
        }

        $canonical = cleanID((string)$this->getConf('canonical_lang'));
        if ($canonical !== '' && preg_match('/^([a-z]{2}(?:-[a-z]+)?):(.+)$/', $pid, $match)
            && $match[1] !== $canonical) {
            $counterpart = $canonical . ':' . $match[2];
            if (page_exists($counterpart) && $this->hasPluginData($counterpart)) {
                return $counterpart;
            }
        }

        return $pid;
    }

    /**
     * Whether a page carries admidio_plugin data (a plugin ID) itself.
     */
    public function hasPluginData(string $pid): bool
    {
        try {
            return trim((string)($this->getPluginData($pid)['plugin_id'] ?? '')) !== '';
        } catch (Throwable $ignored) {
            return false;
        }
    }

    /**
     * The language a page is shown in: the language namespace it lives in (en:plugins:x, de:...),
     * or the wiki's language when that namespace is not one this plugin has strings for.
     */
    public function displayLanguage(string $pid): string
    {
        global $conf;

        $namespace = strtolower(explode(':', $pid)[0]);
        if (preg_match('/^[a-z]{2}(-[a-z]+)?$/', $namespace) && is_dir(__DIR__ . '/lang/' . $namespace)) {
            return $namespace;
        }

        return (string)($conf['lang'] ?? 'en');
    }

    /**
     * A string of this plugin in a given language, whatever language the wiki interface uses.
     *
     * getLang() answers in the wiki's language, but a German plugin page has to read German even
     * for a visitor browsing the wiki in English, and the other way round.
     */
    public function langFor(string $language, string $key): string
    {
        if (!isset($this->languages[$language])) {
            $lang = [];
            foreach (array_unique(['en', $language]) as $load) {
                $file = __DIR__ . '/lang/' . $load . '/lang.php';
                if (preg_match('/^[a-z]{2}(-[a-z]+)?$/', $load) && file_exists($file)) {
                    include $file;
                }
            }
            $this->languages[$language] = $lang;
        }

        return (string)($this->languages[$language][$key] ?? $this->getLang($key));
    }

    /** Strings per language, as langFor() loaded them. @var array<string,array<string,string>> */
    private array $languages = [];

    /**
     * A field that exists per language: "<field>_<language>" when it has content, else the
     * English "<field>". So a German page shows the German note and falls back to the English one.
     */
    public function localized(array $row, string $field, string $language): string
    {
        $translated = trim((string)($row[$field . '_' . strtolower($language)] ?? ''));

        return $translated !== '' ? $translated : trim((string)($row[$field] ?? ''));
    }

    /** Guards renderText() against rendering itself. */
    private static bool $rendering = false;

    /**
     * An author's text as HTML: DokuWiki syntax, so that a comment or a note can link to a
     * changelog (`[[https://…|Changelog]]`) or emphasise a word.
     *
     * Raw HTML is only possible when the wiki allows it at all ($conf['htmlok']) - this goes
     * through DokuWiki's own parser, not around it. This plugin's own syntax and struct's
     * aggregation blocks are removed first: rendering them here would call this renderer again, or
     * put a table inside a release entry.
     *
     * @param bool $inline Strip the wrapping paragraph, for text inside a line.
     */
    public function renderText(string $text, bool $inline = true): string
    {
        $text = preg_replace('/\{\{\s*admidioplugins>[^}]*\}\}/i', '', $text);
        $text = preg_replace('/^\s*-{4,}\s*struct\s+\w+\s*-{4,}\s*$/mi', '', (string)$text);
        $text = trim((string)$text);

        if ($text === '') {
            return '';
        }
        if (self::$rendering) {
            return hsc($text);
        }

        self::$rendering = true;
        try {
            $html = (string)p_render('xhtml', p_get_instructions($text), $info);
        } finally {
            self::$rendering = false;
        }

        if ($inline) {
            $html = preg_replace('#^\s*<p>(.*)</p>\s*$#s', '$1', trim($html));
        }

        return trim((string)$html);
    }

    /**
     * An author's text as plain text, for the catalogue: the wiki syntax a reader would otherwise
     * see as brackets and asterisks is resolved, a link becomes "label (address)".
     */
    public function plainText(string $text): string
    {
        $text = preg_replace('/\[\[([^|\]]+)\|([^\]]+)\]\]/', '$2 ($1)', $text);
        $text = preg_replace('/\[\[([^\]]+)\]\]/', '$1', (string)$text);
        $text = preg_replace('/\{\{[^}]*\}\}/', '', (string)$text);
        $text = preg_replace('/^\s*-{4,}\s*struct\s+\w+\s*-{4,}\s*$/mi', '', (string)$text);
        $text = str_replace(['**', "''", '__'], '', (string)$text);
        $text = preg_replace('#(?<!:)//#', '', $text);            // italics, but not a URL's "//"
        $text = preg_replace('/\\\\\\\\\s*/', "\n", (string)$text); // DokuWiki's forced line break

        return trim((string)$text);
    }

    /**
     * Whether a struct checkbox of a row is ticked.
     */
    public static function isFlagSet(array $row, string $field): bool
    {
        return trim((string)($row[$field] ?? '')) !== '';
    }

    /**
     * The info box: name, description, facts, links and the current download.
     */
    public function renderInfoBox(array $data, array $releases, string $language = 'en'): string
    {
        $text = static fn(string $key): string => trim((string)($data[$key] ?? ''));
        $label = fn(string $key): string => $this->langFor($language, $key);

        $status = strtolower($text('plugin_status')) ?: 'active';
        $latest = null;
        foreach ($releases as $release) {
            if (self::normalizeStatus((string)$release['release_status']) === 'stable') {
                $latest = $release;
                break;
            }
        }

        $html = '<div class="admidioplugins-info status-' . hsc($status) . '">';

        $html .= '<div class="admidioplugins-info-head">';
        $html .= '<span class="admidioplugins-name">' . hsc($text('name') ?: $text('plugin_id')) . '</span>';
        if ($status !== 'active') {
            $html .= ' <span class="admidioplugins-badge badge-' . hsc($status) . '">'
                . hsc($label('plugin_status_' . $status) ?: $status) . '</span>';
        }
        $html .= '</div>';

        $description = $this->localized($data, 'description', $language);
        if ($description !== '') {
            $html .= '<p class="admidioplugins-description">' . hsc($description) . '</p>';
        }

        $multiValueText = static function (string $key) use ($data): string {
            $values = $data[$key] ?? [];
            $values = is_array($values) ? $values : [$values];
            $values = array_filter(array_map('trim', $values), 'strlen');
            return $values ? hsc(implode(', ', $values)) : '';
        };

        $authorUrl = $text('author_url');
        $authorName = hsc($text('author'));
        $facts = [
            'plugin_id' => $text('plugin_id') !== '' ? '<code>' . hsc($text('plugin_id')) . '</code>' : '',
            // The author's name links to their page when one is given, rather than repeating the
            // same link separately among homepage/repository below.
            'author' => preg_match('~^https?://~i', $authorUrl)
                ? '<a class="urlextern" href="' . hsc($authorUrl) . '" rel="noopener">' . $authorName . '</a>'
                : $authorName,
        ];
        // The maintainer is worth a row of its own only when it differs from the author - the
        // common case the struct field's own default assumes.
        $maintainer = $text('maintainer');
        if ($maintainer !== '' && $maintainer !== $text('author')) {
            $facts['maintainer'] = hsc($maintainer);
        }
        $facts['license'] = hsc($text('license'));
        $facts['category'] = hsc($text('category'));
        $facts['supported_databases'] = $multiValueText('supported_databases');

        $translations = $data['supported_translations'] ?? [];
        $translations = is_array($translations) ? $translations : [$translations];
        $translations = array_filter(array_map('trim', $translations), 'strlen');
        if ($translations) {
            $facts['supported_translations'] = hsc(implode(', ', array_map(
                static function (string $code): string {
                    // Data entry has used both "de-DE" (languages/*.xml naming) and "de_DE";
                    // accept either.
                    return self::LANGUAGE_NAMES[str_replace('_', '-', $code)] ?? $code;
                },
                $translations
            )));
        }
        $tags = $data['tags'] ?? [];
        $tags = is_array($tags) ? $tags : [$tags];
        $tags = array_filter(array_map('trim', $tags), 'strlen');
        if ($tags) {
            $facts['tags'] = implode(' ', array_map(
                static fn(string $tag): string => '<span class="admidioplugins-tag">' . hsc($tag) . '</span>',
                $tags
            ));
        }
        if ($latest !== null) {
            $requires = self::isFlagSet($latest, 'hide_requires') ? '' : $this->formatRequires($latest);
            $facts['latest'] = hsc((string)$latest['version']) . ($requires !== '' ? ' <span class="admidioplugins-requires">' . $requires . '</span>' : '');
        }

        $html .= '<dl class="admidioplugins-facts">';
        foreach ($facts as $key => $value) {
            if ($value !== '') {
                $html .= '<dt>' . hsc($label('info_' . $key)) . '</dt><dd>' . $value . '</dd>';
            }
        }
        $html .= '</dl>';

        $links = [];
        foreach (['homepage', 'repository'] as $key) {
            $url = $text($key);
            if (preg_match('~^https?://~i', $url)) {
                $links[] = '<a class="urlextern" href="' . hsc($url) . '" rel="noopener">' . hsc($label('info_' . $key)) . '</a>';
            }
        }
        if ($latest !== null) {
            $url = $this->publicDownloadUrl((string)$latest['download']);
            if ($url !== null) {
                $links[] = '<a class="admidioplugins-download" href="' . hsc($url) . '">'
                    . hsc(sprintf($label('info_download'), (string)$latest['version'])) . '</a>';
            }
        }
        if ($links) {
            $html .= '<p class="admidioplugins-links">' . implode(' ', $links) . '</p>';
        }

        return $html . '</div>';
    }

    // ------------------------------------------------------------------------------------------
    // Downloads
    // ------------------------------------------------------------------------------------------

    /**
     * The media ID a download value names, or null if it is a web address.
     *
     * Accepts "ns:file.zip", ":ns:file.zip" and "{{ns:file.zip|label}}".
     */
    public function parseMediaId(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || preg_match('~^[a-z][a-z0-9+.-]*://~i', $value)) {
            return null;
        }
        if (preg_match('/^\{\{\s*([^?|}\s]+)(?:\?[^|}]*)?(?:\|[^}]*)?\s*\}\}$/u', $value, $match)) {
            $value = $match[1];
        }
        return cleanID($value);
    }

    /**
     * The public, absolute address of a download, or null if there is none anybody may fetch.
     */
    public function publicDownloadUrl(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (preg_match('~^https?://~i', $value)) {
            return $value;
        }

        $media = $this->parseMediaId($value);
        if ($media === null || $media === '' || !file_exists(mediaFN($media))) {
            return null;
        }
        if (auth_aclcheck($media, '', []) < AUTH_READ) {
            return null;
        }
        return ml($media, '', true, '&', true);
    }

    /**
     * "Admidio >=5.1 · PHP >=8.2" for a release row, HTML-escaped. '' without restrictions.
     */
    public function formatRequires(array $release): string
    {
        $parts = [];
        foreach (['requires_admidio' => 'Admidio', 'requires_php' => 'PHP'] as $key => $label) {
            $constraint = trim((string)($release[$key] ?? ''));
            if ($constraint !== '') {
                $parts[] = hsc($label . ' ' . $constraint);
            }
        }
        return implode(' · ', $parts);
    }

    // ------------------------------------------------------------------------------------------
    // Archive inspection
    // ------------------------------------------------------------------------------------------

    /**
     * Fetch a plugin archive and read what it is.
     *
     * The archive has to satisfy the same packaging rule Admidio's installer enforces: exactly one
     * top-level directory, containing plugin.json and plugin.php. The plugin ID is the "id" of
     * plugin.json or, if it states none, the name of that directory - so the source archive GitHub
     * generates for a release tag (repository-1.2.0/) is accepted when its manifest names the ID.
     *
     * An archive without plugin.json is a plugin for Admidio before the plugin manager. With
     * $allowLegacy it is accepted all the same, as long as it is a readable ZIP with one top-level
     * directory; the result then has legacy = true, no version, no requirements and no checksum,
     * because nothing in such an archive states them and it must never reach the catalogue.
     *
     * @param string $download    Web address or media ID of the archive.
     * @param string $expectedId  The plugin ID the archive must contain; '' accepts any valid ID.
     * @param bool   $allowLegacy Whether an archive without plugin.json is accepted.
     * @return array{id:string,version:string,name:string,author:string,license:string,icon:string,
     *               requires_admidio:string,requires_php:string,sha256:string,size:int,download:string,
     *               legacy:bool}
     * @throws RuntimeException with a message meant for the plugin author.
     */
    public function inspectArchive(string $download, string $expectedId = '', bool $allowLegacy = false): array
    {
        $download = trim($download);
        if ($download === '') {
            throw new RuntimeException($this->getLang('err_no_download'));
        }

        $temporary = null;
        $media = $this->parseMediaId($download);

        try {
            if ($media === null) {
                $temporary = $this->fetchArchive($download);
                $file = $temporary;
            } else {
                $file = mediaFN($media);
                if ($media === '' || !file_exists($file)) {
                    throw new RuntimeException(sprintf($this->getLang('err_media_missing'), $download));
                }
                if (auth_aclcheck($media, '', []) < AUTH_READ) {
                    throw new RuntimeException(sprintf($this->getLang('err_media_not_public'), $media));
                }
                $download = ':' . $media;
            }

            $size = (int)filesize($file);
            if ($size > $this->maxArchiveBytes()) {
                throw new RuntimeException(sprintf($this->getLang('err_too_large'), $this->maxArchiveBytes()));
            }

            $layout = $this->checkLayout($file);
            $root = $layout['root'];

            if (!$layout['manifest']) {
                if (!$allowLegacy) {
                    throw new RuntimeException(sprintf($this->getLang('err_layout_missing'), self::MANIFEST_FILE));
                }
                return [
                    'id' => $expectedId,
                    'version' => '',
                    'name' => '',
                    'author' => '',
                    'license' => '',
                    'icon' => '',
                    'requires_admidio' => '',
                    'requires_php' => '',
                    'sha256' => '',
                    'size' => $size,
                    'download' => $download,
                    'legacy' => true,
                ];
            }
            if (!$layout['entry']) {
                throw new RuntimeException(sprintf($this->getLang('err_layout_missing'), self::ENTRY_FILE));
            }

            $manifest = $this->readManifest($file, $root);

            $id = $root;
            if (array_key_exists('id', $manifest)) {
                $id = is_string($manifest['id']) ? $manifest['id'] : '';
                if (!self::isValidId($id)) {
                    throw new RuntimeException(sprintf($this->getLang('err_manifest_id'), $id));
                }
            } elseif (!self::isValidId($root)) {
                throw new RuntimeException(sprintf($this->getLang('err_layout_id'), $root));
            }
            if ($expectedId !== '' && $id !== $expectedId) {
                throw new RuntimeException(sprintf($this->getLang('err_wrong_id'), $id, $expectedId));
            }

            $version = is_string($manifest['version'] ?? null) ? trim($manifest['version']) : '';
            if ($version === '' || !self::isValidVersion($version)) {
                throw new RuntimeException(sprintf($this->getLang('err_bad_version'), $version));
            }

            $requires = is_array($manifest['requires'] ?? null) ? $manifest['requires'] : [];
            $requiresAdmidio = is_string($requires['admidio'] ?? null) ? trim($requires['admidio']) : '';
            $requiresPhp = is_string($requires['php'] ?? null) ? trim($requires['php']) : '';
            foreach ([$requiresAdmidio, $requiresPhp] as $constraint) {
                if (!self::isReadableConstraint($constraint)) {
                    throw new RuntimeException(sprintf($this->getLang('err_bad_constraint'), $constraint));
                }
            }

            return [
                'id' => $id,
                'version' => $version,
                'name' => is_string($manifest['name'] ?? null) ? $manifest['name'] : '',
                'author' => is_string($manifest['author'] ?? null) ? $manifest['author'] : '',
                'license' => is_string($manifest['license'] ?? null) ? $manifest['license'] : '',
                'icon' => is_string($manifest['icon'] ?? null) ? $manifest['icon'] : '',
                'requires_admidio' => $requiresAdmidio,
                'requires_php' => $requiresPhp,
                'sha256' => hash_file('sha256', $file),
                'size' => $size,
                'download' => $download,
                'legacy' => false,
            ];
        } finally {
            if ($temporary !== null) {
                @unlink($temporary);
            }
        }
    }

    private function maxArchiveBytes(): int
    {
        return max(1, (int)$this->getConf('max_archive_mb')) * 1048576;
    }

    /**
     * Download an archive to a temporary file.
     */
    private function fetchArchive(string $url): string
    {
        global $conf;

        if (!preg_match('~^https?://~i', $url)) {
            throw new RuntimeException(sprintf($this->getLang('err_not_url'), $url));
        }
        if (stripos($url, 'http://') === 0 && !$this->getConf('allow_http')) {
            throw new RuntimeException($this->getLang('err_http'));
        }

        $http = new SafeHttpClient((bool)$this->getConf('allow_private_hosts'));
        $http->timeout = max(5, (int)$this->getConf('fetch_timeout'));
        $http->max_redirect = 5;
        $http->max_bodysize = $this->maxArchiveBytes();
        $http->max_bodysize_abort = true;
        $http->keep_alive = false;

        $body = $http->get($url);
        if ($body === false || (int)$http->status !== 200) {
            $reason = $http->error ?: ('HTTP ' . $http->status);
            throw new RuntimeException(sprintf($this->getLang('err_fetch'), $url, $reason));
        }

        $file = tempnam($conf['tmpdir'], 'admidioplugins');
        if ($file === false || file_put_contents($file, $body) === false) {
            throw new RuntimeException($this->getLang('err_tmp'));
        }
        return $file;
    }

    /**
     * Check the packaging rule: safe names, the size limits and one top-level directory.
     *
     * @return array{root:string,manifest:bool,entry:bool} The top-level directory, and whether it
     *         holds plugin.json and plugin.php.
     */
    private function checkLayout(string $file): array
    {
        try {
            $zip = new Zip();
            $zip->open($file);
            $entries = $zip->contents();
        } catch (Throwable $e) {
            throw new RuntimeException($this->getLang('err_not_zip'));
        }

        if (!$entries) {
            throw new RuntimeException($this->getLang('err_not_zip'));
        }
        if (count($entries) > self::MAX_ENTRIES) {
            throw new RuntimeException($this->getLang('err_layout_size'));
        }

        $root = null;
        $total = 0;
        $hasManifest = false;
        $hasEntry = false;

        foreach ($entries as $entry) {
            $name = str_replace('\\', '/', $entry->getPath());
            if ($name === '' || $name[0] === '/' || str_contains($name, "\0")
                || preg_match('~(^|/)\.\.(/|$)~', $name) || preg_match('/^[A-Za-z]:/', $name)) {
                throw new RuntimeException(sprintf($this->getLang('err_unsafe_name'), $name));
            }

            $total += (int)$entry->getSize();
            if ($total > self::MAX_EXTRACTED_BYTES) {
                throw new RuntimeException($this->getLang('err_layout_size'));
            }

            $segments = explode('/', trim($name, '/'));
            if (array_intersect($segments, self::SKIPPED) !== []) {
                continue;
            }
            if (count($segments) < 2 && !$entry->getIsdir()) {
                throw new RuntimeException($this->getLang('err_layout_root'));
            }
            if ($root === null) {
                $root = $segments[0];
            } elseif ($segments[0] !== $root) {
                throw new RuntimeException($this->getLang('err_layout_root'));
            }

            $hasManifest = $hasManifest || $name === $root . '/' . self::MANIFEST_FILE;
            $hasEntry = $hasEntry || $name === $root . '/' . self::ENTRY_FILE;
        }

        if ($root === null) {
            throw new RuntimeException($this->getLang('err_layout_root'));
        }

        return ['root' => $root, 'manifest' => $hasManifest, 'entry' => $hasEntry];
    }

    /**
     * Read and decode <root>/plugin.json from the archive.
     */
    private function readManifest(string $file, string $root): array
    {
        $directory = io_mktmpdir();
        if (!$directory) {
            throw new RuntimeException($this->getLang('err_tmp'));
        }

        try {
            $zip = new Zip();
            $zip->open($file);
            $zip->extract($directory, '', '', '/^' . preg_quote($root . '/' . self::MANIFEST_FILE, '/') . '$/');

            $raw = @file_get_contents($directory . '/' . $root . '/' . self::MANIFEST_FILE);
            $manifest = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($manifest)) {
                throw new RuntimeException($this->getLang('err_manifest'));
            }
            return $manifest;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException($this->getLang('err_manifest'));
        } finally {
            io_rmdir($directory, true);
        }
    }

    // ------------------------------------------------------------------------------------------
    // Writing release rows
    // ------------------------------------------------------------------------------------------

    /**
     * Save a release row of a page. Missing columns are stored empty.
     *
     * This bypasses the schema's "allowed editors": callers must have checked the page ACL.
     *
     * @return int The row ID.
     */
    public function saveRelease(string $pid, array $values, int $rid = 0): int
    {
        $row = [];
        foreach ((new Schema(self::SCHEMA_RELEASE))->getColumns(false) as $column) {
            $label = $column->getLabel();
            $row[$label] = array_key_exists($label, $values) ? $values[$label] : '';
            if ($column->isMulti() && !is_array($row[$label])) {
                $row[$label] = $row[$label] === '' ? [] : [$row[$label]];
            }
        }

        $access = AccessTable::getSerialAccess(self::SCHEMA_RELEASE, $pid, $rid);
        $validator = $access->getValidator($row);
        if (!$validator->validate()) {
            throw new RuntimeException(implode("\n", $validator->getErrors()));
        }
        if (!$validator->saveData()) {
            throw new RuntimeException($this->getLang('err_save'));
        }

        return (int)$access->getRid();
    }

    /**
     * Delete a release row of a page.
     */
    public function deleteRelease(string $pid, int $rid): void
    {
        if ($this->findRelease($pid, $rid) === null) {
            throw new RuntimeException($this->getLang('err_no_release'));
        }
        AccessTable::getSerialAccess(self::SCHEMA_RELEASE, $pid, $rid)->clearData();
    }

    /**
     * Record a change to the releases in the page history, so that it shows up in the recent
     * changes and can be attributed. Serial data is not versioned by struct itself.
     */
    public function recordChange(string $pid, string $summary): void
    {
        /** @var helper_plugin_struct $struct */
        $struct = plugin_load('helper', 'struct');
        if ($struct) {
            $struct::createPageRevision($pid, $summary);
        }
    }
}
