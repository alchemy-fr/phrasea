# Collection search

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

