---
id: 003-saved-search-automations-core
title: Automations sur les saved searches – socle
status: executed
finished: 2026-10-19 15:02
started: 2026-10-19 11:49
run_base: bd9d98b44e664cf3b0e6b4dea62d54e6d4d3131d
created: 2026-10-19 11:27
branch: next
depends_on: []
---

# Automations sur les saved searches – socle

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

## Plan 003 — Socle des automations (`003-saved-search-automations-core`)

`depends_on: []`

### Fichiers concernés

API (`databox/api/`) :
- `composer.json` / `composer.lock` / `symfony.lock` : `symfony/scheduler`, `dragonmantank/cron-expression`.
- `src/Entity/Automation/SavedSearchAutomation.php` (nouveau) : uuid, `savedSearch` (ManyToOne, `onDelete: CASCADE`), `name`, `enabled`, `schedule` (?string), `timezone` (string, défaut `UTC`), `scope` (enum `AutomationScopeEnum`: `new`, `all`), `actions` (JSON), `nextRunAt`, `lastRunAt`, `lastRunStatus`, `consecutiveFailures`, `createdBy` (?string userId, audit), Created/UpdatedAt traits. ApiResource `shortName: 'saved-search-automation'`.
- `src/Entity/Automation/AutomationRun.php` (nouveau) : uuid, `automation` (CASCADE), `trigger` (`manual|schedule`), `dryRun`, `initiatorId`, `status` (`pending|running|success|partial|failed|skipped|cancelled`), `startedAt`, `endedAt`, `matchedCount`, `newCount`, `processedCount`, `failedCount`, `actionResults` (JSON : par action `{type, status, processed, failed, skipped, errors: [{assetId?, message}] (≤100)}`), `error` (?text). ApiResource `shortName: 'automation-run'` (Get, GetCollection filtré par `automation` obligatoire, tri `startedAt desc`).
- `src/Entity/Automation/AutomationSeenAsset.php` (nouveau) : clé composite (`automation` CASCADE, `asset` CASCADE), `firstSeenAt`, `runId`.
- `src/Repository/Automation/*Repository.php` : `findDue(\DateTimeImmutable $now)`, insertion en masse des vus par DBAL (`INSERT ... ON CONFLICT DO NOTHING`, compatible Postgres et SQLite des tests), `filterUnseen(automationId, string[] $assetIds): string[]`, `reset()`.
- `src/Model/AutomationScopeEnum.php`, `AutomationRunStatusEnum.php`, `AutomationTriggerEnum.php`.
- `src/Automation/Action/AutomationActionInterface.php` (nouveau) — contrat des actions :
  - `#[AutoconfigureTag('app.automation_action')]`, index par `#[AsTaggedItem(index: self::TYPE)]` dans chaque implémentation (règle CLAUDE.md : pas de `defaultIndexMethod`, une constante `TYPE` partagée).
  - `validateConfig(array $config): array` (normalise + valide, lève une `ValidationException`/violations ; les validations de droits — collection cible, définition d'attribut — se font ici **avec le token courant = propriétaire**).
  - `getSecretFields(): string[]` (chemins de clés chiffrées, vide par défaut).
  - `processBatch(AutomationBatch $batch): void` — reçoit les assets du lot, la config déchiffrée, et un `AutomationActionState` (compteurs, erreurs par asset, stockage libre `array $data` qui persiste sur tout le run en mémoire).
  - `finish(AutomationActionState $state, AutomationRunContext $context): void` — appelé une fois après le dernier lot (même si 0 asset), pour les actions agrégées (rapport, webhook récapitulatif).
  - Une classe abstraite `AbstractAutomationAction` fournit les défauts (finish no-op, pas de secrets).
- `src/Automation/AutomationActionRegistry.php` : `ServiceLocator` des actions par type ; liste des types pour l'API.
- `src/Automation/ActionConfigSecretsHandler.php` : chiffre à l'écriture les champs `getSecretFields()` (conserve la valeur chiffrée existante si l'input est `null`/`''`), masque en sortie (`null` + `"<field>Set": true`), déchiffre dans le worker.
- `src/Automation/UserContextSwitcher.php` : `runAs(string $userId, callable $fn): mixed` — `Impersonator::getIdentity()` + `RoleMapper::getRoles()` → `JwtUser` + `JwtToken` placés dans `TokenStorageInterface`, restauration du token précédent en `finally`. JWT factice (`'automation'`), scopes vides.
- `src/Automation/AutomationAssetIterator.php` : à partir de `AssetSearch`, ajouter une méthode publique `AssetSearch::iterateIds(?string $userId, array $groupIds, array $options, int $batchSize = 500): \Generator<string[]>` qui réutilise `buildQuery()` (ACL ES incluse) puis scroll Elastica ; l'itérateur charge les `Asset` par lot de 200 (`AssetRepository`), filtre `isGranted(READ)`, et en mode `new` retire les vus.
- `src/Automation/AutomationRunner.php` :
  - `trigger(SavedSearchAutomation $a, AutomationTriggerEnum $trigger, ?string $initiatorId, bool $dryRun = false): AutomationRun` — crée le run `pending`, dispatch `RunAutomation`.
  - `run(AutomationRun $run)` — lock, `runAs(ownerId)`, itération, exécution ordonnée des actions par lot, marquage des vus (assets sans échec), `finish()` de chaque action, statut final (`success` / `partial` si des assets ont échoué / `failed` si exception globale), `lastRunAt/lastRunStatus/consecutiveFailures`, désactivation après 5 échecs planifiés, notification d'échec au propriétaire (topic in-app existant `user:exception` via `ExceptionNotifier` / `UserNotifyableException`, voir `src/Notification/ExceptionNotifier.php`).
  - Le run est flushé régulièrement (progression visible dans l'UI).
- `src/Consumer/Handler/Automation/RunAutomation.php` + `RunAutomationHandler.php` : message `#[MessengerMessage('task')]` `{runId}`.
- `src/Automation/Schedule/AutomationSchedule.php` : `#[AsSchedule('automation')]`, `RecurringMessage::every('1 minute', new DispatchDueAutomations())`, `->lock($lockFactory->createLock('automation-schedule'))`, `->stateful($cache)` (cache.app).
- `src/Automation/Schedule/DispatchDueAutomations.php` + `DispatchDueAutomationsHandler.php` → `AutomationScheduler::dispatchDue(\DateTimeImmutable $now)` : pour chaque automation due, calcule le prochain `nextRunAt` (`CronExpression` dans le fuseau `timezone`), flush, puis `AutomationRunner::trigger(..., schedule)`.
- `src/Automation/Schedule/CronHelper.php` : validation (expression valide, intervalle ≥ 5 min sur les 3 prochaines occurrences), `getNextRunAt()`. `nextRunAt` recalculé à chaque écriture (`schedule`/`timezone`/`enabled`).
- `src/Command/RunDueAutomationsCommand.php` : `app:automation:run-due` (`#[AsCommand]`).
- Actions V1 de ce plan (`src/Automation/Action/`) :
  - `MoveToCollectionAction` (`move_to_collection`, config `{collectionId}`) : mêmes contrôles que `MoveAssetProcessor` (ASSET_CREATE sur la cible, EDIT + même workspace par asset ; sinon asset `skipped` avec raison), puis dispatch `AssetMove`. Idempotent (asset déjà dans la collection = no-op).
  - `AddToCollectionAction` (`add_to_collection`, `{collectionId}`) : réutiliser le service/la logique d'`AddAssetsToCollectionProcessor` (l'extraire dans un service si elle est dans le processor).
  - `AttributeAction` (`attribute`, `{definitionId, action: set|add|delete|replace, value?, locale?, regex?, flags?, replaceWith?}`) : construit un `AssetAttributeBatchUpdateInput` et appelle `BatchAttributeManager::validate()` puis `handleBatch(..., dispatchUpdateEvent: true)`, regroupé par workspace (la définition doit appartenir au workspace de l'asset ; sinon `skipped`).
  - `TagsAction` (`tags`, `{add: tagId[], remove: tagId[]}`) : via `BatchAttributeManager` avec `AttributeValidator::TAGS`.
- API :
  - `src/Api/Model/Input/Automation/SavedSearchAutomationInput.php`, `ResetCursorInput.php`, `TriggerAutomationInput.php` (`dryRun`).
  - `src/Api/Mapper/Input/SavedSearchAutomationInputMapper.php` (valide chaque action via le registre, chiffre les secrets, recalcule `nextRunAt`, `createdBy`).
  - `src/Api/Model/Output/Automation/*Output.php` + `src/Api/Mapper/Output/*OutputMapper.php` (`#[AsTaggedItem(index: XOutput::class)]`), actions masquées par `ActionConfigSecretsHandler`.
  - Opérations : `GetCollection` (QueryParameter `savedSearch` requis), `Get`, `Post`, `Patch`, `Delete` ; `Post /saved-search-automations/{id}/run` (processor `TriggerAutomationProcessor`, `throwOnNotFound: true`, renvoie l'`AutomationRun`) ; `Post /saved-search-automations/{id}/reset-cursor` (processor ; `mode=empty` = suppression SQL synchrone ; `mode=current` = message `ResetAutomationCursor` sur `task`, dont le handler itère les résultats sous l'identité du propriétaire et insère les vus sans exécuter d'action). Pas d'endpoint listant les types d'actions (connus du client).
  - Rappel CLAUDE.md : mode MainController, endpoints custom = provider/processor, jamais `controller:` ; contrôle de sécurité de l'entité via `extraProperties` `InputMapperProcessor::ENTITY_SECURITY(_POST_VALIDATION)` (cf. mémoire : `security:` sur une écriture voit le DTO).
- `src/Security/Voter/SavedSearchAutomationVoter.php` : `READ|EDIT|DELETE|RUN` accordés au propriétaire de la saved search parente (et aux admins, `AdminVoter` le fait déjà). `CREATE` : propriétaire de la saved search ciblée. `AutomationRunVoter` (ou voter de l'automation parente) pour les runs.
- `src/Api/Model/Output/SavedSearchOutput.php` + `SavedSearchOutputMapper.php` : capacité `capabilities.manageAutomations` (= `isGranted(SavedSearchAutomationVoter::CREATE, savedSearch)`).
- Listener Doctrine (`#[AsEntityListener]` ou `#[AsDoctrineListener]`) sur `SavedSearch` : changement d'`ownerId` → `enabled = false` sur ses automations.
- `config/packages/messenger.yaml` : rien pour `scheduler_automation` (transport généré par le composant) ; vérifier que `test://` n'est pas requis pour lui en test.
- `migrations/Version2026XXXXXXXXXX.php` : **SQL pur** (règle CLAUDE.md), généré par `doctrine:migrations:diff` puis relu, `down()` complet.
- `src/Controller/Admin/` : CRUD EasyAdmin lecture seule `SavedSearchAutomationCrudController` + `AutomationRunCrudController` (lien depuis le menu admin à côté de Saved searches).
- Infra : `databox/api/Dockerfile:92` et `docker-compose.yml:786` → `WORKER_PRIORITIES="p1 p2 task scheduler_automation"`. Docs `doc/tech/01_setup.md` (section cron) : mentionner que les automations ne passent pas par le cron hôte.
- `databox/api/bin/cron-script-hourly.sh` : **ne pas** ajouter la commande (le scheduler s'en charge).

Client (`databox/client/src/`) :
- `types/api.ts` : `SavedSearchAutomation`, `AutomationAction`, `AutomationRun`, `AutomationScope`, `AutomationRunStatus` ; `SavedSearch.capabilities.manageAutomations`.
- `lib/api/automations.ts` (nouveau) : `getSavedSearchAutomations(savedSearchId)`, `getSavedSearchAutomation`, `postSavedSearchAutomation`, `patchSavedSearchAutomation`, `deleteSavedSearchAutomation`, `triggerSavedSearchAutomation(id, {dryRun})`, `resetAutomationCursor(id, mode)`, `getAutomationRuns(automationId, {next})`.
- `features/saved-searches/automations/` (nouveau dossier) :
  - `AutomationsTab.tsx` : remplace le placeholder de `SavedSearchManageRoute.tsx` ; onglet visible seulement si `capabilities.manageAutomations`. Liste (nom, planification lisible, scope, switch enabled, dernier run + statut, menu : Exécuter, Dry run, Modifier, Historique, Réinitialiser le curseur, Supprimer avec `ConfirmDialog`).
  - `AutomationForm.tsx` : nom, déclencheur (manuel seul / planifié : presets horaire, quotidien à HH:MM, hebdomadaire jour+heure, expression cron personnalisée + fuseau, prochaine exécution affichée), scope (nouveaux / tous ; à la création en scope nouveaux : case « Ignorer les assets existants » → `reset-cursor mode=current`), liste ordonnée d'actions (ajout, suppression, monter/descendre).
  - `actions/registry.tsx` : `Record<ActionType, {label, icon, Form: FC<{value, onChange, errors}>, summary(config): string}>` — **un fichier par action** (`actions/MoveToCollectionForm.tsx`, `AddToCollectionForm.tsx`, `AttributeActionForm.tsx`, `TagsActionForm.tsx`). Les plans 004/005/006 y ajouteront chacun une entrée.
  - `AutomationRunsPanel.tsx` : historique paginé (statut, déclencheur, dates, compteurs, résultats par action dépliables, erreurs) ; rafraîchissement (polling React Query toutes les 5 s tant qu'un run est `pending|running`).
  - Réutiliser les sélecteurs existants : sélection de collection comme dans `features/assets/actions/CopyMoveDialog.tsx` ; sélecteur de définition d'attribut / valeur et de tags comme dans l'édition d'attributs batch (chercher les composants existants avant d'en créer).
- `i18n/locales/en.json` et `fr.json` : **ajouter les clés à la main** (le scanner `pnpm translate` plante, cf. mémoire), y compris la suppression de `saved_search.automations_soon`.

Tests (`databox/api/tests/`) :
- `unit/Automation/CronHelperTest.php` : validation, intervalle minimal, prochain run avec fuseau.
- `functional/Api/UserSpace/SavedSearchAutomationApiTest.php` : CRUD ; refus pour un éditeur ACL non propriétaire ; secrets masqués ; `nextRunAt` calculé ; `run` crée un run `pending` et dispatch `RunAutomation` (transport `test://`).
- `functional/Automation/AutomationRunnerTest.php` (étend `AbstractSearchTestCase`, ES peuplé) : run en scope `all` puis `new` (second run = 0 nouveau, ajout d'un asset → 1 nouveau) ; dry run ne touche pas au curseur ; asset en échec non marqué vu ; owner désactivé → `failed` + automation désactivée ; lock concurrent → `skipped`. Appeler `AutomationRunner::run()` directement (mémoire : messenger `test://` en test, appeler les services).
- `functional/Automation/DispatchDueAutomationsTest.php` : seules les automations dues et activées partent, `nextRunAt` avancé.
- Tests des 4 actions (move, add, attribute, tags), y compris le cas « pas le droit » → `skipped`.
- Mock Keycloak : `Alchemy\AuthBundle\Tests\Client\KeycloakClientTestMock` (services_test.yaml de l'auth-bundle) — y déclarer/étendre ce qu'il faut pour `getUser`, `getUserEffectiveRealmRoles`, `getUserGroups`.
- Client : tests Vitest du registre d'actions/presets cron (`features/saved-searches/automations/*.test.ts`), si une fonction pure de conversion preset ↔ cron existe.

### Étapes

1. `composer require symfony/scheduler dragonmantank/cron-expression` dans `databox-api-php` (mémoire : rediriger git@github.com en https si un repo VCS ssh bloque ; `reference.php` peut être réécrit → le restaurer si modifié sans raison).
2. Enums, entités, repositories, migration SQL pure.
3. `UserContextSwitcher`, `AssetSearch::iterateIds()`, `AutomationAssetIterator`.
4. Contrat d'action, registre, gestion des secrets, 4 actions V1.
5. Runner, message/handler, scheduler, commande, voter, listener de changement de propriétaire.
6. Ressources API (DTOs, mappers, processors), capacité `manageAutomations`.
7. EasyAdmin lecture seule ; `WORKER_PRIORITIES` ; doc.
8. Client : types, API, onglet, formulaire, registre d'actions, historique, traductions.
9. Tests, `composer cs`, `composer phpstan`, lint/format client.

### Vérification

Sans service partagé :
```bash
dc run --rm databox-api-php su app -c "composer test:quick"
dc run --rm dev pnpm --filter databox-client lint
dc run --rm dev pnpm --filter databox-client test
```
Avec la stack (db, redis, ES — environnement partagé, à faire après intégration si un autre agent l'utilise ; mémoire : `flock` pour agents parallèles) :
```bash
dc run --rm databox-api-php su app -c "composer phpunit:compact" 2>&1 | tail -60
dc run --rm databox-api-php su app -c "composer phpstan"
dc run --rm databox-api-php su app -c "bin/console doctrine:migrations:migrate -n && bin/console doctrine:schema:validate"
```
Manuel (après intégration, stack dev) : créer une automation planifiée `*/5 * * * *` sur une saved search ; `bin/console app:automation:run-due` ; vérifier le run dans l'onglet Automations ; second run → 0 nouveau ; upload d'un asset correspondant → 1 nouveau déplacé. Vérifier le scheduler réel : `dc run --rm databox-worker su app -c "bin/console messenger:consume scheduler_automation -vv --time-limit=130"`. Le worker doit être reconstruit (`WORKER_PRIORITIES`).

### Hors périmètre (003)

Actions des plans 004/005/006 ; preview des résultats sans run ; rejeu ciblé des assets en échec ;
« un asset sorti puis revenu redevient nouveau » ; correction des bugs relevés en passant dans
`AssetSearch::buildQuery()` (pas de contrôle READ sur la saved search passée en `savedSearch`, `sortBy`
stocké ignoré, `array_merge` qui écrase la requête) — à traiter à part.



---

## Interactions avec la file

- Plans en attente sans lien fonctionnel : `001-fixtures-databox-3-workspaces`, `001-readme-open-source`, `002-dupliquer-definitions-integrations`, `002-matomo-databox-client`. Recouvrements :
  - `databox/client/src/i18n/locales/{en,fr}.json` : touchés par les deux plans 002 et par 003-006 → conflits d'ajout de clés triviaux.
  - `002-matomo-databox-client` instrumente de nombreux composants client (dont `features/saved-searches/SaveSearchDialog.tsx`, `features/search/*`) ; s'il est intégré avant, ajouter le tracking des automations (création, run manuel) selon ses conventions (`databox/client/src/lib/analytics/events.ts`) ; sinon rien.
  - `002-dupliquer-definitions-integrations` touche `src/Integration/*` et `src/lib/api/misc.ts` ; la série automations ne modifie pas ces fichiers (nouveau `lib/api/automations.ts`).
- 004, 005 et 006 dépendent de 003 et se recouvrent entre eux uniquement sur
  `databox/client/src/features/saved-searches/automations/actions/registry.tsx` (une entrée chacun) et les fichiers i18n : conflits triviaux à résoudre à l'intégration.
  005 touche seul au `Dockerfile` (ext-ftp) ; 003 et 005 ajoutent des dépendances composer → `composer.lock` à régénérer depuis la base intégrée de 003 en cas de conflit.
