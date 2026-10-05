<?php

/**
 * DokuWiki Plugin admidioplugins (Action Component: release form backend)
 *
 * AJAX call "plugin_admidioplugins" with op = inspect | add | update | delete.
 *
 * Every call needs a logged-in user, a valid security token and edit permission on the plugin
 * page. The plugin ID, version, requirements, size and checksum of a release are never taken from
 * the request: they are read from the archive itself, on "add" again even if "inspect" already did.
 *
 * @license GPL-2.0-or-later
 */

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\Logger;

class action_plugin_admidioplugins_ajax extends ActionPlugin
{
    private const CALL = 'plugin_admidioplugins';
    private const MAX_NOTES = 2000;

    /** @inheritDoc */
    public function register(EventHandler $controller)
    {
        $controller->register_hook('AJAX_CALL_UNKNOWN', 'BEFORE', $this, 'handle');
    }

    public function handle(Event $event, $param): void
    {
        global $INPUT;

        if ($event->data !== self::CALL) {
            return;
        }
        $event->preventDefault();
        $event->stopPropagation();

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        try {
            if ($INPUT->server->str('REQUEST_METHOD') !== 'POST') {
                throw new DomainException($this->getLang('err_method'), 405);
            }
            if ($INPUT->server->str('REMOTE_USER') === '') {
                throw new DomainException($this->getLang('err_login'), 403);
            }
            if (!checkSecurityToken()) {
                throw new DomainException($this->getLang('err_token'), 403);
            }

            $pid = cleanID($INPUT->str('pid'));
            if ($pid === '' || !page_exists($pid)) {
                throw new DomainException($this->getLang('err_no_page'), 404);
            }
            if (auth_quickaclcheck($pid) < AUTH_EDIT) {
                throw new DomainException($this->getLang('err_acl'), 403);
            }

            /** @var helper_plugin_admidioplugins $helper */
            $helper = plugin_load('helper', 'admidioplugins');
            if (!$helper->isReady()) {
                throw new DomainException($this->getLang('err_not_ready'), 500);
            }

            $result = match ($INPUT->str('op')) {
                'inspect' => $this->inspect($helper, $pid),
                'add' => $this->add($helper, $pid),
                'update' => $this->update($helper, $pid),
                'delete' => $this->delete($helper, $pid),
                default => throw new DomainException($this->getLang('err_op'), 400),
            };

            echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (DomainException $e) {
            http_status($e->getCode() ?: 400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (RuntimeException $e) {
            // Problems with the archive or the data, explained for the author.
            http_status(422);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            Logger::error('admidioplugins: release form failed: ' . $e->getMessage(), $e->getTraceAsString(), $e->getFile(), $e->getLine());
            http_status(500);
            echo json_encode(['ok' => false, 'error' => $this->getLang('err_internal')], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Read an archive and tell the author what it contains.
     */
    private function inspect(helper_plugin_admidioplugins $helper, string $pid): array
    {
        global $INPUT;

        $info = $helper->inspectArchive($INPUT->str('download'), $this->pluginId($helper, $pid));
        if ($INPUT->int('rid') > 0) {
            // Re-reading the archive of a published release: it has to be that release's version.
            $this->assertSameVersion($helper, $pid, $INPUT->int('rid'), $info['version']);
        } else {
            $this->assertNewVersion($helper, $pid, $info['version']);
        }

        return ['archive' => $info];
    }

    /**
     * Add a release. The archive is read again; nothing derived is taken from the request.
     */
    private function add(helper_plugin_admidioplugins $helper, string $pid): array
    {
        global $INPUT;

        $info = $helper->inspectArchive($INPUT->str('download'), $this->pluginId($helper, $pid));
        $this->assertNewVersion($helper, $pid, $info['version']);

        $rid = $helper->saveRelease($pid, $this->authorFields() + [
            'version' => $info['version'],
            'release_date' => $this->date($INPUT->str('release_date')) ?: date('Y-m-d'),
            'requires_admidio' => $info['requires_admidio'],
            'requires_php' => $info['requires_php'],
            'download' => $info['download'],
            'sha256' => $info['sha256'],
            'size' => (string)$info['size'],
        ]);
        $helper->recordChange($pid, sprintf($this->getLang('summary_added'), $info['version']));

        return ['rid' => $rid, 'archive' => $info];
    }

    /**
     * Change what may change after publishing: status, date, notes and - to narrow them when an
     * incompatibility turns up later - the version restrictions.
     *
     * With "reread" the archive is fetched again, from the submitted address (which may be a new
     * one), and its address, checksum and size replace the stored ones. The version must stay the
     * same: a different version is a different release.
     */
    private function update(helper_plugin_admidioplugins $helper, string $pid): array
    {
        global $INPUT;

        $release = $helper->findRelease($pid, $INPUT->int('rid'));
        if ($release === null) {
            throw new RuntimeException($this->getLang('err_no_release'));
        }

        $values = $release;
        unset($values['pid'], $values['rid']);
        $values = $this->authorFields() + $values;
        $values['release_date'] = $this->date($INPUT->str('release_date')) ?: (string)$release['release_date'];
        foreach (['requires_admidio', 'requires_php'] as $key) {
            $constraint = trim($INPUT->str($key));
            if (!helper_plugin_admidioplugins::isReadableConstraint($constraint)) {
                throw new RuntimeException(sprintf($this->getLang('err_bad_constraint'), $constraint));
            }
            $values[$key] = $constraint;
        }

        if ($INPUT->bool('reread')) {
            $info = $helper->inspectArchive($INPUT->str('download'), $this->pluginId($helper, $pid));
            $this->assertSameVersion($helper, $pid, $release['rid'], $info['version']);
            $values['download'] = $info['download'];
            $values['sha256'] = $info['sha256'];
            $values['size'] = (string)$info['size'];
        }

        $helper->saveRelease($pid, $values, $release['rid']);
        $helper->recordChange($pid, sprintf($this->getLang('summary_changed'), $release['version']));

        return ['rid' => $release['rid']];
    }

    private function delete(helper_plugin_admidioplugins $helper, string $pid): array
    {
        global $INPUT;

        $release = $helper->findRelease($pid, $INPUT->int('rid'));
        if ($release === null) {
            throw new RuntimeException($this->getLang('err_no_release'));
        }

        $helper->deleteRelease($pid, $release['rid']);
        $helper->recordChange($pid, sprintf($this->getLang('summary_deleted'), $release['version']));

        return ['rid' => $release['rid']];
    }

    /**
     * The plugin ID the page declares. Releases can only be added once it is set.
     */
    private function pluginId(helper_plugin_admidioplugins $helper, string $pid): string
    {
        $id = trim((string)($helper->getPluginData($pid)['plugin_id'] ?? ''));
        if (!helper_plugin_admidioplugins::isValidId($id)) {
            throw new RuntimeException($this->getLang('err_no_plugin_id'));
        }
        return $id;
    }

    private function assertNewVersion(helper_plugin_admidioplugins $helper, string $pid, string $version): void
    {
        foreach ($helper->getReleases($pid) as $release) {
            if (version_compare((string)$release['version'], $version, '==')) {
                throw new RuntimeException(sprintf($this->getLang('err_duplicate'), $version));
            }
        }
    }

    private function assertSameVersion(helper_plugin_admidioplugins $helper, string $pid, int $rid, string $version): void
    {
        $release = $helper->findRelease($pid, $rid);
        if ($release === null) {
            throw new RuntimeException($this->getLang('err_no_release'));
        }
        if (!version_compare((string)$release['version'], $version, '==')) {
            throw new RuntimeException(sprintf($this->getLang('err_version_changed'), $version, $release['version']));
        }
    }

    private function status(string $status): string
    {
        $status = helper_plugin_admidioplugins::normalizeStatus($status);
        if (!in_array($status, helper_plugin_admidioplugins::RELEASE_STATUSES, true)) {
            throw new RuntimeException(sprintf($this->getLang('err_status'), $status));
        }
        return $status;
    }

    private function date(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return '';
        }
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            throw new RuntimeException(sprintf($this->getLang('err_date'), $date));
        }
        return $date;
    }

    private function text(string $value): string
    {
        $value = trim(str_replace("\r\n", "\n", $value));
        if (mb_strlen($value) > self::MAX_NOTES) {
            throw new RuntimeException(sprintf($this->getLang('err_notes'), self::MAX_NOTES));
        }
        return $value;
    }

    /**
     * What an author writes about a release themselves: the status, the comment shown next to the
     * version, the notes below it - comment and notes in English and German - and whether the page
     * repeats the version requirements. The catalogue states them in any case.
     *
     * @return array<string,string>
     */
    private function authorFields(): array
    {
        global $INPUT;

        $values = array('release_status' => $this->status($INPUT->str('release_status')));
        foreach (['comment', 'comment_de', 'notes', 'notes_de'] as $field) {
            $values[$field] = $this->text($INPUT->str($field));
        }
        $values['hide_requires'] = $INPUT->bool('hide_requires') ? 'hide_requires' : '';

        return $values;
    }
}
