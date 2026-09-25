# Leconfe translations

The English files define the application keys. Every configured locale has the same
three PHP files and preserves the English placeholders and HTML tags.
The catalog follows the 77 named locale codes in the
[OJS 3.5 locale directory](https://github.com/pkp/ojs/tree/stable-3_5_0/locale);
the `und` code means an undetermined language and is excluded.

The original French contribution and the existing Arabic, Indonesian, Russian,
Albanian, and Uzbek translations were retained. Most newly added locales were
machine translated from Leconfe's English text. These translations need review by
native speakers, especially publication terminology and validation messages.

Some OJS locale codes have no sufficiently complete translation source for Leconfe.
They remain selectable so contributors can work on them, but their names in the
language settings say `draft: incomplete`:

- `cnr` uses Serbian Latin as an approximation of Montenegrin.
- `rue` uses Ukrainian as an approximation of Rusyn.
- `dsb` and `hsb` have machine-translated Leconfe text that needs native review.
- `kab` and `sid` currently retain English Leconfe text.
- `an` has an Aragonese draft with some untranslated Spanish terms.
- `se` has a Northern Sami draft with some untranslated Finnish terms.

Regional variants currently share their parent language's text where a distinct
translation was unavailable: `bs_Latn` uses Bosnian, `es_MX` uses Spanish,
`fr_CA` uses the contributed French, and `uz_Latn` uses the existing Uzbek Latin.
`sr_Latn` is transliterated from Serbian Cyrillic. Aragonese (`an`) and Norwegian
Nynorsk (`nn`) were translated via a second machine translation step and require
extra review. Northern Sami (`se`) was translated via Finnish and Apertium.
For regional variants with a matching Filament translation, the small files in
`lang/vendor` load the parent locale from the installed Filament package. Other
third-party text can fall back to English when that package has no translation.

Administrators can select available languages under the conference language
settings. New conferences still start with English enabled; this does not change
an existing conference's chosen languages.
