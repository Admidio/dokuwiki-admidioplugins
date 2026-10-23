# Admidio plugin directory for DokuWiki

DokuWiki plugin that turns plugin pages with [struct](https://www.dokuwiki.org/plugin:struct) data
into the plugin directory of [Admidio](https://www.admidio.org/):

- **Catalogue export** – serves all plugins and releases as the JSON catalogue Admidio's plugin
  manager reads (format 1, see *Catalogue* below), optionally filtered for one installation.
- **Plugin info box** – renders the `admidio_plugin` page data with its own template instead of
  struct's default table.
- **Release list** – `{{admidioplugins>releases}}` lists the releases of the page: version, status,
  comment and date in the head line, the requirements and the notes below it, in the language of the
  page. Users who may edit the page get an *Add release* button and edit/delete controls.
- **Release form** – an author enters only the address of the ZIP archive (or the media ID of an
  archive uploaded to the wiki). Plugin ID, version, Admidio/PHP requirements, size and SHA-256
  are read from the archive and its `plugin.json`, with the same packaging rules Admidio's
  installer enforces.
- **Struct editor guard** – closes a gap in struct's row editor (see *Security*).

## Requirements

- DokuWiki (tested with 2026-07-14 "Mort"), PHP 8.0+
- struct and sqlite plugins
- The struct schemas `admidio_plugin` (page data, assigned to the plugin namespace) and
  `admidio_plugin_release` (serial data). `setup_admidio_struct.php` creates both.
- For archives on other hosts: outbound HTTPS from the wiki server.

## Installation

Copy or clone this directory to `lib/plugins/admidioplugins/`.

## Page layout

A plugin page consists of free wiki text plus

- the page data of `admidio_plugin`, entered in the page editor. It is shown as the info box
  after the first heading;
- `{{admidioplugins>releases}}` where the releases should be listed.

Do **not** use a `---- struct serial ----` block for the releases: its editor would bypass the
checks of the release form, and the guard refuses it for this schema anyway.

### Selecting releases

Options after `releases` (and after an explicit page, if any) restrict the list, so that a page
can show the current release in a box of its own and the others below it:

    <WRAP download 60%>
    {{admidioplugins>releases latest}}
    </WRAP>

    Older versions:
    {{admidioplugins>releases older}}

| Option | Effect |
|---|---|
| `admidio=5.0[,4.3…]` | only releases whose Admidio requirement accepts one of these versions (as the catalogue's `admidio` parameter) |
| `status=stable[,rc…]` | only releases with one of these statuses |
| `latest` | of the remaining releases, only the newest stable one |
| `latest=5.1,4.3` | the newest stable release for each of these Admidio versions, e.g. one box listing the current release for Admidio 5 and for Admidio 4 |
| `older`, `older=5.1,4.3` | all remaining releases except those `latest` with the same versions would list |

With `status=`, `latest` picks among those statuses instead of stable releases only
(`latest status=beta`: the newest beta). Withdrawn releases are never "latest". A list whose
options match no release renders nothing; an unknown option renders an error message instead of
the list. Edit controls work in every list; *Add release* is offered once, below the last list of
the page.

### Pages in other languages

Plugin data and releases live on one page per plugin, in the canonical language namespace
(`canonical_lang`, default `en`): `en:plugins:x`. Assign the `admidio_plugin` schema to that
namespace only, otherwise two pages would claim the same plugin ID.

A page in another language, e.g. `de:plugins:x`, has no data of its own and shows that of its
counterpart:

    {{admidioplugins>info}}
    {{admidioplugins>releases}}

On a page without plugin data both look up `<canonical_lang>:<rest of the page ID>`; an explicit
page can be given instead (`{{admidioplugins>releases en:plugins:x}}`).

**Language.** Both blocks are shown in the language of the namespace the page is in: `de:plugins:x`
reads German whatever language the wiki interface uses, and vice versa. That applies to the labels
and to the fields that exist twice - the plugin description and a release's comment and notes - with
the English text as the fallback when a translation is missing. A namespace this plugin has no
strings for falls back to the wiki's language. The info box needs no parameter for this: it takes
the language from the page it is rendered on.

The release list notes where the releases are maintained. Adding or editing a release from such a
page stores it on the canonical page and needs edit permission there. `{{admidioplugins>info}}`
renders nothing on the canonical page itself, which shows the box already.

### admidio_plugin (page data)

| Column | Meaning |
|---|---|
| `plugin_id` | Plugin ID as Admidio knows it (the directory name, `[a-z0-9]+([-_][a-z0-9]+)*`). Releases can only be added once it is set; every archive must contain exactly this plugin. |
| `name` | Display name |
| `description`, `description_de` | Short description in English / German |
| `author`, `license`, `icon` | Author, SPDX licence, Bootstrap icon class for Admidio (`bi-…`) |
| `homepage`, `repository` | Links |
| `category`, `tags` | Classification |
| `plugin_status` | `active`, `deprecated`, `unmaintained` (listed); `legacy`, `archived` (not in the catalogue) |

### admidio_plugin_release (serial data)

| Column | Set by |
|---|---|
| `version`, `requires_admidio`, `requires_php`, `download`, `sha256`, `size` | read from the archive when the release is added |
| `release_status` | author: `stable`, `rc`, `beta`, `alpha`, `withdrawn` |
| `release_date` | author |
| `comment`, `comment_de` | author: a few words shown next to the version, e.g. "security fix" |
| `notes`, `notes_de` | author: a few lines shown below the requirements |
| | Both accept DokuWiki syntax, so a comment can link to a changelog: `[[https://github.com/…/releases\|Changelog]]`. Raw HTML only works where the wiki allows it (`htmlok`); this plugin's own syntax and struct blocks are removed, since they would render themselves again. The catalogue receives plain text, with a link as "label (address)". |
| `hide_requires` | author: leaves the Admidio/PHP requirements off the page. The catalogue states them in any case, so this is only about what the page repeats |

After publishing, an author can change status, date, comment, notes and the requirement display,
and narrow the version restrictions (e.g. when an incompatibility with a later Admidio version becomes known). Version,
download and checksum cannot be changed: a new archive is a new release. To stop offering a
release but keep it visible, set it to `withdrawn`.

Each change to the releases creates a page revision ("Release 1.1.0 added"), so it appears in the
recent changes and the page history.

## Catalogue

    doku.php?do=admidioplugins

All parameters are optional:

| Parameter | Values | Effect |
|---|---|---|
| `format` | `1` (default) | Catalogue format; anything else is answered with 400 |
| `admidio` | version, e.g. `5.1.0` | only releases whose `requires.admidio` accepts this version |
| `php` | version, e.g. `8.3.6` | only releases whose `requires.php` accepts this version |
| `channel` | `stable`, `rc`, `beta`, `alpha`, `all` (default) | only releases of these statuses (cumulative); `all` also includes `withdrawn` |
| `releases` | `all` (default), `latest` | `latest` keeps the newest remaining release of each status per plugin |
| `plugin` | comma-separated plugin IDs | only these plugins |

Plugins without any remaining release are left out. The response is cached on the server (until
struct data changes, at most `cache_seconds`) and sent with `ETag` and `Cache-Control: public`.

Only pages anonymous visitors may read, and downloads anonymous visitors may fetch, are part of
the catalogue. Only releases whose archive was checked (by the release form or
`setup_admidio_struct.php --derive`, which record its SHA-256) are part of it; rows imported
without an archive check, such as the releases for the pre-5.1 plugin runtime, are listed on the
pages only. If two pages declare the same `plugin_id`, the page created first owns it.

The format is specified in the Admidio documentation (*Plugin catalogue format 1*).

## Security

struct's aggregation editor (the "add row" form below `struct serial` / `struct lookup`
tables) enforces the page ACL only in JavaScript: its save call checks nothing but the schema's
"allowed editors", and skips its CSRF check for anonymous users. An anonymous POST to
`lib/exe/ajax.php?call=plugin_struct_aggregationeditor_save` adds rows to any page;
`…_delete` removes any row by ID. The guard in `action/guard.php`

- refuses struct's aggregation editor for both Admidio schemas, and inline edits of release rows;
- for all other schemas, requires a valid security token and edit permission on the page the row
  belongs to (for deletions looked up by row ID), and a logged-in user for global data.

This was reported to the struct maintainers on 2026-09-18.

Additionally `setup_admidio_struct.php` sets `allowed editors: @admin` on the release schema, so
struct's own editors are closed for it even without the guard.

The release form requires a logged-in user with edit permission on the page and a valid security
token for every call. Remote archives are fetched only over HTTPS and only from public addresses
(every redirect is checked), with the size limit of Admidio's installer.

## Configuration

| Option | Default | |
|---|---|---|
| `cache_seconds` | 900 | Cache lifetime of the catalogue |
| `max_archive_mb` | 32 | Largest archive (Admidio's own limit) |
| `fetch_timeout` | 20 | Timeout for remote archives |
| `allow_http` | off | Accept `http://` archives |
| `allow_private_hosts` | off | Accept archives from private addresses (development only) |
| `guard_struct_editor` | on | See *Security* |
| `canonical_lang` | `en` | Language namespace holding the plugin data (see *Pages in other languages*) |
