---
id: 007-saved-search-option-read-check
title: Contrôle READ et fusion de l'option savedSearch de la recherche d'assets
status: executed
finished: 2026-10-19 14:57
started: 2026-10-19 11:52
run_base: bd9d98b44e664cf3b0e6b4dea62d54e6d4d3131d
created: 2026-10-19 11:43
branch: next
depends_on: []
---

# Contrôle READ et fusion de l'option `savedSearch` de la recherche d'assets

## Contexte

`GET /assets` et `GET /assets/geo-clusters` acceptent un paramètre `savedSearch` (déclaré dans
`databox/api/src/Entity/Core/Asset.php`, ~l.171 pour geo-clusters et ~l.328 pour la GetCollection).
`AssetSearch::buildQuery()` (`databox/api/src/Elasticsearch/AssetSearch.php` l.244-248) charge la
`SavedSearch` puis fait `$options = array_merge($options, $savedSearch->getData())`. Trois défauts :

1. **Pas de contrôle `SavedSearchVoter::READ`** : n'importe qui connaissant l'id d'une saved search
   `Secret` exécute sa requête stockée (résultats toujours limités par sa propre ACL d'assets, mais
   la requête et les conditions fuitent).
2. **Le tri stocké est ignoré** : le client stocke `data.sortBy` (`[{a: string, w: 0|1, g?: bool}]`,
   cf. `databox/client/src/features/search/SearchProvider.tsx` `loadSavedSearch` et la conversion de
   `databox/client/src/features/search/ResultProvider.tsx` l.88-93 : `order[a] = w === 1 ? 'desc' : 'asc'`,
   `group` = les `a` où `g`, seul le premier est gardé), alors que `applySort()` ne lit que `$options['order']`.
   La description du paramètre (l.328) annonce pourtant « query, conditions and order are merged in ».
3. **`array_merge` fait gagner les données stockées** sur la requête, et fusionne tout le JSON opaque
   `data` dans les options (il peut porter `userId`, `groupIds`, `ids`, `workspaces`, `collection`,
   `limit`… ; `userId`/`groupIds` sont lus par `DeletedBuiltInAttribute` / `AssetStatusBuiltInAttribute`
   quand une condition `@deleted`/`@status` est construite via `buildConditionQuery(..., $options)`).

Note : le client actuel ne passe jamais `savedSearch` (aucun appelant de `<ResultProvider savedSearch=…>`),
il recopie query/conditions/sortBy dans son état. Le paramètre ne sert qu'aux consommateurs d'API et
servira au plan 003 (automations, `AssetSearch::iterateIds()` réutilise `buildQuery()` sous le token
emprunté du propriétaire).

## Décisions prises avec l'utilisateur

- **READ** : `denyAccessUnlessGranted(AbstractVoter::READ, $savedSearch)` (trait `SecurityAwareTrait`,
  déjà disponible dans `AbstractSearch`) → `AccessDeniedException` → **403** authentifié, **401** anonyme,
  comme `GET /saved-searches/{id}` (cf. `testReadMatrix` de `SavedSearchApiTest`) et les autres providers
  (`denyAccessUnlessGranted` partout dans `src/Api/Provider`). Id inconnu : 404 inchangé.
- **Tri stocké : converti en `order`** (+ `group`) quand la requête ne fournit pas `order` ; un `order`
  (resp. `group`) explicite de la requête prime.
- **Précédence « base + raffinement »** : la saved search est la base ; une `query` non vide (après `trim`)
  de la requête remplace la query stockée ; les `conditions` de la requête sont **ajoutées** aux
  conditions stockées (ET) — la requête peut restreindre, jamais élargir.
- **Liste blanche** : seules les clés `query`, `conditions`, `sortBy` de `data` sont prises en compte ;
  toute autre clé est ignorée.

## Fichiers concernés

- `databox/api/src/Elasticsearch/AssetSearch.php`
  - `buildQuery()` : remplacer le bloc l.244-248 par un appel à une méthode privée
    `applySavedSearch(array $options): array`.
  - `applySavedSearch()` : charge via `DoctrineUtil::findStrictByRepo(..., throw404: true)` (inchangé),
    `$this->denyAccessUnlessGranted(AbstractVoter::READ, $savedSearch)`, puis applique les règles
    ci-dessus à partir de `$data = $savedSearch->getData()` :
    - `query` : si `trim($options['query'] ?? '') === ''` et `is_string($data['query'] ?? null)` → `$options['query'] = $data['query']`.
    - `conditions` : `array_merge(array_values((array) ($data['conditions'] ?? [])), array_values((array) ($options['conditions'] ?? [])))`
      si le résultat n'est pas vide (les conditions stockées sont des objets `{id, query, inversed?, disabled?}`,
      celles de la requête des chaînes AQL ; la boucle de `buildQuery()` gère déjà les deux formes).
    - `sortBy` : si `!isset($options['order'])` et `is_array($data['sortBy'] ?? null)` et non vide → construire
      `order` (`[$s['a'] => (1 === ($s['w'] ?? 0)) ? 'desc' : 'asc']`, en ignorant les entrées sans `a` string)
      et, si `!isset($options['group'])`, `group = [premier a dont g est vrai]` (absent sinon). Marquer
      l'ordre comme venant de la saved search (ex. `$options[self::OPT_SAVED_SORT] = true`, constante privée).
    - Ne jamais copier d'autre clé de `data`.
  - `applySort()` : quand l'ordre vient de la saved search, un champ invalide/inconnu (attribut supprimé
    depuis l'enregistrement : `getESFieldInfo()` lève `InvalidArgumentException`) ou un sens invalide est
    **ignoré** au lieu de lever une 400 — sinon la saved search deviendrait inutilisable. L'ordre fourni par
    la requête garde sa validation stricte actuelle. Si tous les champs stockés sont ignorés, retomber sur le
    tri par défaut (`_score`/`createdAt`).
  - Imports : `AbstractVoter` est déjà importé.
- `databox/api/src/Entity/Core/Asset.php` : descriptions du paramètre `savedSearch` (l.~171 et l.~328) →
  refléter la règle, ex. « Saved search ID: its query (unless one is given), conditions (combined with the
  given ones) and order (unless one is given) apply ». Pour geo-clusters, pas de tri : « its query (unless
  one is given) and conditions (combined with the given ones) apply ».
- `databox/api/tests/functional/Search/AssetSavedSearchOptionTest.php` (nouveau, `extends AbstractSearchTestCase`,
  `use App\Tests\Functional\Api\UserSpace\UserSpaceTestTrait` pour `api()/apiJson()/assertStatus()` et les
  constantes `USER`/`OTHER`/`ADMIN`).
- `databox/api/tests/functional/Search/AssetInversedConditionSearchTest.php` : doit continuer à passer sans
  changement (saved search `Private` lue en anonyme : READ accordé, non-Secret).

## Étapes

1. Implémenter `applySavedSearch()` et l'assouplissement de `applySort()` dans `AssetSearch.php`.
2. Mettre à jour les deux descriptions de paramètre dans `Asset.php`.
3. Écrire `AssetSavedSearchOptionTest` (workspace public + assets publics nommés, saved searches
   persistées via l'EM comme dans `AssetInversedConditionSearchTest`, ACL via `grantUserOnObject()` de
   `tests/functional/DataboxTestTrait.php`) :
   - **Matrice READ** (`#[DataProvider]` public static) sur `GET /assets?savedSearch=<id>` : Secret/propriétaire 200,
     Secret/autre 403, Secret/anonyme 401, Secret/admin 200, Secret/autre avec ACL VIEW 200,
     Private/autre 200, Public/anonyme 200. Idem au moins un cas 403 sur `GET /assets/geo-clusters`
     (lire ses paramètres requis — `field`, `precision`… — dans `Asset.php` ~l.126).
   - Id inconnu → 404.
   - **Tri stocké** : `data.sortBy = [{a: '@createdAt' ou un attribut texte, w: 1}]` → ordre inversé constaté
     (s'inspirer de `assertSortedNames` / `testSortOnBuiltInField` dans `AssetSearchTest.php`) ;
     `order` explicite dans la requête → prime ; `sortBy` vers un attribut inexistant → 200 (ignoré), pas 400 ;
     `order` invalide dans la requête → toujours 400.
   - **Précédence** : query stockée appliquée si la requête n'en a pas ; query de la requête la remplace ;
     conditions de la requête combinées en ET avec les stockées (le résultat est l'intersection, ex. deux
     collections/attributs).
   - **Liste blanche** : `data = {query: …, ids: [<autre asset>], workspaces: [<id inexistant>]}` → `ids`/`workspaces`
     ignorés (résultats identiques à la seule query).
4. Lancer la vérification ci-dessous ; `composer cs` sur les fichiers touchés.

## Vérification

Exige la stack Docker partagée (base de test SQLite + Elasticsearch + Redis) :

```bash
dc run --rm databox-api-php su app -c "composer phpunit:compact -- --filter 'AssetSavedSearchOptionTest|AssetInversedConditionSearchTest|SavedSearchApiTest|AssetSearchTest'" 2>&1 | tail -60
dc run --rm databox-api-php su app -c "composer phpstan" 2>&1 | tail -30
dc run --rm databox-api-php su app -c "composer cs"
```

Si `composer phpunit:compact -- --filter` ne transmet pas le filtre, utiliser
`composer run pre-phpunit` puis
`dc run --rm -e APP_ENV=test databox-api-php su app -c "php -d memory_limit=1024M bin/phpunit --extension 'Alchemy\ApiTest\PHPUnit\CompactResultExtension' --filter AssetSavedSearchOptionTest"`.
Puis la suite complète : `dc run --rm databox-api-php su app -c "composer phpunit:compact" 2>&1 | tail -50`.
Un run parallèle d'un autre agent peut partager la même base/index ES (cf. verrou populate Redis) : en cas
d'échec « populate already running », relancer après la fin de l'autre run.

## Hors périmètre

- Client databox : aucun changement (il ne passe pas `savedSearch`).
- Validation du schéma de `data` à l'écriture (`SavedSearchInput`) ; bugs `markTestIncomplete` de `SavedSearchApiTest`.
- Autres recherches (collections, baskets) : pas d'option `savedSearch`.

## Interactions avec la file

- `003-saved-search-automations-core` (pending) ajoute `AssetSearch::iterateIds()` qui réutilise
  `buildQuery()` et range explicitement ces trois bugs hors de son périmètre (« à traiter à part ») :
  ce plan en est le traitement. Recouvrement sur `databox/api/src/Elasticsearch/AssetSearch.php`
  (003 ajoute une méthode, ce plan modifie `buildQuery()`/`applySort()`) → conflit textuel probable mais
  trivial. Le contrôle READ passe sous le token emprunté du propriétaire utilisé par 003. Pas de dépendance
  bloquante dans un sens ni dans l'autre ; `depends_on: []`.
- Autres plans (001-*, 002-*, 004-006) : pas de fichier commun.
