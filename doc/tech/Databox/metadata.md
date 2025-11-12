# Metadata

__All__ metadata (except binary blobs) are extracted from file after upload.
They are saved _as is_ in json in db `file.metadata`.

Each of the 21,324 known metadata fields is uniquely identified by a `TagGroup:TagName` pair, such as:
- `ExifIFD:CreateDate`
- `GPS:GPSLatitude`
- `IPTC:Keywords`

## How Metadata Is Handled
- After a file is uploaded, all available metadata (except binary blobs) is extracted.
- The extracted metadata is saved as-is in the database.
- Each metadata tag is stored under its unique identifier.

## Initial Attribute Values

The initial value(s) of an attribute are defined by the `attribute-definition: Initial Values All` setting. This setting is expressed in JSON and allows two main types of value sources:

### 1. Direct Metadata Source
A simple, unique metadata tag can be used as the source:
```json
{
    "type": "metadata",
    "value": "IPTC:Keywords"
}
```

### 2. Computed Value Using Twig Templates
You can use the Twig templating language to compute values based on file properties, metadata, or asset properties. For example:

Access file properties:
```json
{
    "type": "template",
    "value": "{{ file.filename }} (size={{ file.size }})"
}
```

Combine metadata and asset properties:
```json
{
    "type": "template",
    "value": "Copyright: {{ file.metadata('IPTC:Credit').value }} ; Phrasea OwnerId: {{ asset.OwnerId }}"
}
```

Fetch the first available creation date from multiple tags:
```json
{
    "type": "template",
    "value": "{{ file.metadata('ExifIFD:CreateDate').value ?? file.metadata('IPTC:DateCreated').value ?? file.metadata('IPTC:DigitalCreationDate').value }}"
}
```

To populate a multi-value attribute using a Twig template, generate one item per line. For example, to create a list of uppercased keywords:
```json
{
    "type": "template",
    "value": "{% for kw in file.metadata('IPTC:Keywords').values %}{{ kw|upper }}\n{% endfor %}"
}
```

### Commonly Used Metadata Tags
A few frequently used tags include:
- `Composite:GPSPosition`
- `ExifIFD:CreateDate`
- `IPTC:Keywords`
- `PDF:Author`
- `XMP-dc:Title`
- `XMP-xmp:Keywords`

For a more comprehensive list, see the helper file in your codebase.

:warning: **Note:** Initial attribute values are set only once, when a file is first added. If the source (such as `asset.title`) changes later, the attribute will not update automatically.

## Fallback Attribute Values (Experimental)
A fallback value defines a virtual value for an attribute if it is not set for an asset. This value is:
- Searchable (if allowed by the attribute definition)
- Readable (returned by the API)
- Displayed in Databox applications
- Not editable

Fallback values are defined per locale, using the same syntax as initial values. The value is computed during asset indexing and display.

Example fallback for an unset "Credit" attribute in English and French:
```json
{
    "type": "template",
    "value": "(c) CoolMedia Agency (unknown author)"
}
```
```json
{
    "type": "template",
    "value": "(c) Agence CoolMedia (auteur inconnu)"
}
```

Fallback formulas can reference other attribute values, allowing for dynamic warnings or computed fields. For example, to fill a "warning" attribute if the Title or Credit is missing:
```json
{
    "type": "template",
    "value": "{% if attr('Title') is empty %}Missing Title\n{% endif %}{% if attr('Credit') is empty %}Missing Credit\n{% endif %}"
}
```

---

## Example: Metadata Mapping and File Properties

- **Title**: Maps to `file.filename` (editable)
- **File Size**: Maps to `file.size` (not editable)
- **Movie Duration**: Maps to `file.length` (editable, keep on new version)

**File object properties:**
- `filename`
- `size`
- `length`
- `width`
- `height`

**Example metadata:**
```json
{
  "Title": "Mon fichier"
}
```

**Example file property:**
- `tech.length`

---

## Legacy Notes (To Be Cleaned)
- Title
- Length : [00:31:01] [X] Override value

METADATA_MAPPING:
Title -> file.filename (editable=true)
Taille du fichier -> file.size (editable=false)
Durée du film -> file.length (editable=true, keep_on_new_version=true)
Durée réelle du film (editable=true, keep_on_new_version=true)

METADATA:
Title: "Mon fichier"

FILE:
- filename
- size
- length
- width
- height

tech.length

