# Metadata

__All__ metadata (except binary blobs) are extracted from the file after upload.
They are saved _as is_ in json in db `file_metadata.metadata`, which only ever mirrors what
was read from the file — see [Reading and writing metadata](#reading-and-writing-metadata).

Each of the 21,324 known metadata fields is uniquely identified by a `TagGroup:TagName` pair, such as:
- `ExifIFD:CreateDate`
- `GPS:GPSLatitude`
- `IPTC:Keywords`

## How Metadata Is Handled
- After a file is uploaded, all available metadata (except binary blobs) is extracted.
- The extracted metadata is saved as-is in the database, grouped by tag group then tag name.
- **A tag always holds a _list_ of values**, even when it carries a single one:

```json
{
  "IPTC": {
    "Keywords": ["dog", "cat", "bird"],
    "City": ["Paris"]
  },
  "ExifTool": {
    "ExifToolVersion": ["13.59"]
  }
}
```

## Reading and writing metadata

The metadata of a file live in **two** tables, both in the shape above:

| Table | Content |
|---|---|
| `file_metadata` | exactly what was read from the file. Immutable: the application never writes individual tags into it, it is only replaced wholesale when the file is read again. |
| `file_overridden_metadata` | the tags the application set itself (today: the document unique id computed by the `doc_unique_id` file analyzer). |

Reading resolves the two — an overridden tag wins over the one read from the file — so
`File::getMetadata()`, `getMetadataNameValues()` and the Twig `file` accessor never have to
care about the distinction. `File::getReadMetadata()` and `File::getOverriddenMetadata()`
expose each side on its own, and `File::setMetadataValue()` always writes to the overrides.

:warning: **Only the overrides are ever written back into a file**, and only into the source
file itself. When a rendition is built or an asset is exported (and the rendition definition has
`writeMetadata` enabled), the embedded bag is:

1. the source file's overridden metadata — **only when the exported file _is_ the source**
   (a `pick source` rendition). A transcoded rendition is a derived file: the metadata the
   application set on the original do not belong in it;
2. then the attribute values (see [`writeMetadata`](#writing-metadata-back-writemetadata)),
   which win over them;
3. then the rendition definition's hardcoded metadata, which win over everything.

The metadata read from the source file are never re-written: they already are in the file, and
the application is not their source of truth.

### Choosing which renditions an attribute reaches

By default an attribute mapped through `writeMetadata` is embedded into every rendition whose
definition has `writeMetadata` enabled. `AttributeDefinition::$writeMetadataRenditions`
restricts it to a subset:

| Scope | Effect |
|---|---|
| empty (default) | written into every rendition, dynamic renditions included |
| one or more rendition definitions | written into those only |

A **dynamic rendition has no definition**, so only unscoped attributes reach it.

Three levels, one job each:

| Level | Field | Question |
|---|---|---|
| Rendition | `RenditionDefinition::$writeMetadata` (bool) | does this rendition carry metadata at all? |
| Attribute | `AttributeDefinition::$writeMetadata` (tags) | which tags does this attribute map to? |
| Link | `AttributeDefinition::$writeMetadataRenditions` | which renditions is it allowed into? |

The link is a real many-to-many, so it is editable from either side and survives the deletion
of a rendition definition. Workspace templates carry it by rendition **name**.

## Attribute definition fields

Four fields of an attribute definition connect attributes to file metadata:

| Field | Type | Purpose |
|---|---|---|
| `readFromMetadata` | ordered list of `Group:Tag` names | Initialize the attribute directly from a file metadata tag (first tag found wins). |
| `initialValues` | Twig template, per locale | Initialize the attribute from a computed value, when the asset is created. |
| `fallback` | Twig template, per locale | Virtual (non-persisted) value used when the attribute has no value. |
| `writeMetadata` | list of `Group:Tag` names | Tags into which the attribute value is written when the asset (or its rendition) is exported. |

:warning: The `initialValues` / `fallback` fields **used to be** JSON objects of the shape
`{"type": "metadata"\|"template", "value": "..."}`. That wrapper is gone: the field is now
**directly the Twig template**, and reading a raw metadata tag has moved to its own
`readFromMetadata` field.

| Before | Now |
|---|---|
| `{"type": "metadata", "value": "IPTC:Keywords"}` | `readFromMetadata: ["IPTC:Keywords"]` |
| `{"type": "template", "value": "{{ file.filename }}"}` | `initialValues: "{{ file.filename }}"` |

### Locales

`initialValues` and `fallback` are stored as a map `locale => template`. The special
locale `_` (`AttributeInterface::NO_LOCALE`) means "all locales / not translated":

```json
{
    "_": "{{ file.filename }}"
}
```
```json
{
    "en": "(c) {{ file.getMetadata('XMP-dc:Creator').value }}. All rights reserved",
    "fr": "(c) {{ file.getMetadata('XMP-dc:Creator').value }}. Tous droits réservés"
}
```

`readFromMetadata` is **not** locale-aware: metadata is not localized, so the resulting
attribute is always created under the `_` locale.

## Initial Attribute Values

### 1. Directly from metadata: `readFromMetadata`

A flat, **ordered** list of metadata tag names. The first tag actually present in the file
metadata is used; the following ones are not evaluated.

```json
["XMP-iptcCore:CreatorCity", "IPTC:City"]
```

How the tag values are mapped depends on the attribute:

- **multi-value attribute**: one attribute value per metadata value (`dog`, `cat`, `bird`).
- **mono-value attribute**: the metadata values are joined with a **newline**
  (`StringableMetadataValue::MULTIVALUE_SEPARATOR`) into a single attribute value.

Empty / blank values are discarded.

### 2. Computed value: `initialValues` (Twig template)

The value is a Twig template, resolved against the following context:

| Variable | Description |
|---|---|
| `file` | the source `File` of the asset, wrapped so it never crashes when the asset has no file (all accessors then return `null`) |
| `asset` | the `Asset` entity |
| `attr` | the other attributes of the asset, accessed by definition slug (see [Referencing other attributes](#referencing-other-attributes)) |

Access file properties:
```
{{ file.filename }} (size={{ file.size }})
```

Available `file` accessors: `filename`, `originalName`, `extension`, `extensionWithDot`,
`type`, `size`, `checksum`, `docUniqueId`, `path`, `pathPublic`, `storage`, `analysis`,
`metadataValues`.

Combine metadata and asset properties:
```
Copyright: {{ file.getMetadata('IPTC:Credit').value }} ; Phrasea OwnerId: {{ asset.ownerId }}
```

#### Reading metadata from a template

`file.getMetadata('Group:Tag')` (or the shorter `file.metadata('Group:Tag')`) returns a
`StringableMetadataValue`, or `null` if the tag is absent:

| Expression | Result for `IPTC:Keywords = [dog, cat, bird]` |
|---|---|
| `file.getMetadata('IPTC:Keywords').values` | `['dog', 'cat', 'bird']` (array) |
| `file.getMetadata('IPTC:Keywords').value` | `"dog\ncat\nbird"` (values joined by newline) |
| `{{ file.getMetadata('IPTC:Keywords') }}` | `dog\ncat\nbird` (the object is stringable) |

Fetch the first available creation date from multiple tags:
```
{{ file.getMetadata('ExifIFD:CreateDate').value
   ?? file.getMetadata('IPTC:DateCreated').value
   ?? file.getMetadata('IPTC:DigitalCreationDate').value }}
```

#### Multi-value attributes

**One value per line**: the rendered template is split on newlines, and each non-blank line
becomes one attribute value.

Since `StringableMetadataValue` already joins its values with a newline, the simplest form is:
```
{{ file.getMetadata('IPTC:Keywords') }}
```

which is equivalent to:
```
{{ file.getMetadata('IPTC:Keywords').values | join("\n") }}
```

To uppercase each keyword:
```
{% for kw in file.getMetadata('IPTC:Keywords').values %}{{ kw|upper }}
{% endfor %}
```

Conversely, to collapse several values into a **mono-value** attribute, join them explicitly:
```
{{ file.getMetadata('IPTC:Keywords').values | join(" ; ") }}
```

### Combining both

`readFromMetadata` and `initialValues` are independent and are both applied when both are set.
Use `readFromMetadata` for a plain "copy this tag" mapping, and `initialValues` as soon as the
value has to be computed, formatted or picked among several sources.

### Commonly Used Metadata Tags
A few frequently used tags include:
- `Composite:GPSPosition`
- `ExifIFD:CreateDate`
- `IPTC:Keywords`
- `PDF:Author`
- `XMP-dc:Title`
- `XMP-xmp:Keywords`

For a more comprehensive list, see the helper file in your codebase.

:warning: **Note:** Initial attribute values are set only once, when a file is first added. If
the source (such as `asset.title`) changes later, the attribute will not update automatically.
Use the `recompute_initial` operation on the attribute definition to replay them
(and `store_fallback` to persist a fallback as a real attribute).

More worked examples (with the file metadata and the resulting attributes) are generated from
`databox/api/src/Documentation/InitialAttributeValuesResolverData.yaml` and published under
_Attribute Initial Values_.

## Fallback Attribute Values (Experimental)

A fallback value defines a virtual value for an attribute if it is not set for an asset. This value is:
- Searchable (if allowed by the attribute definition)
- Readable (returned by the API)
- Displayed in Databox applications
- Not editable

Fallback values are defined per locale, using **the same syntax and the same Twig context as
`initialValues`** (a bare template, not a `{type, value}` object). The value is computed during
asset indexing and display.

Example fallback for an unset "Credit" attribute in English and French:
```json
{
    "en": "(c) CoolMedia Agency (unknown author)",
    "fr": "(c) Agence CoolMedia (auteur inconnu)"
}
```

### Referencing other attributes

Fallback (and initial value) templates can reference other attributes of the asset through
`attr.<definition slug>`. If the referenced attribute has no value yet, its own
template is resolved on the fly; circular references are detected and raise an error.

`attr.<slug>` returns a string for a mono-value definition, and an array of strings for a
multi-value one.

For example, to fill a "warning" attribute if the Title or Credit is missing:
```
{% if attr.title is empty %}Missing Title
{% endif %}{% if attr.credit is empty %}Missing Credit
{% endif %}
```

## Writing metadata back: `writeMetadata`

A list of metadata tag names into which the attribute value is written when the asset — or one
of its renditions — is exported:

```json
["IPTC:Keywords", "XMP-dc:Subject"]
```

The tags are embedded into the exported file copy; the stored original is never modified.
Use `writeMetadataRenditions` to restrict which renditions carry them — see
[Choosing which renditions an attribute reaches](#choosing-which-renditions-an-attribute-reaches).
