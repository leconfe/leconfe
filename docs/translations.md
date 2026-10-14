# Interface translations

`lang/en/*.php` is the source catalog. Use `general.php` for shared interface
labels and keep domain text in its existing catalog (`scheduled_conference.php`,
`validation.php`, or a new domain catalog). Always add a key with new UI text and
call `__('general.key')` at display time. Explicitly label Filament fields,
actions, page titles and navigation entries; generated English labels cannot be
translated through our catalogs. Keep component IDs stable when translating
labels, especially payment tabs used by plugin hooks.

## Contributor workflow

No application installation or database connection is needed:

```sh
php scripts/translations.php export fr translations-fr.csv
php scripts/translations.php import fr translations-fr.csv
php scripts/translations.php check
```

Exports discover every English PHP catalog and include the English source,
locale translation and status (`missing`, `empty`, `same_as_english`,
`translated`, `invalid_tokens`). Edit the **translation** column only. Preserve
the `path` column: it distinguishes literal dotted keys from nested arrays.
CSV fields preserve UTF-8 accents, apostrophes, quotation marks, HTML,
placeholders and actual newlines. Use UTF-8 when saving spreadsheets, and keep
multi-line cells intact. Blank translations leave the existing entry unchanged.
Import validates the entire input before writing; it rejects unknown keys,
duplicate rows, stale English text, changed placeholders and changed HTML tags.
The check reports missing/empty entries and mismatched placeholders and exits
with a nonzero status. This check runs in CI. A value identical to English is
reported in the export for contributor review, but is a valid fallback.

Laravel's fallback locale is English. New keys have English entries in the other
locale catalogs until contributors translate them. French and Canadian French
include the supplementary labels. Presentation dates refer to the presentation
stage, and the dashboard's review count is labelled **Submissions under review**
because it counts submissions, not individual reviews.

## Built-in and organizer-defined names

Role enum values, stored role names, permissions and assignment IDs remain
unchanged. `UserRole::getLabel()` and `Role::display_name` translate only display
text. Unknown role names display their stored names. Keep authorization and
queries on raw values; use translated labels only in rendered text and options.

File types, contributor roles and tracks have a nullable `builtin_code` column.
Their `display_name` accessor resolves `general.{builtin_code}` in the active
locale. It translates only a recognized code whose stored name still matches
that built-in's original name. Renamed built-ins, custom records, unknown codes
and legacy records with no code display their stored names. Edit forms retain
raw organizer-owned text; viewing or changing the locale never rewrites data.
Built-in codes are excluded from normal mass assignment.

New conferences get coded Author/Translator roles, a General Track and the four
official default file types: Abstract, Full Paper, Poster and Other. The seven
Research/Transcripts/Data/Source Text types found in staging are supported
translation mappings, **not** new default records. Repeating population does not
create replacements for renamed coded records or reset existing citation roles.

## Existing installations

Apply the schema-only migration through the usual release migration process:

```sh
php artisan migrate --force
```

It adds nullable codes and scoped unique indexes without renaming, deleting,
populating or automatically classifying existing records. Review the actual IDs
and names in a specific conference before mapping known built-ins. For example,
after verifying that file type 12 is Research Instrument, track 3 is General
Track, and contributor role 5 is Translator in the selected scopes:

```sh
php artisan translations:map-builtins --conference=2 --scheduled-conference=7 \
  --file-type=12:research_instrument --track=3:general_track \
  --author-role=5:translator --dry-run
```

Repeat that exact command without `--dry-run` to persist the reviewed mapping.
Each mapping is `record-ID:code`. Codes are listed in each model's
`builtinNames()`. The command requires the appropriate scope, validates every
selected ID, checks the exact original name and rejects conflicting codes or
duplicate mappings. It is transactional and safe to repeat. It changes only
`builtin_code`; file associations, required flags, track settings, role IDs,
citation selections and all names remain unchanged. Ambiguous or custom data
should remain uncoded. Do not rerun populate actions to migrate old conferences.
Clear the normal application/model caches after a deployment or catalog import.

## English/French staging verification

Automated regression tests cover locale switching, built-in/custom/legacy
labels, scoped mapping (including dry run and reruns), role assignments,
required uploads, workflow settings, navigation, and lossless CSV round trips.
For a deployed staging application, check both `en` and `fr`:

- Submission creation: heading, Submit As options, track options/policy label.
- Upload: type selection, file descriptions, required-type status/notifications.
- Workflow: submission form, New Item, open-review checkbox, presentation dates.
- Dashboard: overview, conference dates, submission counts and payment actions.
- Payments: General, Submission Fees, Participant Fees, Invoice, Payment Method;
  verify plugin tabs still attach under their original IDs.
- Navigation: Presentations and Review Result, including page titles.
- User/contributor role selection, role badges and track management/public pages.
- A custom name and an uncoded old record: verify their names remain unchanged.
- Remove a French translation in staging and verify English fallback.
