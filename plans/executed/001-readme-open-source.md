---
id: 001-readme-open-source
title: README open source et fichiers communauté GitHub
status: executed
finished: 2026-10-19 14:48
started: 2026-10-19 11:24
run_base: bd9d98b44e664cf3b0e6b4dea62d54e6d4d3131d
created: 2026-10-19 10:44
branch: next
depends_on: []
---

# README open source + fichiers communauté GitHub

## Contexte et objectif

Le `README.md` racine de `alchemy-fr/phrasea` est générique (liste de features marketing, aucun
quickstart concret, pas d'architecture, section « Contributing » en trois puces) et contient des
liens cassés :

- badges Issues / PRs / Last Commit pointent vers `github.com/alchemy/phrasea` (org inexistante,
  la bonne est `alchemy-fr`) ;
- « Last Commit » vise la branche `main`, qui n'existe pas (branches : `next` = branche par défaut
  GitHub / développement 5.x, `master` = ligne stable 4.x) ;
- la doc publiée (`https://doc.phrasea.com`, Docusaurus construit par
  `alchemy-fr/phrasea-documentation-builder` à partir de `doc/`) n'est pas mentionnée.

Le « community profile » GitHub du dépôt est à 37 % : ni CONTRIBUTING, ni CODE_OF_CONDUCT, ni
SECURITY, ni modèles d'issue/PR (et pas de dépôt `alchemy-fr/.github` d'organisation).

Objectif : un README au niveau des projets open source à succès (structure type Supabase / Immich /
Plausible / Directus) et les fichiers communauté standards, pour viser 100 % au community profile.

## Décisions prises avec l'utilisateur

1. **Périmètre** : README + fichiers communauté (CONTRIBUTING, CODE_OF_CONDUCT, SECURITY, modèles
   d'issue et de PR). Tout en **anglais**.
2. **Visuels** : prévoir l'emplacement d'une capture produit **sans image** — un commentaire HTML
   `<!-- TODO: product screenshot (databox client) -->` à l'endroit prévu, l'utilisateur fournira
   l'image plus tard (le bucket `phrasea-alchemy-statics.s3.eu-west-3.amazonaws.com` héberge déjà
   le logo). Ne pas lancer la stack pour capturer. Un diagramme d'architecture **Mermaid** est en
   revanche attendu (rendu natif GitHub, pas d'image à héberger).
3. **Sécurité / support** : SECURITY.md renvoie vers le **GitHub Private Vulnerability Reporting**
   (`https://github.com/alchemy-fr/phrasea/security/advisories/new`) ; support via GitHub Issues.
   Aucune adresse email inventée. Activer la fonctionnalité dans les réglages du dépôt est une
   action manuelle hors périmètre (à signaler dans le rapport final).

## Faits vérifiés à réutiliser (ne pas inventer au-delà)

- Repo : `https://github.com/alchemy-fr/phrasea`, description GitHub « The open-source DAM for
  modern organizations », homepage `https://www.phrasea.com`, docs `https://doc.phrasea.com`.
- Licence MIT (`LICENSE`, © 2019 Alchemy). Releases GitHub taguées sans `v` (dernière : `4.5.1`).
- Images Docker Hub : `alchemyfr/ps-<service>` (ex. `alchemyfr/ps-databox-api-php`,
  `ps-databox-client`, `ps-expose-*`, `ps-uploader-*`, `ps-dashboard`, `ps-keycloak`,
  `ps-configurator`, `ps-report-api`) — voir `doc/tech/Deployment/01_prerequisite.md`.
- Helm : `https://github.com/alchemy-fr/phrasea-helm-charts`.
- Applications (voir `CLAUDE.md` § « What this is ») : databox (cœur DAM), expose (publication),
  uploader (ingestion), dashboard (shell), configurator, report, databox/indexer (Node).
- Intégrations databox réelles (`databox/api/src/Integration/`) : AWS Rekognition / Transcribe /
  Translate, Happyscribe, RemoveBg, Moderation, Blurhash, Phraseanet, ToastUi, webhooks,
  watermark, file analyzers, renditions. Ne citer que celles-ci.
- Stack : Symfony 8.1 + API Platform 5 (PHP 8.5), Doctrine ORM 3 / PostgreSQL, Elasticsearch,
  RabbitMQ (Messenger), Redis, MinIO/S3, Keycloak (auth), Traefik ; clients React (Next.js App
  Router pour databox, Vite pour les autres) ; pnpm + Turbo ; tout en Docker Compose.
- Installation : `doc/tech/01_setup.md` (Compose, `.env.local`, fonction `dc`, `bin/setup.sh`,
  `dc up -d`) ; dev : `doc/tech/02_dev.md` (mkcert, `bin/dev/make-cert.sh`,
  `sudo bin/dev/append-etc-hosts.sh`, `dc build dev`, …). Domaine par défaut `phrasea.local`, URLs
  `https://dashboard.phrasea.local`, `https://databox.phrasea.local`, etc. Admin par défaut
  `phrasea-admin` / mot de passe `DEFAULT_ADMIN_PASSWORD` de `.env` (valeurs `__CHANGE_ME_…` à
  changer).
- Tests : 3 tiers `bin/test.sh quick|standard|release`, décrits dans `doc/tech/Development/ci.md`.
  Workflows : `.github/workflows/quick.yaml` (« Quick checks »), `ci.yaml` (« CI »),
  `release.yaml` (« Release »).
- Conventions de commit observées : Conventional Commits (`feat(scope): …`, `fix(ci): …`), préfixées
  d'une clé Jira `PS-1234` quand elle existe. PRs : `next` par défaut, `master` pour les correctifs
  de la ligne stable 4.x.
- Hook pre-commit Husky + lint-staged : **prettier reformate les `*.md`** au commit (via le
  conteneur `dev` si lint-staged n'est pas sur l'hôte). Prévoir que le formatage final soit celui
  de prettier.

## Fichiers concernés

| Fichier | Changement |
|---|---|
| `README.md` | Réécriture complète (voir structure ci-dessous) |
| `CONTRIBUTING.md` | Nouveau |
| `CODE_OF_CONDUCT.md` | Nouveau — Contributor Covenant 2.1 (voir étape 3 pour le canal de signalement) |
| `SECURITY.md` | Nouveau |
| `.github/ISSUE_TEMPLATE/bug_report.yml` | Nouveau (issue form) |
| `.github/ISSUE_TEMPLATE/feature_request.yml` | Nouveau (issue form) |
| `.github/ISSUE_TEMPLATE/config.yml` | Nouveau : `blank_issues_enabled: false`, liens vers la doc et le signalement de faille |
| `.github/PULL_REQUEST_TEMPLATE.md` | Nouveau |

Ne pas toucher à `doc/` (consommé par le builder Docusaurus), ni à `CLAUDE.md`, ni aux workflows.

## Étapes

### 1. README.md

Structure (dans cet ordre) :

1. **En-tête centré** (`<div align="center">`) : logo existant (URL S3 actuelle, `alt="Phrasea"`,
   largeur ~400), tagline « The open-source DAM for modern organizations » (= description GitHub),
   ligne de liens `Website · Documentation · Docker Hub · Helm charts · Report a bug`.
2. **Badges** (une ligne, shields.io, tous corrigés vers `alchemy-fr/phrasea`) : License MIT,
   dernière release (`img.shields.io/github/v/release/alchemy-fr/phrasea`), statut workflow
   « Quick checks » (`…/actions/workflows/quick.yaml/badge.svg?branch=next`), statut « CI »
   (`ci.yaml`, branch `next`), last commit sur `next`
   (`img.shields.io/github/last-commit/alchemy-fr/phrasea/next`), PRs welcome. Liens cibles :
   `https://github.com/alchemy-fr/phrasea/...`.
3. **Emplacement capture** : `<!-- TODO: product screenshot (databox client), e.g. doc/assets/screenshot.png -->`
   sous les badges, rien d'autre.
4. **About / Why Phrasea** : 1 paragraphe (reprendre l'actuel : permissions fines, intégrations IA,
   SaaS / on-premise / hybride), sans superlatifs creux.
5. **Features** : liste groupée par application (Databox, Expose, Uploader, Dashboard) avec 2–4
   puces factuelles chacune, plus « Integrations » citant uniquement les intégrations réelles
   listées ci-dessus, et « Search » (Elasticsearch + AQL, cf. `doc/tech/Databox/aql.md`).
   Vérifier chaque affirmation dans le code/la doc (`doc/tech/*/01_intro.md`,
   `doc/user/*`). Supprimer les features non vérifiables (ex. « versioning » si non trouvé).
6. **Architecture** : diagramme Mermaid `flowchart LR` : navigateur → Traefik → clients
   (dashboard, databox, expose, uploader) et APIs Symfony ; APIs → PostgreSQL, Elasticsearch,
   RabbitMQ (workers), Redis, MinIO/S3 ; Keycloak pour l'auth ; indexer Node ↔ stockage/databox.
   Vérifier les liens réels dans `docker-compose.yml` avant de dessiner. Lien vers
   `doc/tech/Deployment/technical-architecture.svg` pour le détail.
7. **Quick start** (Docker Compose, local) : prérequis (Docker + Compose v2, ~RAM indicative
   seulement si trouvée dans `doc/tech/Deployment/01_prerequisite.md`, mkcert pour HTTPS local),
   puis blocs `bash` un par commande : `git clone`, `cd phrasea`, changer les secrets
   `__CHANGE_ME_` (renvoi `.env.local`), `bin/dev/make-cert.sh`, `sudo bin/dev/append-etc-hosts.sh`,
   `bin/setup.sh`, `docker compose up -d` ; puis tableau des URLs (`https://dashboard.phrasea.local`,
   databox, expose, uploader) et identifiants (`phrasea-admin` / `DEFAULT_ADMIN_PASSWORD`).
   Recouper chaque commande avec `doc/tech/01_setup.md` et `02_dev.md` ; si l'ordre réel diffère,
   suivre la doc. Renvoyer vers ces deux guides pour le reste.
8. **Deployment** : 3 lignes — Docker Compose (setup guide), Kubernetes (Helm charts repo),
   images Docker Hub `alchemyfr/ps-*` ; lien `doc/tech/Deployment/`.
9. **Documentation** : lien `https://doc.phrasea.com` + tableau des entrées de `doc/` (user guide,
   tech setup, dev, configuration, authentication, CI).
10. **Repository layout** : arbre court (apps, `lib/php`, `lib/js`, `bin`, `infra`, `doc`),
    repris de `CLAUDE.md` § « Repository layout », 1 ligne par entrée.
11. **Tech stack** : liste courte (faits vérifiés ci-dessus).
12. **Contributing** : 2 lignes + lien `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`.
13. **Security** : 1 ligne + lien `SECURITY.md`.
14. **Upgrading** : liens `UPGRADE-3.0.md`, `UPGRADE-5.md`, page Releases.
15. **License** : « MIT © Alchemy » + lien `LICENSE`.

Contraintes : pas d'emoji dans les titres, pas de promesse non vérifiable, liens relatifs pour les
fichiers du dépôt, tous les blocs shell tagués `bash`.

### 2. CONTRIBUTING.md

Sections : Ways to contribute (bugs, features, docs, code) ; Before you start (ouvrir une issue
pour un changement non trivial) ; Development setup (renvoi `doc/tech/01_setup.md` +
`02_dev.md`, rappel « everything runs in Docker », fonction `dc`) ; Branches (`next` par défaut,
`master` = correctifs 4.x) ; Coding standards (php-cs-fixer `bin/php-cs.sh`, `pnpm lint` /
`pnpm format` dans le conteneur `dev`, pre-commit Husky) ; Tests (tiers `bin/test.sh quick` /
`standard`, commandes `composer phpunit:compact`, `pnpm --filter … test`, renvoi
`doc/tech/Development/ci.md`) ; Commit messages (Conventional Commits, exemples réels du log) ;
Pull requests (checklist courte) ; Doctrine migrations en SQL pur (règle de `CLAUDE.md`) ;
Licence des contributions (MIT). Reprendre les commandes **telles quelles** depuis `CLAUDE.md` et
`doc/` — n'en inventer aucune.

### 3. CODE_OF_CONDUCT.md

Contributor Covenant 2.1 (texte officiel intégral, https://www.contributor-covenant.org/version/2/1/code_of_conduct/).
Section « Enforcement » : pas d'email inventé ; remplacer `[INSERT CONTACT METHOD]` par « the
project maintainers, privately through GitHub (Security tab → Report a vulnerability) ». Aucun
placeholder ne doit rester.

### 4. SECURITY.md

Supported versions (tableau : `5.x (next)` — prochaine majeure, `4.5.x` — oui, `< 4.5` — non ;
vérifier les tags `git tag --sort=-creatordate` avant d'écrire) ; Reporting a vulnerability : ne pas
ouvrir d'issue publique, utiliser Private Vulnerability Reporting (lien ci-dessus), infos à fournir,
délais d'accusé de réception formulés sans engagement chiffré (« as soon as possible »).

### 5. Modèles GitHub

- `bug_report.yml` : champs description, steps to reproduce, expected/actual, version Phrasea
  (input), composant (dropdown : Databox, Expose, Uploader, Dashboard, Configurator, Report,
  Indexer, Other), déploiement (dropdown : Docker Compose, Kubernetes/Helm, Other), logs (textarea
  `render: shell`). Labels `bug`.
- `feature_request.yml` : problème, solution proposée, alternatives, composant. Labels
  `enhancement`.
- `config.yml` : `blank_issues_enabled: false` ; `contact_links` → Documentation
  (doc.phrasea.com), Security vulnerability (security/advisories/new).
- `PULL_REQUEST_TEMPLATE.md` : Summary, Related issue / Jira, Type of change, Checklist (tests,
  `bin/test.sh quick`, docs/UPGRADE mis à jour si BC break, migrations SQL pur).

### 6. Formatage et commit

Faire passer prettier sur les `.md` (le hook le fera au commit). Un seul commit :
`docs: rewrite README and add community health files`. Pas de trailer Co-Authored-By.

## Vérification

Aucune dépendance à la stack (docker uniquement pour prettier/hook).

```bash
grep -rn "github.com/alchemy/" README.md CONTRIBUTING.md SECURITY.md .github/ ; echo "doit être vide"
```

```bash
grep -rn "INSERT\|TODO" CODE_OF_CONDUCT.md SECURITY.md CONTRIBUTING.md .github/ ; echo "doit être vide (seul README garde le TODO capture)"
```

```bash
for f in README.md CONTRIBUTING.md SECURITY.md CODE_OF_CONDUCT.md; do grep -oE '\]\(\./?[^)#]+' "$f" | sed -E 's/^\]\(\.?\/?//' | while read p; do [ -e "$p" ] || echo "$f: lien cassé $p"; done; done
```

```bash
python3 -c "import yaml,glob; [yaml.safe_load(open(f)) for f in glob.glob('.github/ISSUE_TEMPLATE/*.yml')]; print('yaml ok')"
```

(`python3` est sur l'hôte ; sinon via `docker run --rm -v $PWD:/w -w /w python:3-alpine sh -c "pip install -q pyyaml && python …"`.)

- Vérifier les URLs externes (badges, doc.phrasea.com, Docker Hub, Helm) par `curl -sI` (code
  200/30x).
- Rendu Mermaid et issue forms : vérifiables seulement **après push** sur GitHub (aperçu du README
  sur la branche, onglet « New issue », page Insights → Community standards).

## Hors périmètre

- Captures d'écran réelles (emplacement TODO uniquement).
- Activer Private Vulnerability Reporting / Discussions, changer topics ou description du dépôt
  (réglages GitHub manuels, à signaler à l'utilisateur).
- Modifier `doc/`, les workflows CI, `CLAUDE.md`, créer un dépôt `alchemy-fr/.github`.
- README par sous-projet (`databox/client/README.md`, etc.), CHANGELOG, traduction française.

## Interactions avec la file

File vide au moment de la planification : aucun recouvrement, `depends_on: []`. Fichiers touchés
uniquement à la racine et dans `.github/` hors `workflows/` : faible risque de conflit avec
d'autres plans.
