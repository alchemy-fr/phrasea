---
id: 009-empecher-tri-id
title: Empêcher les tris sur _id et sur les champs non triables
status: executed
finished: 2026-10-21 02:57
started: 2026-10-21 02:16
run_base: 10edfb30fac64cee4c7d3aa5628c73e47957b4db
created: 2026-10-21 01:57
branch: next
depends_on: []
---

# Empêcher les tris sur `_id` et sur les champs non triables (built-ins et définitions)

## Contexte et objectif

Elasticsearch 8 interdit le tri sur `_id` (`indices.id_field_data.enabled` vaut `false` par défaut) :
`order[@id]=asc` sur `/assets` part en erreur ES (« Fielddata access on the _id field is disallowed »)
au lieu d'un 400 propre.

Origine : `databox/api/src/Elasticsearch/BuiltInAttribute/IdBuiltInAttribute.php` a `getName()` =
`'_id'` et hérite de `AbstractBuiltInAttribute::isSortable()` = `true`. Trois trous aggravent le problème :

1. **API, built-ins** : `AssetSearch::applySort()` (`databox/api/src/Elasticsearch/AssetSearch.php`,
   ~l. 603) ne consulte jamais `isSortable()` : même `@rendition`, `@directCollection`, `@similar`
   (qui renvoient `false`) sont transmis à ES.
2. **API, définitions** : `applySort()` trie sur n'importe quelle clé `<slug>_<type>_<s|m>` sans
   regarder `AttributeDefinition::isSortable()` (colonne `sortable`, défaut `false`).
3. **Client** : `SortEditor` (`databox/client/src/features/search/sort/SortByButton.tsx`, ~l. 104)
   filtre `d.sortable || d.builtIn`, donc propose **tous** les built-ins au tri, y compris ceux que
   l'API déclare non triables (le flag `sortable` des built-ins vient de
   `BuiltInAttributeProvider::getBuiltInAttributes()` → `$field->isSortable()`).

Objectif : `@id` n'est plus triable ; l'API refuse proprement tout tri sur un built-in non triable ou
sur une définition non triable ; le client ne les propose plus.

## Décisions prises

- **Empêcher**, pas rediriger : on ne remappe pas `@id` vers un autre champ triable.
- L'API respecte `isSortable()` des **built-ins** et des **`AttributeDefinition`** (choix explicite de
  l'utilisateur).
- Requête directe sur un champ non triable → **400** (`BadRequestHttpException`).
  Tri stocké dans une saved search (mode `lenient`, `OPT_SAVED_SORT`) → champ **ignoré
  silencieusement**, comme un champ disparu (une saved search existante triée par `@id` ou par une
  définition devenue non triable doit rester utilisable).
- Règle pour une clé de définition (`city_text_s`) : une même clé (slug + type + multiple) peut
  correspondre à plusieurs définitions, dans plusieurs workspaces, qui partagent le champ ES
  `attrs._.<clé>`. La clé est **triable si au moins une** définition qui la produit a
  `sortable = true` ; **refusée si toutes** sont `sortable = false`. Une clé ne correspondant à
  **aucune** définition reste acceptée (comportement actuel, couvert par
  `AssetSearchTest::testSortOnNeverDefinedAttributeSlug`). Pas de filtrage ACL ici : la sortabilité
  est une propriété du schéma, la visibilité des valeurs reste gérée par l'ACL de la requête.
- Conséquence assumée : les définitions existantes en base avec `sortable = false` (valeur par défaut)
  ne sont plus triables par l'API. Le client ne les proposait déjà pas (`d.sortable`).
- Le tie-breaker final (`microseconds DESC`, `sequence ASC`) reste tel quel. Les autres usages de
  `'_id'` dans `src/` sont des filtres `Terms`/`Term` ou des `update_by_query`, non concernés.

## Fichiers concernés

- `databox/api/src/Elasticsearch/BuiltInAttribute/IdBuiltInAttribute.php` : ajouter
  `#[\Override] public function isSortable(): bool { return false; }` (même forme que
  `RenditionBuiltInAttribute` / `DirectCollectionBuiltInAttribute`).
- `databox/api/src/Repository/Core/AttributeDefinitionRepository.php` : nouvelle méthode
  `getSortabilityRows(): iterable` (ou nom équivalent) qui sélectionne `t.slug`, `t.type`,
  `t.multiple`, `t.sortable` sur toutes les définitions (même style de QueryBuilder scalaire que
  `getAllSearchableAttributes()`, `toIterable()`).
- `databox/api/src/Elasticsearch/AttributeSearch.php` : nouvelle méthode publique
  `isSortableAttributeKey(string $key): ?bool` — `null` si aucune définition ne produit cette clé,
  sinon `true` si l'une est triable. Index `clé → bool` construit une fois via
  `$this->cache->get('sortable_keys', …)` (même `TemporaryCache` que `sa_<userId>` / `sa_all`), clé
  calculée par `$this->fieldNameResolver->getFieldName($slug, $type, $multiple)`.
- `databox/api/src/Elasticsearch/AssetSearch.php`, `applySort()` :
  - juste après `$builtInAttribute = $this->builtInAttributeRegistry->getBuiltInAttribute($field);`,
    avant la branche `CustomSortBuiltInAttributeInterface` :
    ```php
    if (null !== $builtInAttribute && !$builtInAttribute->isSortable()) {
        if ($lenient) {
            continue;
        }
        throw new BadRequestHttpException(sprintf('Attribute "%s" is not sortable', $field));
    }
    ```
    (`PositionBuiltInAttribute` hérite de `isSortable() = true` : inchangé.)
  - après la résolution `getESFieldInfo()` réussie, pour un champ non built-in
    (`!$esFieldInfo->builtIn`) : `if (false === $this->attributeSearch->isSortableAttributeKey($field))`
    → même traitement (continue en lenient, sinon 400 avec le même message).
- `databox/client/src/features/search/sort/SortByButton.tsx` : le filtre devient `d => d.sortable`.
  Vérifier que `@createdAt`, `@score`, `@position`, `@editedAt`… gardent `sortable: true` dans la
  réponse de l'endpoint des built-ins (`getBuiltInAttributes()`), et que le fallback des lignes
  actives (`s.a === BuiltInAttribute.Score || s.a === BuiltInAttribute.CreatedAt`, ~l. 120) reste
  cohérent.
- `databox/api/tests/functional/DataboxTestTrait.php`, `createAttributeDefinition()` : ajouter
  `$definition->setSortable($options['sortable'] ?? false);` (défaut aligné sur l'entité, pour ne pas
  changer les snapshots).
- Tests existants qui trient sur une définition : passer `'sortable' => true` à la définition
  concernée. Au moins `tests/functional/Search/AssetSearchTest.php`
  (`testSortOnDefinitionWithoutAnyValue`, `testSortOnMultipleDefinitionWithoutAnyValue`,
  `testSortOnDefinitionWithValuesStillOrders`) et `tests/functional/Search/AssetSavedSearchOptionTest.php`
  (définition `CITY` = `city_text_s`, créée ~l. 43). Chercher les autres :
  `grep -rn "'order'\|sortBy\|order\[" databox/api/tests/functional`.
- Snapshots JSON-LD (`databox/api/tests/functional/Api/Snapshot/__snapshots__/`) : un snapshot qui
  liste les built-ins verra `@id` passer à `sortable: false` → régénérer, avec les
  `API_*_OWNER_PROPERTY_REQUIRED_ROLE` vides dans l'environnement (sinon `owner` disparaît).

## Étapes

1. `IdBuiltInAttribute::isSortable()` → `false`.
2. Repository + `AttributeSearch::isSortableAttributeKey()`.
3. Gardes dans `AssetSearch::applySort()` (built-in puis définition ; 400 / ignoré en lenient).
4. `DataboxTestTrait` : option `sortable` ; ajouter `'sortable' => true` aux tests existants qui
   trient sur une définition.
5. Nouveaux tests API :
   - `AssetSearchTest::testSortOnNonSortableBuiltInIsRejected` : `order[@id]=asc` → 400 ; idem
     `order[@rendition]=asc`.
   - `AssetSearchTest::testSortOnNonSortableDefinitionIsRejected` : définition `City` sans
     `sortable` → `order[city_text_s]=asc` → 400 ; puis une 2ᵉ définition de même clé dans un autre
     workspace avec `'sortable' => true` → 200 (règle « au moins une »).
   - `AssetSavedSearchOptionTest::testStoredSortOnNonSortableFieldIsIgnored` : saved search avec
     `sortBy: [['a' => '@id', 'w' => 1]]` puis avec une définition non triable → 200, résultats
     attendus, sur le modèle de `testStoredSortOnRemovedFieldIsIgnored` (l. 194).
6. Client : filtre `d.sortable` dans `SortEditor`. Adapter un test vitest de `SortByButton` s'il en
   existe un (sinon ne pas en créer juste pour ça).
7. Régénérer les snapshots si nécessaire.

## Vérification

Exige la stack docker (base de test, Elasticsearch) — ne pas lancer de `php`/`pnpm` sur l'hôte.

```bash
dc run --rm -e APP_ENV=test databox-api-php su app -c "composer run pre-phpunit"
dc run --rm -e APP_ENV=test databox-api-php su app -c "php -d memory_limit=1024M bin/phpunit --extension 'Alchemy\ApiTest\PHPUnit\CompactResultExtension' tests/functional/Search"
dc run --rm -e APP_ENV=test databox-api-php su app -c "php -d memory_limit=1024M bin/phpunit --extension 'Alchemy\ApiTest\PHPUnit\CompactResultExtension' tests/functional/Api/Snapshot"
dc run --rm databox-api-php su app -c "composer phpstan"
dc run --rm databox-api-php su app -c "composer cs"
```

Puis la suite complète, pour attraper un test qui trie sur une définition sans `sortable` :
`dc run --rm databox-api-php su app -c "composer phpunit:compact" | tail -50`.

Client (conteneur `dev`, cf. piège TTY : passer par `--entrypoint sh dev -c "su app -c '…'"`) :
`pnpm --filter databox-client lint` et `pnpm --filter databox-client test`, plus tsc en filtrant le
bruit préexistant (~15 erreurs de doubles `@types/react`).

Manuel (après intégration, stack partagée) : le menu « Trier par » du client databox ne propose plus
l'ID, ni Rendition / Collection directe / Similaire, ni les définitions non triables ;
`/assets?order[@id]=asc` → 400 ; une saved search triée par `@id` s'ouvre toujours.

Les suites fonctionnelles partagent Redis/ES : si un autre plan tourne en parallèle, risque de
« populate already running » (clé Redis `es_populate_*`) — sérialiser les runs PHPUnit databox.

## Hors périmètre

- Remappage de `@id` vers un champ triable.
- Migration de données pour passer des définitions existantes à `sortable = true`.
- Les autres search (Collection, Tag, Basket, AttributeEntity…) : leurs tris viennent de
  `SortFilter` sur des propriétés fixes, aucun ne cible `_id`.

## Interactions avec la file

- `008-run-automation-from-saved-search-menu` (pending) : touche `databox/client/src/features/saved-searches/*`
  et `databox/client/src/i18n/locales/{en,fr}.json` ; aucun fichier commun. Pas de dépendance.
- Le worktree principal porte du travail non commité (notifier-bundle, `BasketSearch.php`,
  `BasketSearchTest.php`…) : ne pas y toucher, ne pas utiliser `git checkout --`.
