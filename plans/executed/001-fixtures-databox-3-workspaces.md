---
id: 001-fixtures-databox-3-workspaces
title: Fixtures Databox – 3 workspaces métier thématiques
status: executed
finished: 2026-10-19 14:56
started: 2026-10-19 11:24
run_base: bd9d98b44e664cf3b0e6b4dea62d54e6d4d3131d
created: 2026-10-19 10:42
branch: next
depends_on: []
---

# Fixtures Databox : 3 workspaces métier thématiques

## Contexte

Les fixtures dev de Databox (`databox/api/fixtures/`, chargées par `hautelook:fixtures:load` via
`bin/dev/reset-db-fixtures.sh`) sont hétérogènes et techniques :

- `Newspaper.yaml` : seul workspace riche (attributs de tous types, stories, annotations, géoloc,
  listes d'entités traduites, intégrations), mais collections génériques (Sport, Entertainment…),
  noms « Image #12 ».
- `Marketplace.yaml` : quasi vide (2 collections, renditions recréées à la main).
- `Permissions.yaml` : hiérarchie A/B/C/D purement technique, et **ses fichiers pointent sur
  `@w_newspaper`** (dépendance croisée).
- Aucune fixture ne crée d'ACE, d'AttributeFilterRule ni d'AssetPolicy.

Objectif : remplacer ces 3 fichiers par **3 workspaces réalistes, de typologies différentes**, avec
des noms d'entreprise inventés en anglais, chacun avec ses collections, assets, attributs,
intégrations et règles (permissions, politiques, règles de filtrage/masquage, tags, listes
d'entités) cohérents avec son métier. Les cas techniques utiles des anciennes fixtures doivent
survivre, redistribués dans les nouveaux workspaces.

## Décisions prises avec l'utilisateur

- **Tout remplacer** : supprimer `Newspaper.yaml`, `Marketplace.yaml`, `Permissions.yaml`.
- **« Règles » = les quatre familles** : permissions (ACE), politiques d'attributs/renditions
  (AttributePolicy/RenditionPolicy publiques + restreintes), règles d'attributs/renditions
  (`AttributeFilterRule`, `AssetPolicy`), tags & listes d'entités (EntityList/AttributeEntity
  traduites fr/en).
- **Visuels thématiques via le bucket S3** : le paramètre `theme` d'`imageUrlRandomRatio` est
  utilisé par workspace. L'utilisateur déposera lui-même les images sur
  `s3://alchemy-phrasea-fixtures/fixtures/images/<theme>/{landscape,portrait}/{1..42}.jpg`
  (thèmes `reportage` et `fashion` ; `nature` existe déjà). En attendant, le faker **retombe sur
  `nature`** si l'image du thème est absente (403/404), pour que les fixtures se chargent
  toujours.
- Noms d'entreprises, collections, assets, tags, valeurs : **en anglais**, avec traductions `fr`
  là où l'entité le permet (EntityList `withTranslations`, attributs `translatable`).

## Les 3 workspaces

Utilisateurs Keycloak (créés à la volée par `keycloakUser()`) : `phrasea-admin` (propriétaire
des 3 workspaces), `alice`, `bob`, `carol`, `dave` (nouveau). Chaque workspace leur attribue un
rôle métier différent, via ACE (masques de `vendor/alchemy/acl-bundle` : VIEW 1, CREATE 2, EDIT 4,
DELETE 8, OPERATOR 32, MASTER 64, OWNER 128, SHARE 256 ; CHILD_* = bit << 9).

### 1. Northbeam Press — agence de presse / newsroom (remplace Newspaper)

- Workspace `w_northbeam`, slug `northbeam-press`, locales `en`, `fr`, thème images `reportage`.
- **Typologie** : flux éditorial multi-média, géolocalisé, sous embargo, multilingue.
- **Collections** :
  `Desks` > `World`, `Politics`, `Sports` (> `Football` > `Premier League`, `World Cup` ;
  `Tennis` ; `Rugby`), `Culture` (> `Film`, `Music`) ;
  `Archives` > `2024`, `2025`, `2026` ; `Embargoed` (privacy 0) ;
  `Wire Feed` (privacy 2) ; une collection au nom piégeux
  `Q&A "Live" — D&D \@][ \<ap>++"=` (reprend `c_n_special_chars`).
  Sous-collections de `Culture` détenues par `alice` (comme l'ancien `public_videos`).
- **Assets** : ~50 photos, 10 vidéos, 10 audios (« podcast »), 10 PDF (« press release »),
  10 stories « Photo essay » regroupant 20 images (stories : `storyCollection`, collections
  `name: null`). Noms réalistes générés (ex. `'<sentence(4)>'` ou listes `<randomElement([...])>`
  de titres de reportage), plus « Image #n ».
- **Attributs** (policy Public sauf mention) : Headline, Caption (textarea, translatable),
  Byline (initialValues depuis EXIF/XMP comme l'ancien `ad_author`), Keywords (multiple, facet,
  suggest, avec annotations rect/circle/cue/target), Location (geo_point), City, Country
  (`geoPlace*`), Date taken (date), Published at (date_time, sortable), Desk (entity),
  Usage rights (entity), Embargo until (date_time), Word count (number), Duration, File size,
  Breaking (boolean), WebVTT transcript (code, translatable, policy **Newsroom only**),
  Editor notes (policy **Newsroom only**), Watermark, Blurhash, Story title (target Story).
- **Listes d'entités** : Desk (traductions + synonymes), Usage rights (Editorial use only,
  Royalty free, Rights managed, Do not syndicate ; couleurs).
- **Tags** : Breaking (rouge), Exclusive, Embargo, Online (vert), Offline.
- **Politiques** : AttributePolicy Public (factory) + `Newsroom only` (non publique, non
  éditable) ; RenditionPolicy Public (factory) + `Print` (restreinte) avec une
  RenditionDefinition `Print hi-res` (parent `main`, policy Print).
- **Intégrations** : rendition (factory), `tui.photo-editor`, `blurhash` (rendition thumbnail),
  `core.watermark` (attribut Watermark sur thumbnail), `core.moderation` (emails
  `desk@northbeam.local`), `aws.transcribe` **désactivée** (`enabled: false`, secrets
  `WorkspaceSecret` factices `xxx` comme aujourd'hui).
- **Règles** :
  - ACE : `alice` = rédactrice en chef (workspace EDIT|OPERATOR + CHILD_*),
    `bob` = photographe (CREATE|EDIT sur `Desks`), `carol` = pigiste (VIEW sur `Sports`
    uniquement), `dave` = documentaliste (VIEW+EDIT sur `Archives`).
  - AttributeFilterRule : pour `carol`, exclure les assets taggés Embargo (condition AQL,
    syntaxe dans `doc/tech/Databox/aql.md`, exemple dans
    `databox/api/tests/functional/Api/Attribute/AttributeFilterRuleApiTest.php`).
  - AssetPolicy « Embargo masking » : condition collection = `Embargoed`, actions
    `hide_rendition` Print hi-res + `hide_attribute` Editor notes, ciblant `carol` et `dave`
    (entités `AssetPolicyUser`).

### 2. Linden & Rowe — marque de prêt-à-porter / catalogue e-commerce (remplace Marketplace)

- Workspace `w_linden`, slug `linden-rowe`, locales `en`, `fr`, `it`, thème images `fashion`.
- **Typologie** : catalogue produit type PIM, saisons, déclinaisons, prix confidentiels,
  diffusion presse publique.
- **Collections** :
  `Seasons` > `SS26` (> `Womenswear` > `Dresses`, `Knitwear` ; `Menswear` > `Shirts`,
  `Outerwear` ; `Accessories` > `Bags`, `Footwear`), `FW26 (unreleased)` (privacy 0, même
  sous-arbre réduit) ;
  `Campaigns` > `SS26 – Coastline`, `FW26 – Night Garden` ; `Lookbooks` ;
  `Press Kit` (privacy 5, publique) ; `Retired`.
- **Assets** : ~60 packshots, ~15 photos de campagne, 6 vidéos (« runway »), 10 PDF
  (« spec sheet », « lookbook »), 6 stories « Look » (tenue = plusieurs packshots).
- **Attributs** : Product name, SKU (keyword, sortable, ex. `'<bothify("LR-####-??")>'`),
  EAN (`<ean()>`), Colour (entity `withColors`), Sizes (entity multiple), Material (entity
  traduit), Gender (entity), Season (entity), Launch date (date), Rights expiry (date_time),
  Model release signed (boolean), Photographer, Retail price (number, policy **Commercial**),
  Wholesale price (number, policy **Commercial**), Marketing copy (html, translatable).
- **Listes d'entités** : Colour (couleurs hex), Size (XS…XXL, 36…46), Material (traductions fr/it
  + synonymes), Gender, Season.
- **Tags** : New in, Bestseller, Sale, Sustainable, Do not publish.
- **Politiques** : AttributePolicy Public + `Commercial` ; RenditionPolicy Public + `Studio`
  avec RenditionDefinition `E-commerce zoom` (restreinte).
  Ne plus recréer main/preview/thumbnail à la main comme `Marketplace.yaml` : passer par
  `WorkspaceFactory`.
- **Intégrations** : rendition (factory), `tui.photo-editor`, `blurhash`, `remove.bg`
  **désactivée** (`apiKey: ${REMOVE_BG_API_KEY}`), `core.webhook` **désactivée**
  (url `https://pim.linden-rowe.example/hooks/databox`).
- **Règles** :
  - ACE : `bob` = studio photo (CREATE|EDIT sur `Seasons`), `alice` = marketing (EDIT sur
    `Campaigns`, `Lookbooks`, `Press Kit`), `carol` = partenaire wholesale (VIEW sur `SS26` et
    `Press Kit`), `dave` = e-commerce (VIEW workspace + CHILD_VIEW).
  - AttributeFilterRule : pour `carol`, exclure le tag Do not publish.
  - AssetPolicy « Press kit » : collection = `Press Kit` → `hide_attribute` Retail price et
    Wholesale price, `hide_rendition` E-commerce zoom.

### 3. Fernhollow Natural History Museum — musée / archives patrimoniales (remplace Permissions)

- Workspace `w_fernhollow`, slug `fernhollow-museum`, locales `en`, `fr`, thème `nature`
  (images déjà disponibles).
- **Typologie** : inventaire patrimonial, arborescence profonde, conservation, prêts, droits.
  Reprend le rôle de banc d'essai des permissions (hiérarchie ≥ 4 niveaux, privacies 0…5
  mélangées, collections de propriétaires différents).
- **Collections** :
  `Departments` > `Botany` > `Herbarium` > `Ferns` > `Rare Ferns` ;
  `Entomology` > `Lepidoptera`, `Coleoptera` ; `Ornithology` ; `Geology` > `Minerals` ;
  `Exhibitions` > `Wild Coasts (2025)`, `Under the Canopy (2026)` ;
  `Conservation Lab` (privacy 0) ; `Outgoing Loans` (privacy 1) ;
  `Open Collection` (privacy 5). Privacies variées sur les branches, certaines détenues par
  `alice`.
- **Assets** : ~50 photos de spécimens, 15 PDF (« field notebook », « accession record »),
  6 audios (« audio guide »), 4 vidéos, 6 stories « Specimen record » (vues
  face/dos/étiquette). Au moins un asset par niveau de la hiérarchie (comme
  `Permissions.yaml`).
- **Attributs** : Inventory number (keyword, sortable), Scientific name, Common name
  (translatable), Family (entity), Conservation status (entity IUCN LC/NT/VU/EN/CR avec emojis
  et couleurs), Collected on (date), Collector, Collection site (geo_point) + City + Country,
  Accession date (date_time), Licence (entity CC BY, CC BY-NC, All rights reserved),
  Condition report (textarea, policy **Curatorial**), Insurance value (number, policy
  **Curatorial**), On display (boolean).
- **Tags** : On display, In storage, On loan, Needs digitisation, Fragile.
- **Politiques** : AttributePolicy Public + `Curatorial` ; RenditionPolicy Public + `Archive`
  avec RenditionDefinition `Archival master` (restreinte).
- **Workspace** : `attachTermsToExports` activé + un `TermsVersion` (« Fernhollow image licence
  terms », traduit fr).
- **Intégrations** : rendition (factory), `blurhash`, `core.watermark` (crédit « © Fernhollow
  Museum » sur preview), `aws.rekognition` **désactivée** (labels → Keywords).
- **Règles** :
  - ACE : `alice` = conservatrice (EDIT|DELETE + CHILD_* sur `Departments`), `bob` = chercheur
    (VIEW + CHILD_VIEW sur `Departments`, rien sur `Conservation Lab`), `carol` = visiteuse
    (VIEW sur `Open Collection`), `dave` = régisseur (EDIT sur `Outgoing Loans`).
  - AttributeFilterRule : pour `carol` et `bob`, exclure Conservation status = CR (protéger les
    espèces en danger critique).
  - AssetPolicy « Rare species location » : collection = `Rare Ferns` → `hide_attribute`
    Collection site ; AssetPolicy « Loans » : collection = `Outgoing Loans` → `hide_rendition`
    Archival master.

### Commun

- Intégration globale `phrasea.expose` (sans workspace, `clientId:
  \${DATABOX_EXPOSE_INTEGRATION_CLIENT_ID}`), comme l'actuel `wi_expose`.
- Cas techniques à conserver (vérifier leur présence quelque part) : stories, nom de collection
  à caractères spéciaux, annotations 4 types, `largeJSONFileMetadata()` sur les fichiers d'un
  workspace (Northbeam), `pathPublic: '95%? true : false'`, renditions animatedThumbnail sur
  ~10 images, assets multi-collections (`addToCollection` aléatoires), propriétaires
  alice/bob/carol, tous les types d'attributs utilisés aujourd'hui (boolean, number, date,
  date_time, code, filesize, duration, geo_point, entity, multiple, translatable), assets sans
  géoloc (PDF/audio) pour la carte.

## Fichiers concernés

- `databox/api/fixtures/Newspaper.yaml`, `Marketplace.yaml`, `Permissions.yaml` — **supprimés**.
- `databox/api/fixtures/NorthbeamPress.yaml`, `LindenRowe.yaml`, `FernhollowMuseum.yaml` —
  **créés** (un workspace complet par fichier, préfixes de clés distincts `nb_`, `lr_`, `fh_`
  pour éviter toute collision entre fichiers chargés ensemble).
- `databox/api/fixtures/Common.yaml` — **créé** : intégration globale Expose.
- `lib/php/core-bundle/Fixture/Faker/ImageUrlFaker.php` — repli sur `nature` quand l'URL du
  thème demandé est indisponible (tester l'URL du thème avant `downloadAndStore`, ou attraper
  l'échec de `download()` dans `AbstractCachedFaker`) ; le cache local doit être indexé par le
  thème effectivement utilisé.
- `databox/api/src/Fixture/Faker/` — éventuellement un petit provider de libellés thématiques
  (titres de reportage, noms de produits, noms d'espèces) si `randomElement` inline devient
  illisible ; sinon rien.
- `doc/tech/Databox/map.md` — remplacer la mention du workspace Newspaper.
- `doc/tech/Databox/fixtures.md` — **créé** : présentation des 3 workspaces, des personas
  (qui voit quoi), et procédure d'ajout d'un thème d'images sur le bucket (chemins, 42
  variantes par orientation, repli `nature`). Lier depuis `doc/tech/01_setup.md` (section
  reset-db-fixtures).

## Étapes

1. Lire `databox/api/fixtures/Newspaper.yaml` et `Permissions.yaml` pour l'inventaire des cas
   techniques à conserver ; lire `src/Fixture/WorkspaceFactory.php`,
   `src/Service/Workspace/WorkspaceCreator.php` (clés `main`, `preview`, `thumbnail`,
   `animatedThumbnail` ; intégrations `readMetadata`, `rendition`, `fileAnalyzer`,
   `baseRenditions`).
2. Vérifier les formes exactes des entités de règles :
   `Alchemy\AclBundle\Entity\AccessControlEntry` (`objectType` est une **chaîne** :
   `workspace`, `collection`… — `tests/fixtures/test.yaml` utilise `0`, ne pas le copier),
   `App\Entity\Core\AttributeFilterRule` (`addTarget(int $userType, string $userId)`,
   `condition` AQL validée), `App\Entity\Core\AssetPolicy\AssetPolicy` + `AssetPolicyUser`,
   et la valeur attendue par la condition `collection` dans
   `src/Service/Asset/AssetPolicy/AssetPolicyManager.php` (`str_contains` sur
   `getReferenceCollection()->getAbsolutePath()` : vérifier si le chemin absolu est fait d'ids
   ou de noms, et fournir `'@c_x->id'` ou le nom en conséquence ; s'assurer que les assets
   concernés ont bien cette collection comme collection de référence).
3. Implémenter le repli de thème dans `ImageUrlFaker` (sans casser expose, qui appelle sans
   thème, ni `FIXTURES_GENERATE_IMAGES=true`).
4. Écrire `FernhollowMuseum.yaml` (thème `nature`, dispo tout de suite) en premier, le charger,
   corriger ; puis `NorthbeamPress.yaml`, `LindenRowe.yaml`, `Common.yaml`.
   - Workspace via `__factory: { '@App\Fixture\WorkspaceFactory::create': [...] }` ; policies,
     définitions et intégration rendition par défaut récupérées via les getters de la factory.
   - Chaque `File` porte le `workspace` de son asset et
     `imageUrlRandomRatio(@w_x->id, <current()>, <taille>, "<theme>")`.
   - Volume total visé ≈ 250 assets (temps de chargement raisonnable).
5. Supprimer les 3 anciens fichiers.
6. Documentation (`fixtures.md`, `map.md`, lien depuis `01_setup.md`).
7. `composer cs` / `phpstan` sur `lib/php/core-bundle` (et databox/api si du PHP y est ajouté).

## Vérification

Sans environnement partagé (sûr en worktree) :

```bash
dc run --rm databox-api-php su app -c "bin/console lint:yaml fixtures"
dc run --rm databox-api-php su app -c "composer phpstan"
```
et `composer test` dans `lib/php/core-bundle` si le faker est modifié (voir
`bin/vars.sh` / `bin/test.sh quick` pour la commande exacte des libs PHP).

**Exige la stack docker partagée et efface les bases dev** — à ne lancer qu'après intégration
et avec l'accord de l'utilisateur :

```bash
FIXTURES_GENERATE_IMAGES=true bin/dev/reset-db-fixtures.sh   # chargement rapide, images grises
bin/dev/reset-db-fixtures.sh                                 # vraies images (réseau requis)
```

Puis contrôler dans le client databox (connecté en `phrasea-admin`, puis en impersonnant
`carol`, cf. mémoire « Token utilisateur dev ») :
- 3 workspaces visibles avec leurs arborescences, facettes (Desk, Colour, Conservation status…)
  et la carte (Northbeam, Fernhollow) ;
- `carol` ne voit pas les assets Embargo / Do not publish / CR, ni Retail price dans Press Kit,
  ni Collection site dans Rare Ferns ;
- `fos:elastica:populate` (lancé par le script) sans erreur.

## Hors périmètre

- Téléversement des images `reportage` et `fashion` sur le bucket S3 (fait par l'utilisateur ;
  le repli `nature` couvre l'attente).
- Fixtures de test (`databox/api/tests/fixtures/test.yaml`, y compris son ACE `objectType: 0`),
  seed Cypress (`cypress/cypress/e2e/databox/lib/api.js`), fixtures expose/uploader.
- Création de groupes Keycloak (les règles ciblent des utilisateurs).
- Workflows (définis en YAML dans `config/workflows/`, pas en fixtures).

## Interactions avec la file

Aucun plan en attente dans `plans/` : pas de recouvrement, `depends_on: []`.
