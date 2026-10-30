# Interface translations

`lang/en/*.php` is the source catalog. Put shared interface labels in
`general.php` and keep domain text in its existing catalog
(`scheduled_conference.php`, `validation.php`, or a new domain catalog).

When you add interface text:

1. Add the key to `lang/en/general.php` with the English text.
2. Add the same key to every other `lang/*/general.php`. Use the translation if
   you have one, otherwise the English text.
3. Call `__('general.key')` where the text is displayed.

Give Filament fields, actions, page titles, and navigation entries an explicit
label. Filament generates English labels from field and class names, and the
catalogs cannot translate them. Keep component IDs stable when you translate a
label: plugins attach to payment tabs by ID.

`tests/Feature/TranslationCompletenessTest.php` fails when a locale is missing a
key from `general.php`, `scheduled_conference.php`, or `validation.php`, when a
placeholder changes, or when a value contains a literal `\n` instead of a line
break. Laravel falls back to English for any key a locale does not define.

## Export and import a locale

`scripts/translations.php` works on the PHP catalogs directly. It does not need
an installed application or a database.

```sh
php scripts/translations.php export fr translations-fr.csv
php scripts/translations.php import fr translations-fr.csv
php scripts/translations.php check fr
```

The export covers every catalog in `lang/en`. Each row has the English text, the
current translation, and a status: `missing`, `empty`, `same_as_english`,
`translated`, or `invalid_tokens`. Edit the `translation` column only, and keep
the `path` column as exported: it tells nested arrays apart from keys that
contain a dot.

CSV cells keep UTF-8 accents, apostrophes, quotation marks, HTML, placeholders,
and real line breaks. Save the file as UTF-8 and keep multi-line cells intact. A
blank translation leaves the current entry unchanged.

Import reads the whole file before it writes anything. It rejects unknown keys,
duplicate rows, rows whose English text has changed since the export, and
translations that change placeholders or HTML tags.

Import rewrites each catalog it changes as a short array with four-space
indentation and single-quoted strings, the layout the catalogs already use.
Edited lines change, and so does anything outside that layout: blank lines and
comments are dropped, and double-quoted strings become single-quoted.

`check` lists missing, empty, and invalid entries and exits with a nonzero
status when it finds any. Without a locale it checks every locale.

## Built-in and organizer-defined names

User role values, stored role names, permissions, and role assignments stay in
English. `UserRole::getLabel()` and `Role::display_name` translate the displayed
name only. A role name that is not a `UserRole` value is shown as stored.

Submission file types, contributor roles, and tracks have a nullable
`builtin_code` column. The records Leconfe creates for a new conference carry a
code: the Abstract, Full Paper, Poster, and Other file types, the Author and
Translator contributor roles, and the General Track.

`translated('name')` (`translated('title')` for tracks) returns, in order:

1. The organizer's translation for the requested language, if one is stored.
2. `general.<builtin_code>` from the catalog, while the stored name still equals
   the built-in English name listed in the model's `builtinNames()`.
3. The stored name, through the usual content-language fallback.

A renamed built-in, a custom record, and a record without a code are organizer
text, so they skip step 2. Edit forms show the stored text, and a change of
interface language never writes to the database.

A populate action skips a code that already exists, so running it again does not
recreate a renamed built-in that has a code. A record without a code is matched
by its name instead (a track by its title and abbreviation): if it still has the
built-in English name, the action reuses it and leaves it without a code. If it was renamed, the action creates a
new built-in record next to it. The citation author and translator roles are
kept unless they point to roles of another conference, as after a clone. In that
case they are reset to the conference's own Author and Translator roles.

`SubmissionFileType::builtinNames()` also lists Research Instrument, Research
Material, Research Result, Transcripts, Data Analysis, Data Set, and Source Text.
Leconfe does not create these types. The codes exist so that existing records
with these names can be mapped.

## Map records in an existing installation

The migration adds the nullable `builtin_code` columns and a unique index per
conference or scheduled conference. It does not change, delete, or classify
existing records, so older conferences keep showing their stored names.

To have an existing record follow the interface language, map it to a code:

1. Look up the record IDs and names in the conference or scheduled conference.
2. Preview the mapping with `--dry-run`:

   ```sh
   php artisan translations:map-builtins --conference=2 --scheduled-conference=7 \
     --file-type=12:research_instrument --track=3:general_track \
     --author-role=5:translator --dry-run
   ```

3. Run the same command without `--dry-run`.
4. Clear the application and model caches.

Each mapping is `record-ID:code`. The command needs `--conference` for
contributor roles and `--scheduled-conference` for file types and tracks. It
rejects the whole run if a record is outside that scope, if its stored name is
not the built-in English name for the code, or if the code is already used by
another record in the scope. The command writes `builtin_code` only, inside one
transaction, and running it twice has the same result as running it once. Leave
records with custom or ambiguous names unmapped.
