---
id: 005-automation-remote-export
title: Automations – action export S3/SFTP/FTP
status: executed
finished: 2026-10-19 17:22
started: 2026-10-19 15:02
run_base: 361dea3ad53bafaec8a54e2705ecc48c8f7976c9
created: 2026-10-19 11:27
branch: next
depends_on: [003-saved-search-automations-core]
---

# Automations – action export S3/SFTP/FTP

Plan d'une série de 4 (automations sur les saved searches) : 003 socle, puis 004 rapport email, 005 export distant, 006 webhook/workflow/corbeille/privacy (004-006 dépendent de 003). Les sections communes ci-dessous valent pour les quatre ; la section propre à ce plan suit.

## Contexte commun

Une saved search (`databox/api/src/Entity/SavedSearch/SavedSearch.php`) stocke une recherche d'assets
(`data` JSON opaque : `{query, conditions: AQLQuery[], sortBy}`), avec un `ownerId`, une `privacy` et des
ACL (`SavedSearchVoter`). Côté client, la page de gestion
`databox/client/src/features/saved-searches/manage/SavedSearchManageRoute.tsx` a déjà un onglet
**automations** placeholder (`AutomationsTab`, « Automations are coming soon »).

Objectif : attacher à une saved search des **automations** = une liste ordonnée d'**actions**,
déclenchée **à la main** ou **planifiée (cron)**, qui s'applique soit à **tous** les résultats, soit aux
**nouveaux résultats depuis le run précédent** (curseur). Exemples : déplacer les assets, changer une
valeur d'attribut, envoyer un rapport par mail des nouveaux assets, déposer les nouveaux assets sur
S3/SFTP/FTP, appeler un webhook, déclencher un workflow, mettre à la corbeille, changer la privacy.

### Ce qui existe et qu'on réutilise

- **Recherche** : `App\Elasticsearch\AssetSearch` (`databox/api/src/Elasticsearch/AssetSearch.php`).
  `search(?string $userId, array $groupIds, array $options)` (l.63) ; `buildQuery()` privé (l.235)
  fusionne `SavedSearch::getData()` quand `$options['savedSearch']` est fourni (l.244-248) ;
  `createBaseFilterQueries()` (l.385). Post-filtrage `isGranted(READ)` via `FilteredPager` →
  **dépend du token courant**. Pagination from/size plafonnée (limit 50, fenêtre ES 10 000) : **aucun
  utilitaire n'itère sur tous les résultats** (pas de scroll / PIT / search_after). ES 7.17,
  ruflin/elastica 9, index injecté `fos_elastica.index.asset`.
- **Identité hors requête HTTP** : `Alchemy\AuthBundle\Security\Impersonator::getIdentity(string $id)`
  (`lib/php/auth-bundle/Security/Impersonator.php`) renvoie `{username, roles (IdP), groups}` en
  interrogeant Keycloak sans cache, et refuse un utilisateur absent/désactivé ;
  `RoleMapper::getRoles()` mappe les rôles IdP en rôles Symfony ; `JwtUser` / `JwtToken`
  (`lib/php/auth-bundle/Security/`). Modèle d'échange de token :
  `lib/php/webhook-bundle/Webhook/ObjectNormalizer.php:27-40` (sauver le token, `setToken`, restaurer).
- **Actions batch existantes** (vérifient les droits via le token courant) :
  `Api/Processor/MoveAssetProcessor.php` + message `AssetMove` (p1) ;
  `AddAssetsToCollectionProcessor.php` ; `Attribute/BatchAttributeManager::validate()` /
  `handleBatch(string $workspaceId, ?array $assetsId, AssetAttributeBatchUpdateInput $input, ?JwtInterface $userOrClient, bool $dispatchUpdateEvent)`
  (actions `set|add|delete|replace`, tags via `AttributeValidator::TAGS`) ;
  `AssetsDelete` (`Consumer/Handler/Asset/AssetsDelete.php`, p1) ;
  `Api/Processor/TriggerAssetWorkflowProcessor.php` ; privacy via `WorkspacePrivacyTrait`.
- **Secrets** : `App\Security\Secrets\SecretsManager::encryptSecret()/decryptSecret()` (sealed box ;
  `SECRETS_SECRET_KEY` n'est défini **que sur `databox-worker`** → déchiffrement uniquement en async).
- **Notifications** : `lib/php/notifier-bundle` (`NotifierManager::notifyUser/notifyUsers/notifyEmail/notify`),
  topics déclarés dans `databox/api/config/packages/alchemy_notifier.yaml`, templates
  `databox/api/templates/notifications/<object>/<action>/{email.html.twig,in_app.html.twig}`,
  traductions `databox/api/translations/notifications.<locale>.yaml`.
- **Fichiers** : `App\Service\Asset\FileFetcher::getFile(File $file): string` ; sélection de
  renditions + ACL dans `Consumer/Handler/Asset/AssetExportProcessHandler.php`.
- **Messenger** : transports AMQP `p1`, `p2`, `task` (`config/packages/messenger.yaml`, `sync://` en dev,
  `test://` en test) ; routage par `#[MessengerMessage('task')]`. Worker : supervisor
  `messenger:consume ${WORKER_PRIORITIES}` (`databox/api/Dockerfile:92`, `docker-compose.yml:786` =
  `p1 p2 task`). Lock Symfony sur Redis déjà configuré (`config/packages/lock.yaml`).
  **Pas de `symfony/scheduler`** ni de `dragonmantank/cron-expression` aujourd'hui.

## Décisions prises avec l'utilisateur (ne pas redemander)

1. **Moteur dédié**, pas le moteur de workflow : entités `SavedSearchAutomation`, `AutomationRun`,
   `AutomationSeenAsset` ; actions = services tagués exécutés par lots dans un handler Messenger.
2. **Planification par Symfony Scheduler** : une `RecurringMessage` chaque minute, verrouillée (lock
   Redis) et stateful, qui lance les automations dues (`nextRunAt <= now`). Transport
   `scheduler_automation` ajouté aux `WORKER_PRIORITIES` du worker databox. Une commande
   `app:automation:run-due` appelle le même service (dev, secours cron).
3. **Curseur = ensemble des assets déjà vus** (table `automation_seen_asset`). Nouveaux = résultats
   actuels − vus. Capte aussi les assets entrés dans la recherche après leur création.
4. **Identité d'exécution = propriétaire de la saved search** (`SavedSearch::ownerId`), résolu à chaque
   run via `Impersonator::getIdentity()` + `RoleMapper` (droits actuels, jamais un snapshot).
   Conséquence de sécurité : **seul le propriétaire de la saved search (ou un admin) peut
   créer/modifier/lancer/voir les automations** — un simple éditeur ACL ne doit pas pouvoir agir avec les
   droits du propriétaire.
5. **Identifiants des destinations chiffrés dans la config de l'action** via `SecretsManager`, jamais
   renvoyés par l'API (sortie : `null` + indicateur « défini »), champ laissé vide en PATCH = valeur
   conservée.
6. **Actions V1** : toutes les familles proposées — collections + attributs + tags (003), rapport email
   (004), export S3/SFTP/FTP (005), webhook + workflow + corbeille + privacy (006).
7. Découpage en 4 plans chaînés (004/005/006 `depends_on: [003]`).

## Décisions de conception (prises dans ce plan)

- Une automation = `{name, enabled, schedule (cron nullable = manuel seulement), timezone, scope: new|all, actions: [{type, config}] ordonnées}`. Le déclenchement manuel est toujours possible.
- Intervalle minimal entre deux occurrences cron : **5 minutes** (validation à l'écriture).
- **Un seul run à la fois par automation** (lock `automation_run_<id>`) : un déclenchement concurrent
  crée un run au statut `skipped`.
- **Curseur et échecs** : un asset n'est marqué « vu » qu'après le succès de **toutes** les actions sur
  lui. Un asset en échec reste « nouveau » et sera retenté au run suivant → les actions doivent être
  **idempotentes** (ou tolérer la répétition).
- Un asset qui sort puis revient dans les résultats **n'est pas** retraité (le « vu » est définitif).
  Endpoint `reset-cursor` : `mode=empty` (tout redevient nouveau) ou `mode=current` (marque tous les
  résultats actuels comme vus sans agir — proposé dans l'UI à la création d'une automation en scope
  `new` : « ignorer les assets existants »).
- **Dry run** : déclenchement manuel avec `dryRun: true` → compte matched/new, n'exécute aucune action, ne
  touche pas au curseur.
- Changement de propriétaire d'une saved search → ses automations sont désactivées.
- Propriétaire introuvable/désactivé dans Keycloak, ou 5 runs planifiés consécutifs en échec →
  automation désactivée + notification in-app au propriétaire.
- Itération ES : `Elastica\Search::scroll('2m')` (snapshot cohérent), `_source: false`, lots de 500 ids,
  puis chargement Doctrine par lot de 200 et filtre `isGranted(AssetVoter::READ)` sous le token
  emprunté. L'ordre stocké (`sortBy`) est ignoré (inutile pour agir).

---

## Plan 005 — Action « export distant S3 / SFTP / FTP » (`005-automation-remote-export`)

`depends_on: [003-saved-search-automations-core]`

### Fichiers concernés

- `databox/api/composer.json` : `league/flysystem-sftp-v3`, `league/flysystem-ftp` (`league/flysystem-aws-s3-v3` déjà présent).
- `databox/api/Dockerfile` (+ `infra/docker/php-fpm-base/Dockerfile` si l'extension doit être commune) : `docker-php-ext-install ftp` (avec support SSL pour FTPS ; ajouter `openssl-dev` aux build-deps si nécessaire). **Exige un rebuild des images** (`bin/build.sh`).
- `databox/api/src/Automation/Action/RemoteExportAction.php` (`remote_export`) : config
  `{protocol: s3|sftp|ftp, renditions: string[] (noms de définitions ; vide = fichier source), pathTemplate (défaut "{collection}/{assetId}-{rendition}.{ext}", variables : assetId, title (slugifié), rendition, ext, originalName, workspace, date), overwrite: bool, s3: {bucket, region, endpoint?, pathStyle?, accessKeyId, secretAccessKey}, sftp: {host, port=22, username, password?, privateKey?, passphrase?, root}, ftp: {host, port=21, username, password, ssl: bool, passive: bool=true, root}}`.
  - `getSecretFields()` : `s3.secretAccessKey`, `sftp.password`, `sftp.privateKey`, `sftp.passphrase`, `ftp.password`.
  - `src/Automation/Export/RemoteFilesystemFactory.php` : construit un `League\Flysystem\Filesystem` par protocole (S3Client à la manière de `src/Service/Storage/S3Copier.php`).
  - Par asset : sélection des renditions et contrôle des droits de rendition (reprendre la logique de `AssetExportProcessHandler.php`, sous le token emprunté) ; `FileFetcher::getFile()` puis `writeStream` ; rendition absente → `skipped` pour cette rendition ; échec réseau → asset en échec (retenté au run suivant, cf. décision curseur). `overwrite=false` + fichier existant → considéré comme succès (idempotence).
  - Le filesystem est créé une fois par run (dans `state.data`) et fermé dans `finish()`.
  - Validation : protocole connu, champs requis par protocole, `pathTemplate` sans `..`. Les secrets ne pouvant être déchiffrés que dans le worker, **pas** de « tester la connexion » synchrone.
- Client : `actions/RemoteExportForm.tsx` (onglets par protocole, champs secrets en `password` avec placeholder « inchangé » quand `<field>Set`), sélection des renditions (liste des définitions de rendition existante), entrée dans le registre, i18n en/fr.
- Tests : `RemoteExportActionTest` avec un adaptateur Flysystem en mémoire/local injecté via la factory (pas de réseau) ; validation de config ; masquage des secrets dans la sortie API ; chiffrement effectif en base.

### Vérification
```bash
dc run --rm databox-api-php su app -c "composer phpunit:compact -- --filter RemoteExport" 2>&1 | tail -40
```
Après rebuild (environnement partagé) : `dc run --rm databox-api-php php -m | grep -i ftp`. Manuel : export vers le MinIO du compose (S3 compatible, `endpoint` + `pathStyle`).

### Hors périmètre
Azure/GCS/WebDAV, export ZIP unique, suppression distante des assets sortis des résultats, test de connexion synchrone.



---

## Interactions avec la file

- Plans en attente sans lien fonctionnel : `001-fixtures-databox-3-workspaces`, `001-readme-open-source`, `002-dupliquer-definitions-integrations`, `002-matomo-databox-client`. Recouvrements :
  - `databox/client/src/i18n/locales/{en,fr}.json` : touchés par les deux plans 002 et par 003-006 → conflits d'ajout de clés triviaux.
  - `002-matomo-databox-client` instrumente de nombreux composants client (dont `features/saved-searches/SaveSearchDialog.tsx`, `features/search/*`) ; s'il est intégré avant, ajouter le tracking des automations (création, run manuel) selon ses conventions (`databox/client/src/lib/analytics/events.ts`) ; sinon rien.
  - `002-dupliquer-definitions-integrations` touche `src/Integration/*` et `src/lib/api/misc.ts` ; la série automations ne modifie pas ces fichiers (nouveau `lib/api/automations.ts`).
- 004, 005 et 006 dépendent de 003 et se recouvrent entre eux uniquement sur
  `databox/client/src/features/saved-searches/automations/actions/registry.tsx` (une entrée chacun) et les fichiers i18n : conflits triviaux à résoudre à l'intégration.
  005 touche seul au `Dockerfile` (ext-ftp) ; 003 et 005 ajoutent des dépendances composer → `composer.lock` à régénérer depuis la base intégrée de 003 en cas de conflit.
