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
