<?php

/**
 * English language file for the admidioplugins plugin
 */

// Plugin info box
$lang['info_plugin_id'] = 'Plugin ID';
$lang['info_author'] = 'Author';
$lang['info_maintainer'] = 'Maintainer';
$lang['info_license'] = 'License';
$lang['info_category'] = 'Category';
$lang['info_supported_databases'] = 'Supported databases';
$lang['info_supported_translations'] = 'Supported languages';
$lang['info_tags'] = 'Tags';
$lang['info_latest'] = 'Current version';
$lang['info_homepage'] = 'Homepage';
$lang['info_repository'] = 'Source code';
$lang['info_download'] = 'Download version %s';
$lang['plugin_status_active'] = 'active';
$lang['plugin_status_deprecated'] = 'deprecated';
$lang['plugin_status_unmaintained'] = 'unmaintained';
$lang['plugin_status_legacy'] = 'only for older Admidio versions';
$lang['plugin_status_archived'] = 'archived';

// Plugin overview
$lang['overview_filter'] = 'Search plugins …';
$lang['overview_admidio6'] = 'Plugins compatible with Admidio 6';
$lang['overview_admidio5'] = 'Plugins compatible with Admidio 5';
$lang['overview_admidio4'] = 'Plugins only compatible with Admidio 4 or older';

// Release list
$lang['releases_none'] = 'No releases have been published yet.';
$lang['release_label'] = 'Version %s';
$lang['release_requires'] = 'Requires';
$lang['status_stable'] = 'stable';
$lang['status_rc'] = 'release candidate';
$lang['status_beta'] = 'beta';
$lang['status_alpha'] = 'alpha';
$lang['status_withdrawn'] = 'withdrawn';

// Release form
$lang['btn_add'] = 'Add release';
$lang['btn_edit'] = 'Edit';
$lang['btn_delete'] = 'Delete';
$lang['btn_inspect'] = 'Check archive';
$lang['btn_save'] = 'Save';
$lang['btn_cancel'] = 'Cancel';
$lang['field_download'] = 'Archive';
$lang['field_release_status'] = 'Status';
$lang['field_release_date'] = 'Release date';
$lang['field_requires_admidio'] = 'Requires Admidio';
$lang['field_requires_php'] = 'Requires PHP';
$lang['field_comment'] = 'Comment (English)';
$lang['field_comment_de'] = 'Comment (German)';
$lang['field_notes'] = 'Notes (English)';
$lang['field_notes_de'] = 'Notes (German)';
$lang['field_hide_requires'] = 'Do not show the requirements on the page';
$lang['hint_download'] = 'Address of the ZIP archive (GitHub release, your homepage, …) or the media ID of an archive uploaded to this wiki. Version and requirements are read from its plugin.json.';
$lang['hint_comment'] = 'Shown next to the version, for example "security fix" or "first release for Admidio 5.1".';
$lang['hint_notes'] = 'Shown below the version, for a few lines about this release.';
$lang['hint_hide_requires'] = 'The catalogue always states the requirements. Leaving them off the page is useful when they are the same as for the previous release.';
$lang['hint_requires'] = 'Taken from plugin.json. Only narrow it if an incompatibility became known after the release, e.g. ">=5.1 <5.3".';

// Page history
$lang['summary_added'] = 'Release %s added';
$lang['summary_changed'] = 'Release %s changed';
$lang['summary_deleted'] = 'Release %s deleted';

// Errors
$lang['err_not_ready'] = 'The struct plugin or the Admidio plugin schemas are not set up.';
$lang['err_method'] = 'This request must be sent with POST.';
$lang['err_login'] = 'Please log in.';
$lang['err_token'] = 'The security token is invalid. Please reload the page.';
$lang['err_no_page'] = 'The plugin page does not exist.';
$lang['err_acl'] = 'You may not edit this plugin page.';
$lang['err_op'] = 'Unknown operation.';
$lang['err_internal'] = 'Something went wrong. The details have been logged.';
$lang['err_no_plugin_id'] = 'Set a valid plugin ID in the plugin data of this page first (lower case letters and digits, separated by single hyphens or underscores).';
$lang['err_no_download'] = 'Enter the address of the archive.';
$lang['err_not_url'] = '"%s" is neither a web address nor a media file of this wiki.';
$lang['err_http'] = 'Archives must be served over https.';
$lang['err_fetch'] = 'The archive at %s could not be downloaded: %s';
$lang['err_tmp'] = 'The archive could not be stored temporarily.';
$lang['err_media_missing'] = 'There is no media file %s in this wiki.';
$lang['err_media_not_public'] = 'The media file %s cannot be downloaded by visitors. Upload it to a namespace everybody may read.';
$lang['err_too_large'] = 'The archive is larger than %d bytes.';
$lang['err_not_zip'] = 'The file is not a readable ZIP archive.';
$lang['err_layout_size'] = 'The archive contains too many or too large files.';
$lang['err_unsafe_name'] = 'The archive contains the unsafe file name "%s".';
$lang['err_layout_root'] = 'All files of the archive must be inside one directory named after the plugin ID.';
$lang['err_layout_id'] = 'The top-level directory "%s" of the archive is not a valid plugin ID.';
$lang['err_layout_missing'] = 'The archive does not contain %s in its plugin directory.';
$lang['err_wrong_id'] = 'The archive contains the plugin "%s", but this page describes "%s".';
$lang['err_manifest'] = 'plugin.json in the archive is not valid JSON.';
$lang['err_bad_version'] = 'plugin.json declares no usable version ("%s").';
$lang['err_bad_constraint'] = 'The version restriction "%s" cannot be read. Use terms like ">=5.1" or ">=5.1 <6".';
$lang['err_duplicate'] = 'Version %s has already been published on this page.';
$lang['err_version_changed'] = 'The archive contains version %s, but this release is %s. A different version is a new release: add it instead.';
$lang['err_status'] = 'Unknown status "%s".';
$lang['err_date'] = 'Invalid date "%s".';
$lang['err_notes'] = 'The notes may be at most %d characters long.';
$lang['err_no_release'] = 'This release does not exist on this page.';
$lang['err_save'] = 'The release could not be saved.';

// Strings for script.js
$lang['js']['add_title'] = 'Add release';
$lang['js']['edit_title'] = 'Edit release %s';
$lang['js']['checking'] = 'Checking the archive …';
$lang['js']['reread_needed'] = 'Re-read the archive first: a new address only takes effect once the archive behind it has been checked.';
$lang['js']['btn_reread'] = 'Re-read archive';
$lang['js']['btn_inspect'] = 'Check archive';
$lang['js']['saving'] = 'Saving …';
$lang['js']['confirm_delete'] = 'Delete release %s? Installations will no longer be offered it. To keep it visible but stop offering it, set its status to "withdrawn" instead.';
$lang['js']['derived_id'] = 'Plugin';
$lang['js']['derived_version'] = 'Version';
$lang['js']['derived_requires_admidio'] = 'Requires Admidio';
$lang['js']['derived_requires_php'] = 'Requires PHP';
$lang['js']['derived_size'] = 'Size';
$lang['js']['derived_sha256'] = 'SHA-256';
$lang['js']['any'] = 'any';
$lang['js']['error'] = 'Error';
