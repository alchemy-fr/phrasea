# Databox service

Databox is the very core service in Phraseanet.
It handles the DAM data.

This service aims to protect the database with an API envelope.

## Collection search

Given the following entries;

Coll #1 EN: Apple (non translated)
indexed in ES as:
```
{
  "_": "Apple",
  "en": "Apple"
}
```
Coll #2 FR: Légumes (traduit en EN: Vegetables)
indexed in ES as:
```
{
  "_": "Légumes",
  "fr": "Légumes"
  "en": "Vegetables"
}
```

Elasticsearch query: "collection:Appl"
 SHOULD:
     (title[fr]=Appl)_boost=100,
     (title[_]=Appl) _boost=50,
     (title=Appl) _boost=1 (? try in another language)


## Metadata

__All__ metadata (except binary blobs) are extracted from file after upload.
They are saved _as is_ in json in db `file.metadata`.

Each of 21324 known metadata is identified by a unique `TagGroup:TagName`, e.g.
- `ExifIFD:CreateDate`
- `GPS:GPSLatitude`
- `IPTC:Keywords`
- ...


## Initial attribute value(s)
### _Covers Phraseanet "read metadata"_

The initial value(s) of an attribute is defined by its `attribute-definition: Initial Values All` setting.

The setting is (for now) expressed as `json`, allowing to specify 2 "types" of source value:

- simple unique `metadata` source:
```json
{
    "type": "metadata",
    "value": "IPTC:Keywords"
}
```

- Computation of text, metadata, tests, math, ... using the twig `templating` language.

One can access to file properties, including metadata tags _via_ the `file` object, or also acces to `asset` properties:
```json
{
    "type": "template",
    "value": "{{ file.filename }} (size={{ file.size }})"
}
```
```json
{
    "type": "template",
    "value": "Copyright: {{ file.metadata('IPTC:Credit').value }} ; Phrasea OwnerId: {{ asset.OwnerId }}"
}
```
Fetch the first available "CreationDate" from multiple possible tags:
```json
{
    "type": "template",
    "value": "{{ file.metadata('ExifIFD:CreateDate').value ?? file.metadata('IPTC:DateCreated').value ?? file.metadata('IPTC:DigitalCreationDate').value }}"
}
```
To populate a `multi-values` attribute using twig template, one must generate **one item per line**.

Here is how to populate uppercased keywords:
```json
{
    "type": "template",
    "value": "{% for kw in file.metadata('IPTC:Keywords').values %}{{ kw|upper }}\n{% endfor %}"
}
```

### Short list of commonly used tags:
```text
Composite:GPSPosition
ExifIFD:CreateDate
ExifIFD:DateTimeOriginal
ExifIFD:ImageUniqueID
IPTC:By-line
IPTC:By-lineTitle
IPTC:Caption-Abstract
IPTC:CopyrightNotice
IPTC:City
IPTC:Country-PrimaryLocationName
IPTC:CopyrightNotice
IPTC:Credit
IPTC:ImageOrientation
IPTC:Keywords
JPEG:Comment
PDF:Author
PDF:Keywords
PDF:PageCount
PDF:Subject
PDF:Title
XMP-dc:Date
XMP-dc:Description
XMP-dc:Language
XMP-dc:Publisher
XMP-dc:Rights
XMP-dc:Source
XMP-dc:Subject
XMP-dc:Title
XMP-exif:FlashFired
XMP-iptcExt:PersonInImage
XMP-xmp:Author
XMP-xmp:Keywords
XMP-xmp:Rating
XMP-xmp:Title
```
for complete list, refer to: `databox/api/var/cache/phpexiftool/Helper.php`

:warning: With `initial values` setting, attributes values are created only once, - when a file is added -.
Changes from dynamic / editable sources (e.g. refering to `asset.title`) will **not** update the related
attributes.

## Fallback attribute value(s) !! wip do not use !!
A fallback value defines the "virtual" value of an attribute if this attribute is **not set** for the asset.
This "virtual" computed value is:
- searchable - if the attribute definition allows it -
- readable (returned by api)
- displayed in databox applications
- not editable

The fallback value for an attribute is defined **by locale (lng)**, using the same syntax as `initial values`.

The value is computed on asset indexation (thus after editing) and on display.

e.g. fallback for unset "Credit", in EN and FR
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

Because the fallback "formula" can refer to other (real) attributes values, it is possible to generate attributes
that depends on other attributes.

e.g.: fill the "warning" attribute if the Title or Credit is not set
```json
{
    "type": "template",
    "value": "{% if attr('Title') is empty %}Missing Title\n{% endif %}{% if attr('Credit') is empty %}Missing Credit\n{% endif %}"
}
```







---
## old doc to be cleaned
- Title
- Length : [00:31:01] [X] Overrive value


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
