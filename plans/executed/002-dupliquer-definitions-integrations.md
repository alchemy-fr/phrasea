---
id: 002-dupliquer-definitions-integrations
title: Dupliquer attribute definition, rendition definition et intégration
status: executed
finished: 2026-10-19 14:55
started: 2026-10-19 11:24
run_base: bd9d98b44e664cf3b0e6b4dea62d54e6d4d3131d
created: 2026-10-19 11:13
branch: next
depends_on: []
---

# Dupliquer une attribute definition, une rendition definition, une intégration

## Contexte et objectif

Dans le client databox (gestion d'un workspace : onglets `attributes`, `renditions`, `integrations`, et
écran des intégrations d'instance `/admin/integrations`), un administrateur doit pouvoir **dupliquer**
un élément existant pour en créer une variante sans tout ressaisir. Aujourd'hui rien ne le permet : pas de
`__clone()`, pas d'endpoint, pas d'action UI.

## Décisions prises (avec l'utilisateur)

- **Mécanisme : endpoint API qui crée la copie directement** — `POST /<ressource>/{id}/duplicate`,
  réponse 201 avec le DTO de sortie habituel. Le client ajoute ensuite la copie à la liste, la
  sélectionne et ouvre son formulaire (l'utilisateur peut la renommer). Pas de formulaire pré-rempli.
- **Nom de la copie** : `"<nom> (copy)"`, puis `"<nom> (copy 2)"`, `"(copy 3)"`… jusqu'à un nom libre
  dans le workspace (suffixe en anglais, généré côté serveur).
- **Intégration : la copie est créée désactivée** (`enabled = false`) pour éviter un double déclenchement.
- **Rendition definition : les drapeaux `useAsMain/useAsPreview/useAsThumbnail/useAsAnimatedThumbnail`
  sont copiés tels quels.**
- **Surface : client databox uniquement.** Pas d'action EasyAdmin.
- Pas de dialogue de confirmation pour dupliquer (action non destructive) ; toast de succès (`sonner`).

## Règles de copie

Toujours : **ne pas** copier les données rattachées (valeurs d'attributs `attributes`, `renditions`
d'assets, `IntegrationData`, `IntegrationToken`, ACE), **vider** `key` (sinon contrainte unique
`(workspace_id, key)` + l'upsert par clé des InputMappers viserait l'original) et `lastErrors`.
Les traductions sont copiées **sauf** l'entrée `name` (elle traduirait le nom de l'original).

- **AttributeDefinition** (`databox/api/src/Entity/Core/AttributeDefinition.php`) : même workspace,
  mêmes références `policy`, `entityList`, `writeMetadataRenditions` (côté propriétaire de la
  ManyToMany) ; copier tous les scalaires/JSON (`fileType`, `type`, booléens `searchable`, `facetEnabled`,
  `sortable`, `translatable`, `multiple`, `allowInvalid`, `suggest`, `editable`, `enabled`,
  `fillFromName`, `namePriority`, `searchBoost`, `initialValues`, `readFromMetadata`, `writeMetadata`,
  `fallback`, `labels`, `options`, `target`, `position`). La liste exhaustive des setters est dans
  `databox/api/src/Service/Workspace/Template/Section/AttributeDefinitionSection.php` (`import()`
  L65–131) : s'en servir comme référence, ne pas réutiliser la section (son `findExisting` matche
  l'original). `slug` : généré par Gedmo à l'insert depuis le nouveau nom (style lower, séparateur `''`) ;
  la contrainte DB `uniq_attr_def_ws_slug` impose que le générateur de nom saute aussi un candidat dont
  le slug calculé existe déjà. Même `position` que la source : l'ordre `position, name` place la copie
  juste après. Entité en `DEFERRED_EXPLICIT` → `persist()` explicite.
- **RenditionDefinition** (`databox/api/src/Entity/Core/RenditionDefinition.php`) : mêmes `policy`,
  `parent` ; copier `definition`, `config`, `buildMode`, `substitutable`, `download`, `priority`,
  `useAs*`, `labels`, `target`, et les `writeMetadataAttributes` (via le côté propriétaire
  `AttributeDefinition::addWriteMetadataRendition`). Référence des champs :
  `Template/Section/RenditionDefinitionSection.php` (`import()` L53–103).
- **WorkspaceIntegration** (`databox/api/src/Entity/Integration/WorkspaceIntegration.php`) : même
  `workspace` (éventuellement null = intégration d'instance) et même `integration` ; copier `if`,
  `public`, `needs` (mêmes cibles ; aucun cycle possible) ; `enabled = false` ; `ownerId` = utilisateur
  courant (`WithOwnerIdProcessorTrait::processOwnerId` après avoir mis `ownerId` à null) ; nom unique sur
  `(workspace, name, integration)` — si la source n'a pas de nom, partir du titre du type d'intégration
  (registry des intégrations). `config` : copiée puis passée à une nouvelle méthode
  `IntegrationInterface::prepareDuplicateConfiguration(array $config): array` (implémentation par défaut
  dans `src/Integration/AbstractIntegration.php` = identité ; `src/Integration/Phrasea/Uploader/UploaderIntegration.php`
  retire `securityKey` puis appelle `generateConfigurationDefaults()` pour en générer une nouvelle — une
  clé de sécurité ne doit pas être partagée entre deux intégrations). `AbstractIntegration` est la seule
  implémentation de l'interface.

## Fichiers concernés

### API (`databox/api`)

- `src/Entity/Core/AttributeDefinition.php`, `src/Entity/Core/RenditionDefinition.php`,
  `src/Entity/Integration/WorkspaceIntegration.php` — nouvelle opération dans chaque `#[ApiResource]`,
  sur le modèle de `/workspaces/{id}/flush` (`src/Entity/Core/Workspace.php` L55–62) :
  ```php
  new Post(
      uriTemplate: '/attribute-definitions/{id}/duplicate', // /rendition-definitions/…, /integrations/…
      throwOnNotFound: true,
      security: 'is_granted("'.AbstractVoter::EDIT.'", object)',
      input: false,
      processor: DuplicateAttributeDefinitionProcessor::class,
      name: 'duplicate',
  ),
  ```
  Statut 201 par défaut ; la sortie reste le DTO `output:` de la ressource, appliqué par
  `OutputMapperProcessor` sur l'entité retournée. Vérifier que le provider
  `WorkspaceIntegrationCollectionProvider` sert bien l'item pour cette opération (sinon mettre
  `provider:` item adéquat sur l'opération).
- `src/Api/Processor/DuplicateAttributeDefinitionProcessor.php`,
  `DuplicateRenditionDefinitionProcessor.php`, `DuplicateWorkspaceIntegrationProcessor.php` — nouveaux :
  construisent la copie selon les règles ci-dessus, contrôlent `is_granted("CREATE", $copy)` (403 sinon —
  pour les intégrations, EDIT peut venir d'une ACE alors que CREATE exige l'éditeur du workspace / l'admin
  pour une intégration d'instance), valident l'entité avec le `ValidatorInterface` (groupes
  `Default, create` pour l'intégration ; lever `ApiPlatform\Validator\Exception\ValidationException`),
  `persist` + `flush`, retournent la copie. La logique de copie peut vivre dans un service si elle devient
  longue, mais pas de `__clone()` sur les entités (collections Doctrine et ids à réinitialiser).
- `src/Service/Duplicate/CopyNameGenerator.php` — nouveau : `generate(string $name, callable $isTaken): string`
  produisant `X (copy)`, `X (copy 2)`… ; si `X` finit déjà par ` (copy)` / ` (copy N)`, repartir de la base
  (dupliquer « X (copy) » donne « X (copy 2) »). Les processors fournissent le `$isTaken` (requête
  repository par workspace — `workspace IS NULL` pour une intégration d'instance — + slug pour les attributs).
  Respecter la longueur max de la colonne `name`.
- `src/Integration/IntegrationInterface.php`, `src/Integration/AbstractIntegration.php`,
  `src/Integration/Phrasea/Uploader/UploaderIntegration.php` — `prepareDuplicateConfiguration()`.
- Tests fonctionnels (ajouter aux fichiers existants, mêmes helpers) :
  - `tests/functional/Api/Attribute/AttributeDefinitionTest.php` (trait `AttributeApiTestTrait::api()`,
    `createAttributeDefinition()`, `grantOnWorkspace()`…)
  - `tests/functional/Api/Rendition/RenditionDefinitionApiTest.php` (`RenditionTestTrait`, constantes
    `EDITOR`/`READER`/`ADMIN`)
  - `tests/functional/Api/WorkspaceIntegrationTest.php`
  - Unit : `tests/unit/Service/Duplicate/CopyNameGeneratorTest.php` (namespace `App\Tests\Unit\…`).

### Client (`databox/client`)

- `src/features/workspaces/manage/DefinitionManager.tsx` — prop optionnelle
  `onDuplicate?: (item: T) => Promise<T>` ; dans `RowView` (L398–464) un bouton ghost `size="icon-xs"`
  avec `CopyIcon` (lucide), `aria-label` traduit, `data-testid="definition-duplicate"`, visible au survol
  comme la corbeille et placé avant elle ; passer un `noop` dans le rendu `SortableOverlay` (L305–316)
  comme pour les autres actions. Au clic : `const copy = await onDuplicate(item)`, `onChanged()`,
  `setCreated(copy)`, `navigate([copy.id])` (même mécanisme que l'enregistrement, L351–360), toast
  `definition.duplicated` ; en erreur `toast.error(e?.message)`. Bouton désactivé pendant l'appel.
  Les autres utilisateurs de `DefinitionManager` (tags, policies, entity lists…) ne changent pas.
- `src/lib/api/metadata.ts` — `duplicateAttributeDefinition(id)` ; `src/lib/api/misc.ts` —
  `duplicateRenditionDefinition(id)` ; `src/lib/api/integrations.ts` — `duplicateIntegration(id)`. Modèle :
  `archiveBasket` (`src/lib/api/misc.ts:81`) → `api.post<T>(\`/${EntityName.X}/${id}/duplicate\`, {})`.
- `src/features/workspaces/manage/tabs/AttributeDefinitionsTab.tsx`,
  `src/features/workspaces/manage/tabs/RenditionDefinitionsTab.tsx`,
  `src/features/integrations/manage/IntegrationManager.tsx` (sert aussi l'écran d'instance) — passer
  `onDuplicate` à côté de `onDelete`. Pour les attributs, `onChanged` recharge déjà le store
  `useDefinitionsStore.loadWorkspace`.
- `src/i18n/locales/en.json`, `src/i18n/locales/fr.json` — clés ajoutées **à la main** (le scanner
  `pnpm translate` plante) : `definition.duplicate` = « Duplicate » / « Dupliquer »,
  `definition.duplicated` = « Copy created » / « Copie créée ».
- Test vitest : `src/features/workspaces/manage/DefinitionManager.test.tsx` (nouveau, jsdom) — le bouton
  n'apparaît qu'avec `onDuplicate`, le clic appelle `onDuplicate(item)` puis `onChanged`.
- Cypress : `cypress/cypress/e2e/databox/09-workspace-admin.cy.js` (dupliquer une attribute definition
  et une rendition definition → un `definition-item` « … (copy) » apparaît et est sélectionné) et
  `cypress/cypress/e2e/databox/17-integrations.cy.js` (dupliquer une intégration → copie désactivée).

## Étapes

1. `CopyNameGenerator` + test unitaire.
2. `prepareDuplicateConfiguration()` dans l'interface, `AbstractIntegration`, `UploaderIntegration`.
3. Les trois processors + les trois opérations `duplicate`.
4. Tests fonctionnels, pour chaque ressource :
   - éditeur → 201, nom `X (copy)`, `key` null, champs copiés identiques, références conservées
     (policy, parent, entityList, writeMetadataRenditions / writeMetadataAttributes, needs), original
     inchangé ; seconde duplication → `X (copy 2)` ; dupliquer `X (copy)` → `X (copy 2)` ;
   - lecteur / utilisateur sans droit → 403 ; id inconnu → 404 ;
   - attribut : slug de la copie distinct ; traductions copiées hors `name` ; aucune valeur d'attribut copiée ;
   - rendition : `useAs*` copiés ; aucune `AssetRendition` copiée ;
   - intégration : `enabled` false, `ownerId` = appelant, `needs` copiés, intégration uploader → `securityKey`
     différente, tokens/data non copiés ; intégration d'instance dupliquée par un admin (workspace null).
5. Client : fonctions API, `DefinitionManager`, câblage des trois écrans, i18n, test vitest.
6. Cypress (09 et 17).
7. `composer cs` (php-cs-fixer) et `pnpm --filter databox-client cs`.

## Vérification

Conteneurs Docker (rien sur l'hôte). Depuis un worktree, adapter le projet compose / chemins
(`/var/workspace/<worktree>` dans le conteneur `dev`).

- PHP ciblé (base de test sqlite ; préparer avant un `bin/phpunit` direct) :
  ```bash
  dc run --rm -e APP_ENV=test databox-api-php su app -c "composer run pre-phpunit && php -d memory_limit=1024M bin/phpunit --extension 'Alchemy\ApiTest\PHPUnit\CompactResultExtension' --filter 'Duplicate|CopyNameGenerator'"
  ```
  puis les fichiers de test touchés en entier, puis `dc run --rm databox-api-php su app -c "composer phpstan"`.
  Suite complète : `dc run --rm databox-api-php su app -c "composer phpunit:compact"`.
- Client :
  ```bash
  dc run --rm dev pnpm --filter databox-client test
  dc run --rm dev pnpm --filter databox-client lint
  ```
  + typecheck (`tsc --noEmit` dans le conteneur dev ; ~15 erreurs préexistantes de doubles `@types/react`
  à ignorer). Si `dc run dev` sort en 0 sans rien exécuter (pas de TTY), passer par
  `dc run --rm --entrypoint sh dev -c "su app -c '…'"`.
- **Nécessite la stack partagée (vérifiable seulement après intégration)** : Cypress 09 et 17, et un essai
  manuel dans le client (dupliquer chacun des trois types, vérifier sélection + formulaire ouvert de la
  copie, intégration désactivée). Les tests sqlite ne couvrent pas les particularités Postgres
  (contraintes uniques avec `NULL` notamment) : vérifier une duplication d'intégration sans nom sur la
  stack Postgres.

## Hors périmètre

- Action « Dupliquer » dans EasyAdmin.
- Duplication d'autres objets (policies, tags, entity lists, filter rules, workflows…), même s'ils
  utilisent `DefinitionManager` : le prop optionnel le permettra plus tard.
- Copie vers un autre workspace.
- Copie des ACE d'une intégration, de ses tokens/données, des valeurs d'attributs ou des renditions d'assets.
- Choix du nom à la duplication (renommage ensuite via le formulaire).

## Interactions avec la file

- `001-fixtures-databox-3-workspaces` : touche `databox/api/fixtures/*.yaml`, `ImageUrlFaker`, docs —
  aucun recouvrement de fichiers. Si les fixtures changent pendant l'exécution, les tests fonctionnels
  de ce plan n'en dépendent pas (ils créent leurs données).
- `001-readme-open-source` : README et fichiers communauté — aucun recouvrement.
- `depends_on: []`.
