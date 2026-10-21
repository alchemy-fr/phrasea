---
id: 010-formulaire-config-integrations
title: Formulaire de configuration des intégrations généré depuis leurs paramètres
status: executed
started: 2026-10-21 14:38
finished: 2026-10-21 15:27
run_base: b3016ad801644f549ccdc99670d050c958e097da
created: 2026-10-21 02:38
branch: next
depends_on: []
---
# Formulaire de configuration des intégrations généré depuis leurs paramètres (fin du YAML obligatoire)

## Contexte et objectif

Aujourd'hui, configurer une intégration databox (`/workspaces/:id/manage/integrations`) consiste à
écrire du **YAML libre** dans un `CodeEditor`, en s'aidant d'une « référence » textuelle (dump
`YamlReferenceDumper` de l'arbre Symfony Config de l'intégration). Rien ne guide l'utilisateur :
ni les types, ni les valeurs possibles, ni les ressources du workspace à référencer (slugs
d'attributs, noms de renditions, collection…). Le cas d'origine : l'intégration Faces
(`core.face_recognition`) exige dans `attribute` le slug d'une définition d'attribut `entity`,
multivaluée, liée à une liste d'entités — et l'API **refuse d'enregistrer** l'intégration tant que
cet attribut n'existe pas (`FaceRecognitionIntegration::validateConfiguration` via
`ValidIntegrationOptionsConstraintValidator`). L'administrateur doit donc créer la liste, puis
l'attribut, puis écrire le YAML, à la main.

Objectif : **un seul formulaire généré** pour toutes les intégrations à partir de leur arbre de
configuration (`buildConfiguration(NodeBuilder)`), exposé par l'API sous forme de schéma JSON ; les
champs qui référencent une ressource du workspace deviennent des sélecteurs, et les attributs
offrent « Créer… » (dialog contraint par le champ : type, multivalué, liste d'entités créée à la
volée). Le YAML reste disponible en **mode avancé**. Le wizard Faces de la demande initiale est
donc un cas particulier du sélecteur d'attribut « choisir ou créer ».

## Décisions prises avec l'utilisateur

1. **Formulaire généré sur une page** (groupes repliables), pas de stepper multi-étapes. Le côté
   « wizard » = sélecteurs « choisir ou créer » sur les champs qui référencent une ressource.
2. **YAML conservé en mode avancé** : bascule Formulaire / YAML dans le même écran, conversion dans
   les deux sens côté client (nouvelle dépendance `yaml` dans `databox/client`).
3. **Un seul plan**, exécuté par un seul agent (API + client).
4. Décisions d'implémentation prises pendant l'exploration (à appliquer telles quelles) :
   - Le schéma est **maison** (inspiré de JSON Schema, pas conforme), généré en PHP par un dumper
     qui parcourt l'arbre `NodeInterface` construit par `TreeBuilder::buildTree()`.
   - Les indications d'UI (« widget », secret, contraintes) se déclarent **dans l'arbre de config
     lui-même** via `NodeDefinition::attribute()` (Symfony Config), lues par `BaseNode::getAttribute()`.
   - « Créer… » est offert pour les **attributs** (et la liste d'entités nécessaire). Les
     **renditions** et **collections** sont en sélection seule (créer une rendition exige sa
     définition de build YAML ; la collection a déjà son écran).
   - Les secrets ne sont **pas** caviardés côté API (comportement inchangé : `configYaml` complet
     pour qui a EDIT) ; le formulaire les affiche en champ masqué avec bouton « afficher ».
   - La liste polymorphe `analyzers` de `core.file_analyzer` est décrite par des **variantes**
     discriminées par `name`, une par analyseur.
   - Pas de nouvel endpoint de création : les sélecteurs utilisent `POST /entity-lists` et
     `POST /attribute-definitions` existants ; si la définition échoue après la liste, la liste
     créée est supprimée (`deleteEntityList`, cascade) avant de remonter l'erreur.

## Fichiers concernés (chemins depuis la racine du dépôt)

### API (`databox/api`)

- `src/Integration/Config/ConfigWidget.php` (**nouveau**) : `enum ConfigWidget: string`
  avec `Attribute = 'attribute'`, `Rendition = 'rendition'`, `Collection = 'collection'`,
  `Email = 'email'`, `Language = 'language'` ; constantes `ATTR_WIDGET = 'widget'`,
  `ATTR_WIDGET_OPTIONS = 'widgetOptions'`, `ATTR_SECRET = 'secret'` (clés passées à
  `NodeDefinition::attribute()`). Méthode statique
  `apply(NodeDefinition $node, self $widget, array $options = []): NodeDefinition` pour alléger
  les intégrations.
- `src/Integration/Config/ConfigSchemaDumper.php` (**nouveau**, service) :
  `dump(NodeInterface $root): array` renvoie le schéma de la racine (un objet dont `properties`
  sont les clés de config). Règles de conversion :

  | Nœud Symfony | Schéma |
  |---|---|
  | `ArrayNode` avec enfants | `{type:'object', properties:{…}}` ; si un enfant `enabled` est un `BooleanNode` et que le nœud `hasDefaultValue()` (`canBeEnabled`/`canBeDisabled`) → `toggle:'enabled'` |
  | `ArrayNode` sans enfant ni prototype (ex. `webhook.options`) | `{type:'any'}` |
  | `PrototypedArrayNode` | `{type:'list', items: dump(getPrototype()), minItems}` (`getKeyAttribute()` non utilisé aujourd'hui : ignoré) |
  | `VariableNode` | `{type:'any'}` |
  | `EnumNode` | `{type:'enum', values: getValues()}` |
  | `IntegerNode` / `FloatNode` | `{type:'integer'|'float', min, max}` (`NumericNode::getMin/getMax`) |
  | `BooleanNode` | `{type:'boolean'}` |
  | `ScalarNode` | `{type:'string'}` |

  Sur chaque nœud : `label` (clé humanisée : `maxImageSize` → « Max image size », `actions_on_reject`
  → « Actions on reject »), `info` (`getInfo()`), `example` (`getExample()`), `required`
  (`isRequired()`), `default` (`hasDefaultValue() ? getDefaultValue() : absent`), `widget`,
  `widgetOptions`, `secret` depuis `getAttribute(...)`. Les attributs d'un prototype sont portés
  par `items`.
- `src/Integration/Config/ConfigSchemaCustomizerInterface.php` (**nouveau**) :
  `customizeConfigSchema(array $schema): array`.
- `src/Integration/IntegrationManager.php` : nouvelle méthode
  `getIntegrationSchema(IntegrationInterface $integration): array` (même construction d'arbre que
  `getIntegrationReference()` : `new TreeBuilder('root')` + `buildConfiguration()` +
  `buildTree()`, puis `ConfigSchemaDumper::dump`, puis le customizer si l'intégration l'implémente).
- `src/Model/IntegrationType.php` : `#[Groups(self::GROUP_READ)] public array $schema = [];` ;
  `src/Api/Provider/IntegrationTypeProvider.php` : `$object->schema =
  $this->integrationManager->getIntegrationSchema($integration);` (collection et item).
- `src/Api/Model/Output/WorkspaceIntegrationOutput.php` : `public ?array $configValues = null`
  (groupe `GROUP_LIST`) ; `src/Api/Mapper/Output/WorkspaceIntegrationOutputMapper.php` : à côté
  de `configYaml`, `$output->configValues = $integration->denormalizeConfiguration(
  $data->getConfig(), $data->getWorkspace())` (même garde `isGranted(EDIT)`). L'input accepte déjà
  `config` (tableau) ou `configYaml` ; inchangé.
- `src/Validator/ValidIntegrationOptionsConstraintValidator.php` : quand l'exception est une
  `InvalidConfigurationException` avec `getPath()` non vide, violation `atPath('config.'.<chemin
  sans le préfixe `root.`>)` (ex. `root.budgetLimit.limit` → `config.budgetLimit.limit`,
  `root.analyzers.0.name` → `config.analyzers.0.name`) ; sinon `config` comme aujourd'hui.
- `src/Integration/Core/FaceRecognition/FaceRecognitionIntegration.php` :
  `attribute` → `ConfigWidget::apply($node, ConfigWidget::Attribute, ['type' => 'entity',
  'multiple' => true, 'entityList' => true])` ; `rendition` → `Rendition`. Dans
  `validateConfiguration`, les exceptions qui visent `attribute` reçoivent `setPath('root.attribute')`
  (même chose pour `rendition` dans `AwsRekognitionIntegration`).
- Annotations dans les autres intégrations (uniquement `->attribute(...)`, aucun changement de
  sémantique) :
  - `Rendition` : `blurhash.rendition`, `core.similarity.rendition`, `happyscribe.rendition`,
    `aws.rekognition.{labels,texts,faces}.rendition`, prototypes de `core.rendition.renditions` et
    `core.watermark.applyToRenditions` ;
  - `Attribute` : `blurhash.attribute`, `test.asset_operation.attribute`,
    `core.watermark.attributeName`, `happyscribe.attribute` et `sourceLanguageAttribute`,
    `aws.transcribe.attributes.{language,vtt,srt,transcript}`,
    `aws.rekognition.{labels,texts}.attributes[].name` (option `['multiple' => true]`) ;
  - `Collection` : `phrasea.uploader.collectionId` ;
  - `Email` : prototype de `core.moderation.emails` ; `Language` : `aws.translate.defaultSourceLanguage`
    et les prototypes de `preferredSourceLanguages` / `translatedLanguages` ;
  - `secret: true` : `AbstractAwsIntegration::addCredentialConfigNode` (`accessKeySecret`),
    `happyscribe.apiKey`, `phrasea.uploader.securityKey`, `phraseanet.renditions.token`,
    `remove.bg.apiKey`.
- `src/Integration/Core/FileAnalyzer/FileAnalyzerIntegration.php` : implémente
  `ConfigSchemaCustomizerInterface` ; remplace `analyzers.items` par
  `{type:'object', discriminator:'name', variants:{<name>: dump(arbre de l'analyseur)}}`, l'arbre
  de chaque analyseur étant construit comme dans `FileAnalyzerRegistry::processConfiguration`
  (`FileAnalyzerConfigHelper::createBaseTree($name)` + `$analyzer->buildConfiguration($children)`
  + `buildTree()`) pour chaque analyseur de `FileAnalyzerRegistry::getAnalyzers()`. Le dumper doit
  tolérer le double `max_severity` de `createBaseTree` (le second l'emporte ; ne pas le corriger ici).
- `tests/unit/Integration/Config/ConfigSchemaDumperTest.php` (**nouveau**, `App\Tests\Unit\…`,
  `TestCase` pur) : un `TreeBuilder` de test couvrant chaque forme (scalaire requis avec défaut et
  info, entier min/max, float, booléen, enum, objet imbriqué, objet `canBeEnabled`, listes de
  scalaires / d'enums / d'objets, `variableNode`, arrayNode vide, attributs widget/secret) et
  assertions sur le tableau produit.
- `tests/functional/Api/Platform/IntegrationApiTest.php` : étendre `testIntegrationTypesCatalog`
  (ou nouveau test) : `GET /integration-types/core--face_recognition` expose `schema.properties.attribute`
  avec `widget === 'attribute'`, `widgetOptions.type === 'entity'`, `required === true`, et
  `schema.properties.minConfidence.max === 1` ; `GET /integration-types/core--file_analyzer` expose
  `schema.properties.analyzers.items.variants.checksum`. Nouveau test : `POST /integrations` avec
  `config: {attribute: 'x', minConfidence: 2}` (ou `budgetLimit.limit: 'abc'` sur une intégration
  sans ressource requise) → 422 avec une violation dont `propertyPath` vaut `config.minConfidence`.
  Et : la réponse d'un GET par un éditeur contient `configValues` (objet) en plus de `configYaml`.
- `doc/tech/Databox/integration/01_integrations.md` : section « Configuration form & schema »
  (comment annoter un nœud avec `ConfigWidget`, ce que le client en fait, le mode YAML).

### Client (`databox/client`)

- `package.json` : dépendance `yaml` (`^2`), installée via le conteneur `dev`
  (`dc run --rm dev pnpm --filter databox-client add yaml` ; piège TTY → voir Vérification).
- `src/types/api.ts` : `IntegrationType.schema: ConfigSchema` ; `WorkspaceIntegration.configValues?:
  Record<string, unknown>`.
- `src/features/integrations/config/schema.ts` (**nouveau**) : types TS du schéma (`ConfigSchema`
  union discriminée sur `type`, `ConfigObjectSchema.toggle`, `.variants`, `.discriminator`,
  `ConfigListSchema.items`, `widget`, `widgetOptions`, `secret`…) et helpers purs :
  `defaultsOf(schema)` (valeurs initiales : défauts déclarés, objets avec `toggle` → `{enabled:
  default}`, listes → `[]`), `pruneConfig(values, schema)` (retire `undefined`, chaînes vides et
  listes vides des clés **non requises**, et les objets devenus vides, pour envoyer une config aussi
  minimale que le YAML d'aujourd'hui), `toYaml(values)` / `fromYaml(text)` (`yaml.stringify` /
  `yaml.parse`, erreur de parse remontée), `errorsByPath(apiError)` (violations
  `config.a.b` → `Record<'a.b', string>`, le reste dans `_global`), `labelOf(schema, key)`.
- `src/features/integrations/config/ConfigForm.tsx` (**nouveau**) : `<ConfigForm schema value
  onChange errors workspaceId>` et le composant récursif `ConfigField` ({schema, path, value,
  onChange, errors, workspaceId}). Rendu par type :
  - `string` : `Input` (`FormRow` label/help=`info`, placeholder=`example`) ; `secret` → `type=
    "password"` + bouton œil (`EyeIcon`/`EyeOffIcon`) ; `widget` → sélecteur (ci-dessous) ;
    `email` → `type="email"` ; valeur commençant par `$` (référence `${VAR}` d'env/secret de
    workspace) → `font-mono` et aide `integration.config.env_ref` ;
  - `integer`/`float` : `Input type="number"` (`min`, `max`, `step` 1 ou `any`), conversion
    nombre/`undefined` ;
  - `boolean` : `Switch` dans `LabeledControl` ;
  - `enum` : `SimpleSelect` (option « — » quand non requis) ;
  - `any` : `CodeEditor mode="yaml"` sur ce seul nœud (`toYaml`/`fromYaml`, erreur de parse
    affichée dans `FormRow error`, valeur conservée tant que le YAML est invalide) ;
  - `object` : `<fieldset>` bordé (`rounded-lg border p-3`) avec `<legend>` ; si `toggle`, un
    `Switch` dans la légende et les champs masqués quand désactivé ; les objets de niveau racine sont
    rendus directement (pas de fieldset pour la racine) ;
  - `list` : `items.type` scalaire sans widget → nouveau `components/form/TagsInput.tsx` (puces,
    Entrée/virgule ajoute, Retour arrière retire la dernière, collage découpe sur virgule/espace) ;
    `items.type === 'enum'` → groupe de `Checkbox` ; `items.widget` → sélecteur `multiple` ;
    `items.type === 'object'` → cartes répétées (en-tête « #n » + bouton supprimer `Trash2Icon`,
    bouton « Ajouter » `PlusIcon`), et si `items.variants` : `SimpleSelect` sur le discriminateur
    en tête de carte, champs de la variante choisie en dessous (valeur `{name: 'checksum', …}`) ;
    `items.type === 'any'` → éditeur YAML sur toute la liste ;
  - erreurs : `errors[path]` dans `FormRow error` ; `errors._global` et les chemins inconnus dans
    un `FieldError` en tête de formulaire ;
  - `data-testid="config-field-<path>"` sur chaque `FormRow`, `config-list-add-<path>`,
    `config-list-remove-<path>.<i>`, `config-toggle-<path>`.
- `src/features/integrations/config/pickers/AttributePicker.tsx` (**nouveau**) : `AsyncCombobox`
  (valeur = **slug**), options via `getWorkspaceAttributeDefinitions({workspaceId, query,
  type: options.type})` filtrées sur `options.multiple` (→ `d.multiple`) et `options.entityList`
  (→ `!!d.entityList`) ; label `d.name` + badge slug ; `resolveValue` cherche le slug dans la même
  liste (`['attribute-definitions', 'picker', workspaceId, options]`) ; `onCreate` ouvre
  `CreateAttributeDialog` (`useModals().openModal`, résultat = définition créée ou `undefined`) et
  renvoie `{value: slug, label}`. Mode `multiple` pour les listes. Sans `workspaceId`
  (intégration d'instance) : retombe sur un `Input` texte.
- `src/features/integrations/config/pickers/CreateAttributeDialog.tsx` (**nouveau**, sur
  `components/modals/FormDialog.tsx`, props `ModalProps<AttributeDefinition | undefined>` +
  `workspaceId` + `constraints: {type?, multiple?, entityList?}` + `initialName`) : champs Nom
  (`faces.setup.attribute_default` « Faces » par défaut quand `type === 'entity'`, sinon
  `initialName`), Policy (`getAttributePolicies`, défaut « Public » si présente sinon la première),
  Type (`getAttributeFieldTypes()`, verrouillé si imposé), « Valeurs multiples » (`Checkbox`,
  coché et verrouillé si imposé) ; quand le type est `entity` : Liste d'entités = `SimpleSelect`
  des listes du workspace (`getEntityLists({workspaceId})`) + option « Nouvelle liste… » qui
  affiche un `Input` nom (défaut `faces.setup.list_default` « Persons »). Soumission : si nouvelle
  liste → `postEntityList(workspaceId, {name})` ; puis `postAttributeDefinition({workspace:
  iri(EntityName.Workspace, workspaceId), name, type, multiple, policy, entityList: list['@id'] ou
  IRI choisie, searchable: true, editable: true, editableInGui: true, target: AssetType.Asset})` ;
  en cas d'échec après création de la liste → `deleteEntityList(list.id)` (best effort) puis
  `throw`. Succès : invalider `['entity-lists', workspaceId]`, `['attribute-definitions',
  'manage', workspaceId]`, `['attribute-definitions', 'picker', workspaceId]`,
  `faceRecognitionSetupsQueryKey` (`features/faces/useFaceRecognition.ts`) ; appeler
  `useDefinitionsStore.getState().loadWorkspace(workspaceId)` (`features/attributes/definitionsStore.ts`) ;
  `resolve(saved)`.
- `src/features/integrations/config/pickers/RenditionPicker.tsx` (**nouveau**) : `AsyncCombobox`
  (valeur = **nom** de rendition, comme le YAML dénormalisé), options via `getRenditionDefinitions(
  {workspaceId, query})` (`lib/api/misc.ts`), mode `multiple` pour les listes ; sans `workspaceId`
  → `Input`.
- `src/features/integrations/config/pickers/CollectionPicker.tsx` (**nouveau**) : enveloppe
  `components/form/CollectionTreePicker.tsx` restreint au workspace, valeur = id de collection
  (format attendu par `phrasea.uploader.collectionId`).
- `src/features/integrations/manage/IntegrationManager.tsx` (`IntegrationForm`) :
  - état : `config: Record<string, unknown>` initialisé à `i?.configValues ?? defaultsOf(schema)`,
    `configYaml` conservé, `configMode: 'form' | 'yaml'` (défaut `form` quand le type a un schéma
    avec au moins une propriété, sinon `yaml` ; types sans config → encart « Aucun paramètre ») ;
    le schéma vient de `reference.data?.schema` (requête `['integration-type', form.integration]`
    existante) ;
  - en-tête de la section Configuration : bascule Formulaire / YAML (deux `Toggle` de
    `components/ui/controls.tsx`, `data-testid="integration-config-mode-form|yaml"`) ; form→yaml :
    `configYaml = toYaml(pruneConfig(config))` ; yaml→form : `config = fromYaml(configYaml)` (si
    parse invalide : `toast.error`, on reste en YAML) ;
  - rendu : `<ConfigForm>` (`data-testid="integration-config-form"`) ou le `CodeEditor` actuel ;
    `ReferenceDetails` seulement en mode YAML ;
  - `save()` : mode form → `config: pruneConfig(config, schema)` et pas de `configYaml` ; mode
    yaml → `configYaml` comme aujourd'hui ; sur `ApiError` 422 → `setErrors(errorsByPath(e))` en
    plus du toast ; `useDirtyState` inclut `config` ;
  - `IntegrationCatalog.onSelect` : `config = defaultsOf(schema)` dès que le schéma est chargé
    (effet sur `reference.data` quand `!i`).
- `src/features/integrations/manage/integrationTypeUi.tsx` : `'core.face_recognition':
  ScanFaceIcon` (lucide) dans `icons`.
- `src/i18n/locales/en.json` et `fr.json` (clés ajoutées à la main, ordre alphabétique) :
  `integration.config.mode_form` (« Form »), `mode_yaml` (« YAML »), `no_config` (« This integration
  has no parameter »), `env_ref` (« Reference to a workspace secret or variable »), `yaml_invalid`,
  `list_add` (« Add »), `list_remove`, `list_empty`, `variant` (« Type ») ; `attribute_picker.*`
  (`placeholder`, `create` « Create “{{name}}”… »), `create_attribute.*` (`title`, `name`,
  `policy`, `type`, `multiple`, `entity_list`, `new_list`, `list_name`, `submit`, `done`),
  `faces.setup.attribute_default` (« Faces » / « Visages »), `faces.setup.list_default`
  (« Persons » / « Personnes »).
- Tests Vitest (modèles : `features/workspaces/manage/DefinitionManager.test.tsx`,
  `features/faces/FaceRecognitionPanel.test.tsx` pour les mocks `vi.hoisted` + `importOriginal`) :
  - `features/integrations/config/schema.test.ts` : `defaultsOf`, `pruneConfig` (requis conservés,
    optionnels vides retirés, objets `toggle` gardés), aller-retour `toYaml`/`fromYaml`,
    `errorsByPath` ;
  - `features/integrations/config/ConfigForm.test.tsx` : schéma de fixture couvrant chaque type ;
    saisie d'un entier, bascule d'un objet `toggle`, ajout/suppression dans une liste d'objets,
    choix d'une variante, puces d'une liste de scalaires, erreur affichée sous le bon champ ;
  - `features/integrations/config/pickers/CreateAttributeDialog.test.tsx` : création liste +
    définition (`postEntityList` puis `postAttributeDefinition` avec `type: 'entity'`, `multiple:
    true`, `entityList` = IRI de la liste), rollback (`deleteEntityList` appelé quand la définition
    échoue), contraintes verrouillées.
- `cypress/cypress/e2e/databox/17-integrations.cy.js` : le test « creates a workspace
  integration » cherche `Configuration (YAML)` ; remplacer par `cy.getBySel('integration-config-form')`
  (la première carte du catalogue est une intégration avec schéma). Ajouter un test « sets up face
  recognition from the form » : carte `[data-integration="core.face_recognition"]`, champ
  `config-field-attribute` → saisir « Faces » → option « Create “Faces”… » → dialog → « Nouvelle
  liste… » → valider → Save → toast « Integration saved » ; puis `apiRequest` GET
  `/attribute-definitions?workspaceId=` contient `faces` (`type: entity`, `multiple: true`).

## Étapes

1. API : `ConfigWidget`, `ConfigSchemaDumper`, `ConfigSchemaCustomizerInterface`,
   `IntegrationManager::getIntegrationSchema`, `IntegrationType.schema`, `configValues` en sortie,
   chemins de violation. Test unitaire du dumper.
2. API : annotations des nœuds dans toutes les intégrations listées ; variantes de
   `FileAnalyzerIntegration` ; tests fonctionnels ; doc.
3. Client : dépendance `yaml`, types, `schema.ts` + tests.
4. Client : `TagsInput`, `ConfigForm` + tests.
5. Client : sélecteurs (`AttributePicker`, `CreateAttributeDialog`, `RenditionPicker`,
   `CollectionPicker`) + test du dialog.
6. Client : intégration dans `IntegrationForm` (modes, sauvegarde, erreurs), icône Faces, i18n.
7. Cypress : adapter le spec 17 et ajouter le scénario Faces.
8. CS : `dc run --rm databox-api-php composer cs` ; `dc run --rm dev pnpm --filter databox-client cs`.

## Vérification

Rien n'est installé sur l'hôte : tout passe par Docker (CLAUDE.md). Piège TTY du conteneur `dev` :
sans TTY, `dc run dev …` peut sortir en 0 sans rien exécuter ; dans ce cas utiliser
`dc run --rm --entrypoint sh dev -c "su app -c 'cd /var/workspace/<worktree> && pnpm …'"`.

```bash
# API
dc run --rm -e APP_ENV=test databox-api-php php -d memory_limit=1024M bin/phpunit --extension 'Alchemy\ApiTest\PHPUnit\CompactResultExtension' tests/unit/Integration/Config/ConfigSchemaDumperTest.php
dc run --rm databox-api-php composer run pre-phpunit   # avant un bin/phpunit fonctionnel direct
dc run --rm -e APP_ENV=test databox-api-php php -d memory_limit=1024M bin/phpunit --extension 'Alchemy\ApiTest\PHPUnit\CompactResultExtension' tests/functional/Api/Platform/IntegrationApiTest.php tests/functional/Integration
dc run --rm databox-api-php composer phpstan
dc run --rm databox-api-php composer phpunit:compact | tail -50
# Client
dc run --rm dev pnpm --filter databox-client add yaml
dc run --rm dev pnpm --filter databox-client test -- src/features/integrations
dc run --rm dev pnpm --filter databox-client test
dc run --rm dev pnpm --filter databox-client lint
dc run --rm dev pnpm --filter databox-client build   # ignorer le bruit tsc préexistant (doubles @types/react)
```

`composer install` en `APP_ENV=test` réécrit `config/reference.php` : le restaurer avant commit
s'il apparaît dans le diff.

Exige la stack partagée (Docker complet, Keycloak, navigateur) — **vérifiable seulement après
intégration** : Cypress `17-integrations.cy.js` ; parcours manuel : workspace → Gérer →
Intégrations → Nouvelle → « Face recognition » → le formulaire montre `attribute` en sélecteur,
« Create “Faces”… » → dialog (policy Public, type entité verrouillé, multi verrouillé, « Nouvelle
liste… » Persons) → Save → l'intégration s'ouvre, le YAML (mode avancé) affiche `attribute: faces`,
l'onglet Attributs montre « Faces », l'onglet Listes « Persons », l'entrée « Faces » de la barre
du haut apparaît sans recharger. Vérifier aussi `core.file_analyzer` (cartes d'analyseurs avec
variante), `aws.rekognition` (groupes activables, liste d'objets `attributes`), `core.watermark`
(liste de renditions en sélecteur multiple), `core.webhook` (`options` en YAML), et qu'une
intégration existante écrite en YAML se rouvre en mode formulaire avec ses valeurs (`configValues`).

## Hors périmètre

- Caviardage des secrets dans `configYaml` / `configValues` / `configInfo` (comportement actuel
  conservé) ; gestion des secrets/variables de workspace via l'API (EasyAdmin seulement).
- Création inline de renditions et de collections (sélection seule).
- Correction du double `max_severity` de `FileAnalyzerConfigHelper::createBaseTree`, du texte
  d'info copié-collé de `budgetLimit.interval`, du nœud `webhook.options` vide, et de la fixture
  `nb_wi_face_recognition` (`databox/api/fixtures/NorthbeamPress.yaml`) sans `config.attribute` :
  à signaler dans le rapport, pas à corriger.
- Validation côté client au-delà des bornes natives (`min`/`max`/`required` HTML) : l'API reste
  la source de vérité, ses violations sont rapportées par champ.
- Page d'aide EasyAdmin `/admin/integrations/help` et `reference` textuel : inchangés.

## Interactions avec la file

Plans en cours : `008-run-automation-from-saved-search-menu` (features/saved-searches) et
`009-empecher-tri-id` (databox/api Elasticsearch). Aucun fichier commun hors `en.json`/`fr.json`
(008 ajoute des clés dans d'autres blocs : conflit trivial à l'intégration). `depends_on: []`.
