<?php

/**
 * German language file for the admidioplugins plugin
 */

// Plugin info box
$lang['info_plugin_id'] = 'Plugin-ID';
$lang['info_author'] = 'Autor';
$lang['info_maintainer'] = 'Betreuer';
$lang['info_license'] = 'Lizenz';
$lang['info_category'] = 'Kategorie';
$lang['info_supported_databases'] = 'Unterstützte Datenbanken';
$lang['info_supported_translations'] = 'Unterstützte Sprachen';
$lang['info_tags'] = 'Schlagwörter';
$lang['info_latest'] = 'Aktuelle Version';
$lang['info_homepage'] = 'Homepage';
$lang['info_repository'] = 'Quellcode';
$lang['info_download'] = 'Version %s herunterladen';
$lang['plugin_status_active'] = 'aktiv';
$lang['plugin_status_deprecated'] = 'veraltet';
$lang['plugin_status_unmaintained'] = 'nicht mehr gepflegt';
$lang['plugin_status_legacy'] = 'nur für ältere Admidio-Versionen';
$lang['plugin_status_archived'] = 'archiviert';

// Plugin-Übersicht
$lang['overview_filter'] = 'Plugins durchsuchen …';
$lang['overview_admidio6'] = 'Plugins kompatibel mit Admidio 6';
$lang['overview_admidio5'] = 'Plugins kompatibel mit Admidio 5';
$lang['overview_admidio4'] = 'Plugins nur kompatibel mit Admidio 4 oder älter';

// Release list
$lang['releases_none'] = 'Es wurden noch keine Versionen veröffentlicht.';
$lang['release_label'] = 'Version %s';
$lang['release_requires'] = 'Benötigt';
$lang['status_stable'] = 'stabil';
$lang['status_rc'] = 'Release Candidate';
$lang['status_beta'] = 'Beta';
$lang['status_alpha'] = 'Alpha';
$lang['status_withdrawn'] = 'zurückgezogen';

// Release form
$lang['btn_add'] = 'Version hinzufügen';
$lang['btn_edit'] = 'Bearbeiten';
$lang['btn_delete'] = 'Löschen';
$lang['btn_inspect'] = 'Archiv prüfen';
$lang['btn_save'] = 'Speichern';
$lang['btn_cancel'] = 'Abbrechen';
$lang['field_download'] = 'Archiv';
$lang['field_release_status'] = 'Status';
$lang['field_release_date'] = 'Veröffentlicht am';
$lang['field_requires_admidio'] = 'Benötigt Admidio';
$lang['field_requires_php'] = 'Benötigt PHP';
$lang['field_comment'] = 'Kommentar (englisch)';
$lang['field_comment_de'] = 'Kommentar (deutsch)';
$lang['field_notes'] = 'Anmerkungen (englisch)';
$lang['field_notes_de'] = 'Anmerkungen (deutsch)';
$lang['field_hide_requires'] = 'Voraussetzungen auf der Seite nicht anzeigen';
$lang['hint_download'] = 'Adresse des ZIP-Archivs (GitHub-Release, eigene Homepage, …) oder die Medien-ID eines in dieses Wiki hochgeladenen Archivs. Version und Voraussetzungen werden aus dessen plugin.json gelesen.';
$lang['hint_comment'] = 'Wird neben der Version angezeigt, z. B. "Sicherheitsupdate" oder "erste Version für Admidio 5.1".';
$lang['hint_notes'] = 'Wird unter der Version angezeigt, für ein paar Zeilen zu dieser Version.';
$lang['hint_hide_requires'] = 'Im Katalog stehen die Voraussetzungen immer. Auf der Seite kann man sie weglassen, wenn sie dieselben sind wie bei der vorigen Version.';
$lang['hint_requires'] = 'Aus plugin.json übernommen. Nur einschränken, wenn nach der Veröffentlichung eine Inkompatibilität bekannt wurde, z. B. ">=5.1 <5.3".';

// Page history
$lang['summary_added'] = 'Version %s hinzugefügt';
$lang['summary_changed'] = 'Version %s geändert';
$lang['summary_deleted'] = 'Version %s gelöscht';

// Errors
$lang['err_not_ready'] = 'Das struct-Plugin oder die Admidio-Plugin-Schemas sind nicht eingerichtet.';
$lang['err_option'] = 'Unbekannte oder ungültige Option der Versionsliste: "%s".';
$lang['err_method'] = 'Diese Anfrage muss mit POST gesendet werden.';
$lang['err_login'] = 'Bitte melde dich an.';
$lang['err_token'] = 'Das Sicherheitstoken ist ungültig. Bitte lade die Seite neu.';
$lang['err_no_page'] = 'Die Plugin-Seite existiert nicht.';
$lang['err_acl'] = 'Du darfst diese Plugin-Seite nicht bearbeiten.';
$lang['err_op'] = 'Unbekannte Operation.';
$lang['err_internal'] = 'Etwas ist schiefgegangen. Die Einzelheiten wurden protokolliert.';
$lang['err_no_plugin_id'] = 'Trage zuerst eine gültige Plugin-ID in die Plugin-Daten dieser Seite ein (Kleinbuchstaben und Ziffern, getrennt durch einzelne Bindestriche oder Unterstriche).';
$lang['err_no_download'] = 'Gib die Adresse des Archivs ein.';
$lang['err_not_url'] = '"%s" ist weder eine Webadresse noch eine Mediendatei dieses Wikis.';
$lang['err_http'] = 'Archive müssen über https ausgeliefert werden.';
$lang['err_fetch'] = 'Das Archiv unter %s konnte nicht geladen werden: %s';
$lang['err_tmp'] = 'Das Archiv konnte nicht zwischengespeichert werden.';
$lang['err_media_missing'] = 'Es gibt keine Mediendatei %s in diesem Wiki.';
$lang['err_media_not_public'] = 'Die Mediendatei %s kann von Besuchern nicht heruntergeladen werden. Lade sie in einen Namensraum hoch, den alle lesen dürfen.';
$lang['err_too_large'] = 'Das Archiv ist größer als %d Bytes.';
$lang['err_not_zip'] = 'Die Datei ist kein lesbares ZIP-Archiv.';
$lang['err_layout_size'] = 'Das Archiv enthält zu viele oder zu große Dateien.';
$lang['err_unsafe_name'] = 'Das Archiv enthält den unsicheren Dateinamen "%s".';
$lang['err_layout_root'] = 'Alle Dateien des Archivs müssen in einem Verzeichnis liegen, das wie die Plugin-ID heißt.';
$lang['err_layout_id'] = 'Das oberste Verzeichnis "%s" des Archivs ist keine gültige Plugin-ID.';
$lang['err_layout_missing'] = 'Das Archiv enthält kein %s im Plugin-Verzeichnis.';
$lang['err_wrong_id'] = 'Das Archiv enthält das Plugin "%s", diese Seite beschreibt aber "%s".';
$lang['err_manifest'] = 'plugin.json im Archiv ist kein gültiges JSON.';
$lang['err_bad_version'] = 'plugin.json nennt keine verwendbare Version ("%s").';
$lang['err_bad_constraint'] = 'Die Versionsbeschränkung "%s" ist nicht lesbar. Verwende Angaben wie ">=5.1" oder ">=5.1 <6".';
$lang['err_duplicate'] = 'Version %s wurde auf dieser Seite bereits veröffentlicht.';
$lang['err_version_changed'] = 'Das Archiv enthält Version %s, diese Version ist aber %s. Eine andere Version ist eine neue Version: füge sie stattdessen hinzu.';
$lang['err_status'] = 'Unbekannter Status "%s".';
$lang['err_date'] = 'Ungültiges Datum "%s".';
$lang['err_notes'] = 'Die Anmerkungen dürfen höchstens %d Zeichen lang sein.';
$lang['err_no_release'] = 'Diese Version gibt es auf dieser Seite nicht.';
$lang['err_save'] = 'Die Version konnte nicht gespeichert werden.';

// Strings for script.js
$lang['js']['add_title'] = 'Version hinzufügen';
$lang['js']['edit_title'] = 'Version %s bearbeiten';
$lang['js']['checking'] = 'Archiv wird geprüft …';
$lang['js']['reread_needed'] = 'Lies zuerst das Archiv neu ein: Eine neue Adresse gilt erst, wenn das Archiv dahinter geprüft wurde.';
$lang['js']['btn_reread'] = 'Archiv neu einlesen';
$lang['js']['btn_inspect'] = 'Archiv prüfen';
$lang['js']['saving'] = 'Wird gespeichert …';
$lang['js']['confirm_delete'] = 'Version %s löschen? Installationen wird sie dann nicht mehr angeboten. Um sie sichtbar zu lassen, aber nicht mehr anzubieten, setze stattdessen den Status auf "zurückgezogen".';
$lang['js']['derived_id'] = 'Plugin';
$lang['js']['derived_version'] = 'Version';
$lang['js']['derived_requires_admidio'] = 'Benötigt Admidio';
$lang['js']['derived_requires_php'] = 'Benötigt PHP';
$lang['js']['derived_size'] = 'Größe';
$lang['js']['derived_sha256'] = 'SHA-256';
$lang['js']['any'] = 'beliebig';
$lang['js']['error'] = 'Fehler';
