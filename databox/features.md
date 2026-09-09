# Fonctionnalités du client Databox

Inventaire fonctionnel du client React de Databox (`databox/client`), établi par analyse du code source.
Sauf mention contraire, les chemins de fichiers sont relatifs à `databox/client/src`.

Les fonctionnalités sont regroupées par domaine. Pour chacune : nom, description, fichier(s) principaux.

## Sommaire

1. [Structure globale, navigation, authentification](#1-structure-globale-navigation-authentification)
2. [Recherche](#2-recherche)
3. [Liste de résultats](#3-liste-de-résultats)
4. [Visionneuse d'asset](#4-visionneuse-dasset)
5. [Attributs](#5-attributs)
6. [Gestion et actions sur les assets](#6-gestion-et-actions-sur-les-assets)
7. [Fichiers, analyse et quarantaine](#7-fichiers-analyse-et-quarantaine)
8. [Upload](#8-upload)
9. [Workspaces (administration)](#9-workspaces-administration)
10. [Collections et arborescence](#10-collections-et-arborescence)
11. [Paniers](#11-paniers)
12. [Partage](#12-partage)
13. [Permissions et confidentialité](#13-permissions-et-confidentialité)
14. [Profils d'affichage](#14-profils-daffichage)
15. [Règles de filtrage d'attributs](#15-règles-de-filtrage-dattributs)
16. [Pages CMS / Landing](#16-pages-cms--landing)
17. [Intégrations](#17-intégrations)
18. [Workflows](#18-workflows)
19. [Tâches d'opération](#19-tâches-dopération)
20. [Discussion et pièces jointes](#20-discussion-et-pièces-jointes)
21. [Carte et géolocalisation](#21-carte-et-géolocalisation)
22. [Utilisateur : préférences, thèmes, langues](#22-utilisateur--préférences-thèmes-langues)
23. [Récapitulatif des raccourcis clavier](#23-récapitulatif-des-raccourcis-clavier)
24. [Récapitulatif des préférences persistées](#24-récapitulatif-des-préférences-persistées)
25. [Configuration runtime](#25-configuration-runtime)

---

## 1. Structure globale, navigation, authentification

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Layout vertical principal | Menu vertical avec logo (clic = réinitialisation de la recherche), navigation, panneau gauche (facettes, arbre de collections, paniers, recherches sauvegardées) et menu paramètres (profil d'affichage, thème, langue, notifications, tâches d'opération pour les admins). | `components/Layout/AppLayout.tsx`, `components/Media/LeftPanel.tsx` |
| Barre horizontale alternative | Variante avec menu horizontal utilisée hors de l'écran principal (pages CMS), logo cliquable vers les assets. | `components/Layout/AppBar.tsx` |
| Panneau gauche repliable | Ouvert par défaut, refermé automatiquement sous le breakpoint `md`. Trois onglets : **Facettes**, **Navigation** (arbre), **Paniers** (masqué si non authentifié). | `components/App.tsx`, `components/Media/LeftPanel.tsx` |
| Modales routées | Toutes les fenêtres de gestion sont des routes portées par le paramètre d'URL `_m` : workspaces, collections, paniers, recherches sauvegardées, profils, assets, fichiers, workflow, éditeur d'attributs en masse, tâches d'opération. URL partageables, historique navigateur, pile de modales. | `routes.ts`, `components/Root.tsx`, `components/Routing/ModalLink.tsx`, `components/Dialog/RouteDialog.tsx` |
| Modales à onglets | Dialogues à onglets scrollables dont l'onglet actif est reflété dans l'URL (`manage/:tab`). Onglets filtrés selon les capabilities de l'entité. | `components/Ui/Tabs.tsx`, `components/Dialog/Tabbed/TabbedDialog.tsx`, `ContentTab.tsx`, `FormTab.tsx` |
| Authentification Keycloak / OAuth | Routes publiques vs privées, redirection automatique vers Keycloak (option `autoConnectIdP` pour sauter le sélecteur d'IdP), loader pendant la reprise de session, page de callback du code d'autorisation, gestion de l'expiration de session. | `components/Routing/RouteProxy.tsx`, `components/AppAuthorizationCodePage.tsx`, `components/Root.tsx`, `init.ts` |
| Rôles applicatifs | `databox-admin` débloque le menu de navigation (Assets, Pages beta) et l'entrée « Operation Tasks ». Le rôle `tech` donne accès aux onglets « ES Document ». | `constants.ts`, `components/Layout/AppNav.tsx`, `components/Dialog/Asset/ESDocument.tsx` |
| Page publique de partage | Route publique `s/:id/:token` affichant un asset partagé. | `pages/SharePage.tsx` |
| Pages CMS | Routes `p/:slug` (page publique), `/` (accueil éditable), `pages` et `pages/:id/edit` (back-office, marqué Beta). | `pages/PagePage.tsx`, `pages/HomePage.tsx`, `pages/PageIndexPage.tsx`, `pages/PageEditPage.tsx` |
| Notifications temps réel | Pusher/Soketi avec authentification des canaux privés via `POST /pusher/auth` (jeton Keycloak). Le centre de notifications du framework s'y branche. | `lib/pusher.ts`, `components/Layout/AppLayout.tsx` |
| Deep links de notification | Route `notification-uri?uri=…` résolvant une URI applicative vers l'écran cible : asset (avec ancre sur un message de discussion), collection (recherche filtrée), onglet d'administration de workspace. Repli sur l'accueil. | `pages/NotificationUriPage.tsx`, `hooks/useNotificationUriHandler.ts` |
| Toasts d'état persistants | Toasts de progression globaux pour les **uploads en cours** (« X / Y uploaded », clic = dialogue des uploads en attente) et les **exports** (« Preparing Export… n% » puis « Export Ready! » avec bouton Download). | `components/StateFullToasts.tsx`, `hooks/usePendingUploads.tsx`, `hooks/usePendingExports.tsx`, `lib/toast.ts` |
| Glisser-déposer global | Toute la page de résultats est une zone de dépôt de fichiers pour l'import d'assets (refus si non authentifié). | `components/Media/Asset/AssetDropzone.tsx` |
| Analytics et supervision | Matomo (suivi des routes, impressions de contenus, plugin média optionnel) et Sentry (DSN, environnement, release, utilisateur associé). | `components/Root.tsx`, `components/App.tsx`, `config-compiler.js` |
| Cache de requêtes | React Query avec `staleTime` global de 5 minutes. | `lib/query.ts` |
| Page 404 | Page « non trouvé ». | `pages/NotFound.tsx` |

---

## 2. Recherche

### 2.1 Barre de recherche et requête plein texte

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Champ de recherche plein texte | Champ `type=search` autofocus, bouton « Search » ; la requête n'est appliquée qu'à la soumission du formulaire. | `components/Media/Search/SearchBar.tsx` |
| Effacement rapide | La croix native du champ relance une recherche vide. | `components/Media/Search/SearchBar.tsx` |
| Bouton Search intelligent | Désactivé si la requête tapée est identique à la courante et qu'un chargement est en cours. | `components/Media/Search/SearchBar.tsx` |
| Isolation clavier | `Ctrl+A` dans le champ sélectionne le texte sans déclencher « tout sélectionner » sur la liste. | `components/Media/Search/SearchBar.tsx` |
| État de recherche dans l'URL | Requête, conditions, tri, géolocalisation et id de recherche sauvegardée sont sérialisés dans le hash (`id=`, `q=`, `f=`, `s=`, `l=`) : URL partageable, navigation arrière/avant. | `components/Media/Search/search.ts`, `SearchProvider.tsx`, `lib/useHash.ts` |
| Réinitialisation | Action « Clear search » vidant le hash. | `components/Media/Search/SearchMoreAction.tsx` |
| Rechargement forcé | Relancer la même requête force un nouvel appel API. | `components/Media/Search/SearchProvider.tsx` |
| Annulation des requêtes concurrentes | Chaque nouvelle recherche annule la précédente (`AbortController`). | `components/Media/Search/ResultProvider.tsx` |
| Détection « recherche active » | Pilote l'activation des actions Save / Clear (requête, conditions, tri non par défaut ou géoloc). | `components/Media/Search/SearchProvider.tsx` |

### 2.2 Suggestions et autocomplétion

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Suggestions de recherche | Popover d'autocomplétion (Algolia autocomplete-core) alimenté par l'API ; suggestions typées asset / collection / workspace, avec surlignage du terme. | `components/Media/Search/Suggest/useSuggest.tsx`, `Suggest/SuggestPopover.tsx`, `api/asset.ts` |
| Suggestion de collection | Sélectionner une collection vide la requête texte et applique directement le filtre de collection. | `components/Media/Search/Suggest/useSuggest.tsx` |
| Suggestion d'asset | Injecte le nom entre guillemets comme requête et lance la recherche. | `components/Media/Search/Suggest/useSuggest.tsx` |
| Navigation clavier | Navigation dans les suggestions via le clavier (autocomplete-core). | `components/Media/Search/Suggest/useSuggest.tsx` |

### 2.3 Langage de requête AQL

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Parseur AQL (grammaire nearley) | Expressions AND/OR/NOT, parenthèses, appels de fonctions (ex. `NOW()`), arithmétique (`+ - * /`), littéraux quotés avec échappement, booléens, `null`, champs et champs built-in (`@xxx`). Grammaire compilée via `pnpm compile-grammar`. | `components/Media/Search/AQL/grammar.ne`, `grammar.ts`, `AQL.ts` |
| Opérateurs | `=`, `!=`, `>`, `<`, `>=`, `<=`, `IN`, `NOT IN`, `IS MISSING`, `EXISTS`, `CONTAINS`, `DOES NOT CONTAIN`, `MATCHES`, `DOES NOT MATCH`, `STARTS WITH`, `DOES NOT START WITH`, `BETWEEN`, `NOT BETWEEN`, `WITHIN CIRCLE(lat, lon, rayon)`, `WITHIN RECTANGLE(4 coords)`. | `components/Media/Search/AQL/aqlTypes.ts`, `query.ts` |
| Champs built-in | `@checksum @collection @createdAt @docUniqueId @editedAt @extension @filename @hasSource @size @type @id @owner @privacy @rendition @score @tag @isStory @story @workspace @deleted @assetStatus`. | `components/Media/Search/search.ts` |
| Sérialisation AST → texte | Réécriture canonique d'un AST en chaîne AQL (bascule builder ↔ texte). | `components/Media/Search/AQL/query.ts` |
| Validation typée | Vérifie l'existence du champ, la compatibilité opérateur/type (ex. `MATCHES` réservé aux textes, `WITHIN CIRCLE` aux geo_point) et le type des valeurs, avec messages explicites. | `components/Media/Search/AQL/validation.ts` |
| Affichage humanisé | Les chips affichent les noms d'affichage des attributs, les valeurs formatées par type, `Yes/No/Null` pour les constantes et les entités résolues (id → libellé). | `components/Media/Search/AQL/useResolveASTs.ts`, `query.ts` |
| Entités inline | Syntaxe `@<id:label>` pour référencer une entité, rendue en pastille visuelle ; résolution asynchrone (`…` / `Not found` / `Not allowed`). | `components/Media/Search/AQL/entities.tsx`, `store/entitiesStore.ts` |
| Tests de la grammaire | Jeu de tests Vitest couvrant booléens, null, arithmétique, fonctions. | `components/Media/Search/AQL/AQL.test.ts` |

### 2.4 Conditions de recherche (chips)

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Liste de conditions | Chaque condition est un chip cliquable sous la barre de recherche. | `components/Media/Search/AQL/SearchConditions.tsx`, `SearchCondition.tsx` |
| Ajouter / éditer | Bouton « Add Condition » ; clic sur un chip pour rouvrir la modale pré-remplie. | `SearchConditions.tsx`, `SearchCondition.tsx` |
| Activer / désactiver | Une condition désactivée passe en couleur « warning », reste dans l'URL (flag `_`) mais est exclue de la requête. | `SearchCondition.tsx`, `ResultProvider.tsx` |
| Supprimer | Entrée « Remove » du menu du chip. | `SearchCondition.tsx`, `SearchProvider.tsx` |
| Menu contextuel | Clic droit ou icône « ⋮ » sur un chip. | `SearchCondition.tsx` |
| Conditions identifiées | Les filtres de facettes, collection, workspace et corbeille utilisent un id stable et se remplacent au lieu de s'empiler. | `SearchProvider.tsx`, `search.ts` |
| Flag « inversé » | Le format d'URL prévoit un flag `!` (inversé) par condition. | `components/Media/Search/search.ts` |

### 2.5 Builder visuel de conditions

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Modale de condition | Titre contextuel (Add / Edit), validation avant fermeture, affichage des erreurs. | `components/Media/Search/AQL/SearchConditionDialog.tsx` |
| Bascule Builder ↔ Texte | Conversion bidirectionnelle AST ↔ chaîne AQL. | `SearchConditionDialog.tsx` |
| Éditeur AQL brut | Champ monospace, spellcheck désactivé, bordure rouge si invalide. | `components/Media/Search/AQL/AQLField.tsx` |
| Ligne de condition | Champ + opérateur + valeur(s) + suppression. | `AQL/Builder/ConditionBuilder.tsx` |
| Sélecteur de champ | Définitions `enabled` + `searchable`, built-in en tête (gras), recherche intégrée. | `AQL/Builder/ConditionBuilder.tsx` |
| Opérateurs filtrés par type | Seuls les opérateurs compatibles avec le type du champ sont proposés. | `AQL/Builder/ConditionsBuilder.tsx` |
| Arité variable | `IN`/`NOT IN` : valeurs à volonté ; `BETWEEN` : 2 ; `WITHIN CIRCLE` : 3 champs nommés ; `WITHIN RECTANGLE` : 4 ; `EXISTS`/`MISSING` : aucune. | `AQL/Builder/ValueBuilder.tsx` |
| Widgets typés | Le widget de saisie est celui du type d'attribut (date, datetime-local, etc.), normalisation des dates. | `AQL/Builder/FieldBuilder.tsx`, `ValueBuilder.tsx` |
| Casting explicite | Préfixe `=` pour une expression AQL brute ; guillemets pour forcer une chaîne. | `AQL/Builder/ValueBuilder.tsx` |
| Groupes imbriqués | « Add Condition Group » avec sélecteur AND/OR par groupe, encadrement visuel, suppression. | `AQL/Builder/AddExpressionRow.tsx`, `AndOrOrExpressionBuilder.tsx`, `ExpressionBuilder.tsx`, `builder.ts` |
| Chargement des définitions | Définitions globales + workspace chargées avant affichage. | `SearchConditionDialog.tsx`, `store/attributeDefinitionStore.ts` |

### 2.6 Facettes

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Panneau de facettes | Facettes renvoyées par la recherche, repliables individuellement. | `components/Media/Asset/Facets/Facets.tsx`, `FacetGroup.tsx` |
| Filtre par nom | Champ « Filter… » sur les noms de facettes ; en mode filtre, les facettes masquées réapparaissent. | `Facets.tsx` |
| Tout déplier / replier | Menu « ⋮ » : Expand All / Collapse All. | `Facets.tsx` |
| Épingler une facette | Fixe la facette en tête (préférence persistée). | `Facets.tsx`, `facetFunc.ts` |
| Masquer une facette + Undo | Icône œil barré ; toast « Undo » pour restaurer. | `Facets.tsx`, `facetFunc.ts` |
| Réglages des facettes | Modale de réordonnancement par drag & drop, listes visibles / masquées, « Reset to default » avec confirmation. | `Facets/FacetSettingsDialog.tsx` |
| Facette texte | Valeurs + compteur, cases à cocher, multi-sélection → `champ IN (...)`. | `Facets/types/TextFacet.tsx`, `TextFacetItem.tsx`, `ListFacet.tsx` |
| Facette booléenne | `Yes (n)` / `No (n)`. | `Facets/types/BooleanFacet.tsx` |
| Facette entités | Rendu enrichi (couleur, emoji) + compteur. | `Facets/types/EntitiesFacet.tsx` |
| Facette tags | Pastille de couleur + nom + compteur. | `Facets/types/TagsFacet.tsx`, `TagColor.tsx` |
| Facette date | Histogramme superposé à un slider double borne, libellés adaptés à la granularité → `champ BETWEEN "a" AND "b"`, bouton « Clear filter ». | `Facets/types/DateHistogramFacet.tsx` |
| Facette geo-distance | Carte Leaflet avec marqueur et cercles de distance colorés, popup du nombre de documents. | `Facets/types/GeoDistanceFacet.tsx`, `components/Map/OpenStreetMap.tsx` |
| Valeur manquante | Entrée « Missing (n) » → `champ IS MISSING`. | `Facets/types/ListFacet.tsx`, `AQL/AQLConditionBuilder.ts` |
| Relecture d'une condition | Le builder de condition de facette relit une condition existante pour cocher les bonnes valeurs. | `components/Media/Search/AQL/AQLConditionBuilder.ts` |
| Locale de facette | Le nom affiché est suffixé par la locale si la facette est localisée. | `Facets/FacetGroup.tsx` |

### 2.7 Tri

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Bouton « Sort by » | Chips récapitulatifs (attribut + flèche ↑/↓). | `components/Media/Search/Sorting/SortBy.tsx`, `SortByChip.tsx` |
| Éditeur multi-critères | Liste des attributs `sortable`, switch d'activation, switch de sens, poignée de drag. | `Sorting/EditSortBy.tsx`, `SortByRow.tsx` |
| Réordonnancement | Drag & drop (dnd-kit) ; déplacer une ligne l'active. | `Sorting/EditSortBy.tsx` |
| Tri par défaut | `@score` DESC puis `@createdAt` DESC. | `components/Media/Search/search.ts` |
| Groupement par sections | Case « Group by sections » ajoutant des séparateurs entre résultats sur le 1er critère (désactivée si ce critère est `@score`). Un seul champ de groupement envoyé à l'API. | `Sorting/EditSortBy.tsx`, `ResultProvider.tsx` |
| Appliquer / Réinitialiser | Boutons « Apply » et « Reset ». | `Sorting/EditSortBy.tsx` |

### 2.8 Filtre géographique

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| « Autour de moi » | Bouton bascule dans la barre de recherche : demande la position du navigateur et l'injecte comme contexte de recherche (tri / facette geo-distance). Persisté dans le hash d'URL. | `components/Media/Search/GeoPointFilter.tsx`, `hooks/useBrowserLocation.ts`, `ResultProvider.tsx` |

### 2.9 Recherches sauvegardées

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Menu d'actions de la recherche | Clear search / Save Search / Update Search / Save as new search. | `components/Media/Search/SearchMoreAction.tsx` |
| Sauvegarder | Modale avec nom (pré-rempli avec la requête) et confidentialité ; enregistre `query`, `conditions`, `sortBy` (pas la géoloc). | `SavedSearch/SaveSearchDialog.tsx`, `SavedSearchFields.tsx`, `api/savedSearch.ts` |
| Confidentialité (3 niveaux) | **Secret** (moi et utilisateurs autorisés), **Private** (accessible par lien, non listée), **Public** (listée pour tous). | `SavedSearch/SavedSearchFields.tsx`, `types.ts` |
| Mettre à jour | « Update Search » désactivé si aucune modification depuis la sauvegarde (comparaison par checksum). | `SearchMoreAction.tsx` |
| Enregistrer sous | « Save as new search » quand une recherche est déjà chargée. | `SearchMoreAction.tsx` |
| Liste dans le panneau gauche | Section « Saved Searches » avec filtre, item sélectionné surligné, chargement en un clic. | `SavedSearch/SavedSearchList.tsx`, `hooks/useSearch.ts` |
| Gestion (onglets) | Info, Edit (nom + confidentialité), Permissions (ACL), Automations (placeholder « coming soon »). | `components/Dialog/SavedSearch/SavedSearchDialog.tsx`, `InfoSavedSearch.tsx`, `EditSavedSearch.tsx`, `Acl.tsx`, `Automations.tsx` |
| Éditer / Supprimer | Menu « ⋮ » par item selon les capabilities. | `SavedSearchList.tsx` |
| Source d'un widget CMS | Une recherche sauvegardée alimente le widget « Search Grid » d'une page. | `components/Landing/widgets/search-grid/SearchGridWidget.tsx`, `components/Form/SavedSearchSelect.tsx` |

### 2.10 Navigation contextuelle

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Filtrer par workspace | Pose `@workspace = "id"` et retire les conditions collection / corbeille / statut. | `SearchProvider.tsx`, `components/Media/WorkspaceMenuItem.tsx` |
| Filtrer par collection | Pose `@collection = "id"`. | `SearchProvider.tsx`, `components/Media/CollectionMenuItem.tsx` |
| Corbeille | Entrée « Trash » : remplace toutes les conditions par `@deleted = true`. | `components/Media/CollectionsPanel.tsx` |
| Quarantaine | Entrée « Quarantine » : `@assetStatus = quarantined`. | `components/Media/CollectionsPanel.tsx` |

### 2.11 Débogage et retours

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Modale « Search Debug » | Clic sur le compteur de résultats (si l'API renvoie `debug:es`) : requête Elasticsearch JSON, temps ES et temps total, bouton « Copy ». | `components/Media/Search/DebugEsModal.tsx`, `components/AssetList/Toolbar/TotalResults.tsx` |
| Debug console des suggestions | Requêtes ES des suggestions loguées en `console.debug`. | `components/Media/Search/Suggest/useSuggest.tsx` |
| Affichage des erreurs | Bloc d'erreur dédié avec message brut. | `components/AssetSearch/SearchError.tsx` |
| Aucun résultat | Message + suggestions (orthographe, facettes, filtres). | `components/AssetSearch/NoSearchResult.tsx` |

---

## 3. Liste de résultats

### 3.1 Pagination et chargement

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Pagination cumulée | Pages stockées en `Asset[][]`, curseur `next`. | `components/Media/Search/ResultProvider.tsx` |
| Bouton « Load more » | Anti double-déclenchement, état loading. | `components/AssetList/LoadMoreButton.tsx` |
| Scroll infini | Chargement automatique à 30 px du bas du conteneur. | `hooks/useInfiniteScroll.ts` |
| Séparateurs de page | Divider collant « # 2 », « # 3 »… | `components/AssetList/PageDivider.tsx`, `SectionDivider.tsx` |
| Retour en haut | Scroll remis à zéro à chaque nouvelle recherche. | `components/AssetList/useScrollTopPages.ts` |
| Compteur de résultats | « N results » ou « sélection / total », formaté selon la locale. | `components/AssetList/Toolbar/TotalResults.tsx`, `lib/numbers.ts` |
| Loader animé | Spinner en fondu au-dessus de la liste. | `components/AssetList/AnimatedLoader.tsx` |
| Rafraîchissement temps réel | Événement `RENDITION_UPDATE` (canal `ASSETS`) : rechargement de l'asset concerné. | `ResultProvider.tsx`, `lib/pusher.ts` |
| Store d'assets partagé | Les assets affichés reflètent les mises à jour faites hors recherche. | `store/assetStore.ts` |

### 3.2 Layouts

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Layout Grille | Grille responsive, largeur d'item = taille de vignette, transitions animées, contrôles en overlay au survol. | `components/AssetList/Layouts/Grid/GridLayout.tsx`, `GridPage.tsx`, `Grid/AssetItem.tsx` |
| Layout Liste | Liste virtualisée (react-virtualized), hauteur de ligne mesurée dynamiquement ; vignette à gauche, attributs à droite. | `Layouts/List/ListLayout.tsx`, `List/AssetItem.tsx` |
| En-têtes flottants (liste) | Couche synchronisée au scroll reproduisant les dividers de groupe/page. | `Layouts/VirtualizedGroups.tsx`, `Layouts/page.ts` |
| Vignette d'asset | Thumbnail, miniature animée au survol, icône de type si absence de rendu, puce de type de fichier, puces « Story » et « Deleted ». | `components/Media/Asset/AssetThumb.tsx`, `Thumb.tsx` |
| Vignette de story | Pile de vignettes des enfants. | `components/Media/Asset/StoryThumb.tsx` |
| Zones de carte configurables (grille) | Valeurs d'attributs positionnées sur la vignette (grille 3×3) ou dans un bandeau sous la vignette, variantes rich / chip / text, tailles S/M/L, 12 couleurs de chips, libellé optionnel. Piloté par le profil d'affichage. | `Layouts/Grid/GridCardZone.tsx`, `Layouts/Grid/resolveGridItem.ts`, `store/profileStore.ts` |
| Contenu par défaut de la carte | Sans profil grille : nom (avec surlignage), tags, collections cliquables. | `Layouts/Grid/AssetItem.tsx`, `components/Media/Asset/Widgets/AssetTagList.tsx`, `AssetCollectionList.tsx` |
| Actions de quarantaine en liste | Les assets en quarantaine exposent leurs actions dans la ligne. | `Layouts/List/AssetItem.tsx`, `components/Media/Asset/QuarantineActions.tsx` |
| Overlay d'item personnalisable | Rendu custom superposable par item (paniers, éditeur en masse). | `components/AssetList/types.ts` |

### 3.3 Groupement

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Séparateurs de groupe | Divider collant portant la valeur du groupe formatée selon le type ; « None » si vide ; combinable avec le n° de page. | `Layouts/GroupDivider.tsx`, `GroupRow.tsx` |
| Changer le format du groupe | Bouton œil au survol du divider pour changer le format d'affichage du type. | `Layouts/GroupDivider.tsx` |
| Rendus spécialisés | Tags en pastilles colorées, collections en chips. | `components/AssetList/GroupValue/types.tsx` |

### 3.4 Sélection

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Sélection simple | Clic sur un item remplace la sélection. | `components/AssetList/selection.ts`, `Layouts/AssetItemWrapper.tsx` |
| Ctrl/Cmd + clic | Ajoute / retire un item. | `components/AssetList/selection.ts` |
| Shift + clic | Sélection de plage à travers les pages. | `components/AssetList/selection.ts` |
| Case à cocher par item | Coche sans propager le clic. | `Layouts/Grid/AssetItem.tsx`, `Layouts/List/AssetItem.tsx` |
| Tout sélectionner | Case maître avec état indéterminé et badge du nombre sélectionné. | `components/AssetList/Toolbar/SelectionActions.tsx` |
| Ctrl/Cmd + A | Sélectionne tous les items chargés ; ignoré si le focus est dans un champ ; pile de listeners pour que seule la liste la plus récente réponde. | `hooks/useSelectAllKey.ts` |
| Réinitialisation automatique | Sélection vidée à chaque nouvelle recherche. | `components/AssetList/AssetList.tsx` |
| Assets désactivés | Liste `disabledAssets` non sélectionnables. | `components/AssetList/AssetList.tsx` |
| Sélection contrôlée | Props `defaultSelection`, `subSelection`, `onSelectionChange` pour réutiliser la liste en mode sélecteur. | `components/AssetList/AssetList.tsx`, `AssetSelection.tsx`, `context/AssetSelectionContext.tsx` |

### 3.5 Actions de masse (toolbar de sélection)

Affichées si au moins un item est sélectionné, filtrées par le contexte d'actions de la liste et par les capabilities des assets.

| Action | Description | Fichiers |
|---|---|---|
| Ajouter au panier | Ajoute la sélection au panier courant (toast), ou ouvre le panier / la liste des paniers. | `components/AssetList/Toolbar/WithSelectionActions.tsx`, `components/Basket/BasketSwitcher.tsx` |
| Exporter / Télécharger | Modale de choix des renditions (groupées par workspace), suivi de l'export. | `components/Media/Asset/Actions/ExportAssetsDialog.tsx`, `store/assetExportStore.ts`, `api/export.ts` |
| Copier | Destination via arbre, copie **par référence (raccourci)** ou copie dure avec options « Copy attributes » / « Copy tags » ; avertissements pour les assets non copiables par référence. | `components/Media/Asset/Actions/CopyAssetsDialog.tsx`, `components/Form/CollectionTreeWidget.tsx` |
| Déplacer | Arbre de destination filtré sur `createAsset`. | `components/Media/Asset/Actions/MoveAssetsDialog.tsx` |
| Éditer | 1 asset → modale d'édition ; N assets → éditeur d'attributs en masse (bloqué si workspaces différents). | `WithSelectionActions.tsx`, `components/AttributeEditor/` |
| Partager | Un seul asset à la fois. | `components/Share/ShareAssetDialog.tsx` |
| Supprimer / Corbeille | Choix « Move to trash » ou retrait de collections précises, avertissement si assets partagés. | `components/Media/Asset/Actions/DeleteAssetsConfirmDialog.tsx` |
| Supprimer définitivement | Exige de taper « Delete » pour confirmer. | `DeleteAssetsConfirmDialog.tsx` |
| Restaurer | Pour les assets en corbeille ; liste les non restaurables (collection supprimée) avec restauration de la collection. | `components/Media/Asset/Actions/RestoreAssetsConfirm.tsx`, `components/Media/Collection/CollectionRestoreConfirmDialog.tsx` |
| Actions personnalisées | `extraActions` injectables (label single/multi, icône, couleur, rechargement, reset de sélection). | `components/AssetList/types.ts` |
| Contexte d'actions | Drapeaux `basket / layout / export / edit / move / copy / replace / share / delete / restore / info / open / saveAs`. | `components/AssetList/actionContext.ts` |

### 3.6 Menu contextuel par asset

Ouvert par clic droit ou bouton « ⋮ ».

| Entrée | Description | Fichiers |
|---|---|---|
| Open | Ouvre la visionneuse. | `components/AssetList/AssetContextMenu.tsx`, `hooks/useAssetActions.ts` |
| Add to basket | Ajoute au panier courant. | `AssetContextMenu.tsx` |
| Save as | Enregistrer le fichier source sous différentes formes. | `components/Media/Asset/Actions/SaveAsButton.tsx` |
| Liens alternatifs | Une entrée par `alternateUrl` du fichier principal. | `AssetContextMenu.tsx` |
| Info / Download / Share / Edit / Move / Copy | Équivalents unitaires des actions de masse. | `hooks/useAssetActions.ts` |
| Replace source file | Remplacement du fichier source (fichier ou URL). | `components/Media/Asset/Actions/ReplaceAssetSourceDialog.tsx` |
| Delete / Restore | Suppression ou restauration unitaire. | `hooks/useAssetActions.ts` |
| Double-clic | Ouvre la visionneuse avec le contexte de navigation (suivant / précédent). | `Layouts/Grid/GridPage.tsx`, `components/AssetSearch/useOpenAsset.ts` |

### 3.7 Options d'affichage

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Bouton Display Settings | Ouvre le menu d'options. | `components/AssetList/Toolbar/DisplaySettingButton.tsx` |
| Choix du layout | Grid View / List View. | `Toolbar/DisplayOptionsMenu.tsx` |
| Taille des vignettes | Slider 60 → 400 px (défaut 200). | `Toolbar/ThumbSizeWidget.tsx` |
| Prévisualisation au survol | Interrupteur « Display preview on hover ». | `DisplayOptionsMenu.tsx` |
| Lecture auto des vidéos | « Auto play video previews ». | `DisplayOptionsMenu.tsx` |
| Fichier / attributs dans la preview | Interrupteurs « Display File in preview » et « Display attributes in preview ». | `DisplayOptionsMenu.tsx` |
| Taille de la preview | Slider 20–80 % de la fenêtre. | `Toolbar/SizeRatioWidget.tsx` |
| Répartition preview / attributs | Slider 20–80 %. | `DisplayOptionsMenu.tsx` |

### 3.8 Prévisualisation au survol

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Popover de prévisualisation | Délai d'entrée 50 ms / sortie 100 ms, fermeture automatique quand les résultats changent. | `components/AssetList/usePreview.ts`, `PreviewPopover.tsx` |
| Contenu | Lecteur de média + attributs épinglés, dimensionnés selon les ratios configurés. | `PreviewPopover.tsx` |
| Verrouillage | Mode « locked » : preview interactive (contrôles vidéo, attributs scrollables) avec cadenas rouge pour fermer. | `PreviewPopover.tsx`, `usePreview.ts` |
| Repositionnement | Reste dans le viewport (flip + preventOverflow). | `PreviewPopover.tsx` |

### 3.9 Divers

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Upload par collage (Ctrl+V) | Une image collée ouvre le dialogue d'upload. | `components/Media/Asset/AssetDropzone.tsx`, `lib/ImagePaste.ts` |
| Bouton flottant d'ajout | FAB « + » en bas à droite (si authentifié). | `components/AssetSearch/AssetSearch.tsx` |
| Toolbar collante | Barre de recherche + actions + compteur + réglages collée en haut. | `components/AssetList/AssetToolbar.tsx` |
| Adaptation mobile | Icônes de boutons masquées sous `md`, panneau latéral refermé. | `Toolbar/SelectionActions.tsx`, `components/App.tsx` |

---

## 4. Visionneuse d'asset

Route `/assets/:id/:renditionId`, modale plein écran.

### 4.1 Vue générale

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Visionneuse plein écran | Deux colonnes : média redimensionné dynamiquement + panneau latéral de 400 px. Non fermable par Échap. Rafraîchissement temps réel (canal `asset-{id}`, événement `asset_ingested`). | `components/Media/Asset/View/AssetView.tsx` |
| Sélecteur de rendition | Liste déroulante en en-tête pour basculer entre renditions (change l'URL). | `View/AssetViewHeader.tsx` |
| Navigation entre assets | Flèches précédent / suivant alimentées par la liste d'origine. Raccourcis `←` / `→`. | `components/Media/Asset/AssetViewNavigation.tsx` |
| Carrousel de story | Bandeau horizontal des assets enfants d'une story (miniatures cliquables, « +N more »). | `View/StoryCarousel.tsx` |
| Barre d'actions | Suivre, Télécharger, Éditer (+ « Substituer le fichier »), « Enregistrer sous », Partager, Supprimer / Restaurer, selon les capabilities. | `components/Media/Asset/Actions/AssetViewActions.tsx`, `hooks/useAssetActions.ts` |
| Abonnement aux notifications | Bouton « Follow » avec 3 sujets : mise à jour, suppression, nouveau commentaire. | `Actions/AssetViewActions.tsx`, `components/Ui/FollowButton.tsx` |
| Superposition d'intégration | Une intégration peut afficher un composant par-dessus le lecteur ou le remplacer. | `View/AssetView.tsx`, `components/Media/Asset/FileIntegrations.tsx` |
| Suivi d'audience | Impressions Matomo à l'ouverture et interactions « click » sur image / PDF. | `View/AssetView.tsx`, `Players/FileToolbar.tsx` |
| Asset introuvable | Message si inaccessible ou supprimé. | `View/AssetView.tsx` |

### 4.2 Panneau latéral (accordéons)

| Section | Description | Fichiers |
|---|---|---|
| Attributes | Attributs formatés avec contrôles (épingle, copie, format, annotations liées). | `components/Media/Asset/AssetAttributes.tsx` |
| Information | ID, propriétaire, dates, workspace, collection de référence, fichier source (liens + copie). | `AssetViewInfo.tsx`, `AssetInfoList.tsx` |
| Appears in | Collections où l'asset apparaît (raccourcis). | `AssetAppearsIn.tsx` |
| Metrics | Statistiques Matomo : lectures, finitions, taux de lecture / finition / plein écran, visites. | `AssetMatomoMetricsView.tsx`, `AssetMatomoMetricsList.tsx` |
| Attachments | Pièces jointes : téléchargement, renommage, détachement, suppression, ajout. | `AssetAttachments.tsx`, `Actions/AddAttachmentDialog.tsx`, `Attachment/RenameAttachmentDialog.tsx` |
| Discussion | Fil de commentaires (déplié par défaut), avec annotations en pièces jointes. | `AssetDiscussion.tsx`, `View/useBindAnnotationMessage.ts` |
| Integrations | Intégrations disponibles pour le fichier affiché. | `FileIntegrations.tsx` |

### 4.3 Lecteurs de médias

Aiguillage par type MIME dans `components/Media/Asset/FilePlayer.tsx`. Si le fichier est en cours d'analyse ou rejeté, une puce d'analyse remplace le lecteur.

| Lecteur | Description | Fichiers |
|---|---|---|
| Image | `<img>` avec gestion du SVG, `crossOrigin` pour les annotations sur canvas. | `Players/ImagePlayer.tsx` |
| Vidéo | ReactPlayer : lecture/pause, boucle, progression bufferisée, autoplay selon préférence, pause automatique hors viewport, attributs Matomo média. | `Players/VideoPlayer.tsx` |
| Audio | Waveform Wavesurfer avec timeline et zoom, lecture/pause, compteur temps / durée. | `Players/AudioPlayer.tsx` |
| PDF | Rendu page par page (react-pdf), navigation `n / total`, saut à la page de la dernière annotation. | `Players/PDFPlayer.tsx` |
| Autres documents | Icône de type de fichier en repli. | `AssetFileIcon.tsx`, `AssetTypeIcon.tsx` |
| Barre d'outils fichier | Barre flottante repliable : pages, zoom, annotation. | `Players/FileToolbar.tsx`, `Players/ToolbarPaper.tsx` |
| Zoom / pan | Zoom avant / arrière, réinitialisation, « fit to screen », mode main. Échelle 0.1 à 100 ; le PDF est rechargé en meilleure résolution selon le zoom. | `Players/ZoomControls.tsx` |

### 4.4 Annotations

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Outils de dessin | Cible, dessin libre, cercle, ligne, flèche, texte, rectangle, plus **cue** et **time_range** (annotations temporelles). | `components/Media/Asset/Annotations/annotationTypes.ts`, `Annotations/shapes/*` |
| Barre d'annotation | Mode annotation, choix de forme, couleur, taille de trait, réappliqués en direct à la forme sélectionnée. | `Annotations/AnnotateToolbar.tsx` |
| Mémorisation des options | Dernière couleur et taille mémorisées par type de forme (session). | `Annotations/defaultOptions.ts` |
| Manipulation | Sélection, déplacement, redimensionnement, duplication, suppression avec confirmation, nommage. | `Annotations/ShapeControl.tsx`, `editCanvas.ts`, `useAnnotationDraw.ts` |
| Raccourcis | `Échap` annule / désélectionne, `Suppr` supprime, `Espace` maintenu = pan. | `Annotations/AnnotateWrapper.tsx` |
| Annotation ↔ discussion | Chaque annotation devient une pièce jointe du message en cours ; cliquer une pièce jointe réaffiche l'annotation. | `View/useBindAnnotationMessage.ts` |
| Annotations d'attribut | Un attribut peut porter des annotations projetées sur le média via une icône. | `Attribute/AttributeRowUI.tsx` |
| Annotations d'intégration | AWS Rekognition projette ses résultats en rectangles colorés. | `components/Integration/AwsRekognition/AwsRekognitionAssetEditorActions.tsx` |

---

## 5. Attributs

### 5.1 Affichage dans la fiche

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Rendu par définition | Groupement par définition, meilleure locale disponible, listes pour les multi-valeurs. | `components/Media/Asset/Attribute/Attributes.tsx`, `attributeIndex.ts` |
| Profil d'affichage | Ordre et contenu selon le profil actif (épinglés, built-in, séparateurs, espaceurs, « afficher même si vide »). | `Attribute/Attributes.tsx`, `store/profileStore.ts` |
| Épinglage | Punaise pour ajouter / retirer une définition du profil. | `Attribute/AttributeRowUI.tsx` |
| Copie de valeur | Icône copier au survol, sur la valeur ou chaque item de liste. | `Attribute/CopyAttribute.tsx` |
| Changement de format | Icône œil faisant tourner les formats du type ; choix mémorisé par type et par définition (session). | `Attribute/Format/AttributeFormatProvider.tsx`, `AttributeFormatContext.ts` |
| Valeurs invalides | Icône d'alerte + repli texte brut. | `Attribute/InvalidAttributeIcon.tsx` |
| Surlignage | Balises `[hl]…[/hl]` de la recherche rendues en surbrillance. | `Attribute/AttributeHighlights.tsx` |
| Support RTL | Direction `rtl` selon la locale de l'attribut. | `Attribute/AttributeRowUI.tsx`, `lib/lang.ts` |

### 5.2 Types d'attributs, widgets et formats

Source : `api/types.ts` (`AttributeType`) et `components/Media/Asset/Attribute/types/index.ts`.

| Type | Widget d'édition | Formats d'affichage |
|---|---|---|
| `text` | Champ texte | — |
| `textarea` | Champ multiligne | — |
| `code`, `web_vtt` | Éditeur de code (Ace) | — |
| `html` | Éditeur HTML, rendu riche | — |
| `json` | Éditeur JSON | — |
| `boolean` | Tri-état nullable | Label, Binaire, Pouces, True/False |
| `number` | Champ numérique | Original, Entier, Formaté, Fixe (2 déc.), Scientifique |
| `date` | Champ date | ll, L, LLLL… |
| `date_time` | Champ date-heure | Medium, Short, Long, Relative, ISO |
| `duration` | — | Compact, Formaté, Humanisé, Original |
| `filesize` | — | Humanisé, Humanisé base 10, Original |
| `color` | Color picker | Pastille, Hexadécimal |
| `geo_point` | Champ « lat, lng » + carte | Coords, Carte (Leaflet), JSON |
| `entity` | Select d'entité avec création à la volée | Full, Emoji, Couleur |
| `tag` | Sélecteur de tags | — |
| `user` | Sélecteur d'utilisateur | UserChip |
| `workspace` | Sélecteur de workspace | WorkspaceChip |
| `collection_path` | Lecture seule | CollectionChip |
| `story` | Lecture seule | CollectionStoryChip |
| `rendition` | Sélecteur de définition de rendition | — |
| `privacy` | Widget de confidentialité | Full, Short |
| `assetStatus` | Sélecteur de statut | AssetStatusChip |

Types déclarés sans widget dédié (repli texte) : `file_type`, `id`, `ip`, `keyword`.

### 5.3 Édition mono-asset (onglet Edit et upload)

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Formulaire d'attributs | Un champ par définition `editable && editableInGui`, filtré par cible Asset / Story ; les autres en lecture seule. | `Attribute/AttributesEditor.tsx` |
| Multi-valeurs | Ajout / suppression avec « Add {{name}} » et « Remove », autofocus. | `Attribute/MultiAttributeRow.tsx` |
| Multilingue | Onglets par locale (drapeau) + onglet « Untranslated » ; alerte si aucune locale dans le workspace. | `Attribute/TranslatableAttributeTabs.tsx` |
| Correction de valeur invalide | Message + bouton « Correct » restaurant le widget typé. | `Attribute/AttributeWidget.tsx` |
| Lien « Manage list » | Accès direct à la gestion de la liste d'entités liée. | `Attribute/AttributesEditor.tsx` |
| Calcul du diff | Actions batch set / add / replace / delete par comparaison avec les valeurs distantes. | `Attribute/useAttributeEditor.ts`, `Attribute/BatchActions.ts`, `api/asset.ts` |
| Garde-fou de navigation | Prompt si le formulaire est sale. | `components/Dialog/Asset/EditAsset.tsx` |

### 5.4 Éditeur d'attributs en masse (`/attributes/editor`)

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Layout 4 zones redimensionnables | Bandeau de vignettes en haut, définitions à gauche, panneau d'édition au centre, suggestions à droite. | `components/AttributeEditor/AttributeEditor.tsx`, `Resizable.tsx` |
| Sous-sélection d'assets | La grille de vignettes restreint l'édition à un sous-ensemble ; « Remove from selection ». | `AttributeEditor.tsx`, `context/AssetSelectionContext.tsx` |
| Raccourcis | `Ctrl/Cmd+A` (tout sélectionner), `Tab` / `Shift+Tab` (définition suivante / précédente), `Entrée` (valider une valeur multi). | `hooks/useSelectAllKey.ts`, `AttributeEditor/shortcuts.ts`, `MultiAttributeRow.tsx` |
| Liste des définitions | Valeur commune, nombre de valeurs, cadenas si non éditable, marqueur **« Indeterminate »** si valeurs divergentes. | `AttributeEditor/Attributes.tsx` |
| Assets non éligibles | Assets dont le type ne correspond pas à la cible sont grisés. | `attributeGroup.ts`, `api/asset.ts` |
| Édition mono-valeur | Widget typé avec état indéterminé (`[multiple values]`), saisie debouncée 500 ms. | `AttributeEditor/EditorPanel.tsx`, `AttributeWidget.tsx` |
| Édition multi-valeurs | Valeurs distinctes avec pourcentage de couverture, boutons « ajouter à tous » / « retirer de tous ». | `AttributeEditor/MultiAttributeRow.tsx`, `PartPercentage.tsx` |
| Application par asset | Overlay +/- sur chaque vignette pour ajouter / retirer une valeur asset par asset. | `AttributeEditor/AssetToggleOverlay.tsx` |
| Multilingue | Onglets de locale + « Untranslated ». | `EditorPanel.tsx` |
| Pseudo-définition « Tags » | Les tags sont édités comme un attribut multiple supplémentaire. | `attributeGroup.ts` |
| Onglet Preview | Média de l'asset courant avec navigation dans la sous-sélection. | `Suggestions/Preview.tsx` |
| Onglet Values | Valeurs distinctes triées par fréquence, barre de pourcentage, comparaison aux valeurs originales, menu : « Appliquer aux assets sélectionnés » et « Sélectionner les assets ayant cette valeur ». | `Suggestions/ValuesSuggestions.tsx`, `SuggestionPanel.tsx` |
| Undo / Redo | Historique complet (valeurs, sous-sélection, définition courante). | `attributeGroup.ts`, `AttributesToolbar.tsx` |
| Aperçu du diff avant sauvegarde | Modale « Confirm Changes? » listant par définition chaque action (Delete / Add / Set), locale et nombre d'assets. | `SavePreviewDialog.tsx`, `ValueDiff.tsx`, `batchActions.ts` |
| Annulation protégée | Confirmation « Discard changes? ». | `AttributesToolbar.tsx` |
| Préférences dédiées | Le bandeau utilise la clé `displayBatchEdit`, distincte de la liste normale. | `AttributeEditor.tsx`, `components/Media/DisplayProvider.tsx` |

### 5.5 Entités d'attributs (listes contrôlées)

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Listes d'entités (Entity Lists) | CRUD des référentiels : nom, accepter de nouvelles valeurs, approbation automatique, traductions, synonymes, emojis, couleurs. | `components/Dialog/Workspace/EntityListManager.tsx`, `api/entityList.ts` |
| Formulaire d'entité | Valeur, statut (Pending / Approved), emoji, couleur, traductions et synonymes par locale, selon les options de la liste. | `components/AttributeEntity/AttributeEntityFields.tsx` |
| Création à la volée | Depuis un champ d'attribut, si la liste l'autorise. | `components/AttributeEntity/CreateAttributeEntityDialog.tsx`, `components/Form/AttributeEntitySelect.tsx` |
| Modération | Valeurs en attente en italique orange dans la fiche ; boutons pouce haut / bas dans le gestionnaire. | `Attribute/types/AttributeEntityType.tsx`, `components/Dialog/Workspace/AttributeEntityManager.tsx` |
| Gestionnaire de liste | Recherche, CRUD, suppression en masse, **fusion d'entités**, menu Export / Import / Vider la liste (confirmation par saisie du nom). | `AttributeEntityManager.tsx`, `MergeEntitiesDialog.tsx` |
| Export | Formats LiForm (Uploader), JSON, CSV ; choix de la locale ou toutes. | `components/Dialog/AttributeEntity/ExportAttributeEntitiesDialog.tsx` |
| Import | Import CSV. | `components/Dialog/AttributeEntity/ImportAttributeEntitiesDialog.tsx` |
| Rendu d'entité | Libellé traduit + pastille de couleur ; formats Full / Emoji / Couleur. | `Attribute/AttributeEntityListText.tsx`, `api/attributeEntity.ts` |

---

## 6. Gestion et actions sur les assets

### 6.1 Modale de gestion (`/assets/:id/manage/:tab`)

| Onglet | Description | Fichiers |
|---|---|---|
| Open | Raccourci vers la visionneuse. | `components/Dialog/Asset/AssetDialog.tsx` |
| Info | ID, propriétaire, dates, workspace, collection, fichier source (copiables et cliquables). | `components/Dialog/Asset/InfoAsset.tsx`, `components/Dialog/Info/InfoRow.tsx` |
| Edit | Tags, confidentialité, éditeur d'attributs ; sauvegarde combinée. | `components/Dialog/Asset/EditAsset.tsx` |
| Renditions | Liste avec lecteur, taille, badges (verrouillée, substituée, projection), suppression, upload de substitution, « Enregistrer sous », emplacements vides pour définitions non générées, bouton « Create custom rendition ». Temps réel sur `RENDITION_UPDATE`. | `components/Dialog/Asset/Rendition/Renditions.tsx`, `Rendition.tsx`, `RenditionPlaceholder.tsx`, `RenditionStructure.tsx` |
| Versions | Historique des versions du fichier source, suppression. | `components/Dialog/Asset/AssetFileVersions.tsx`, `AssetFileVersion.tsx` |
| Permissions | ACL de l'asset (View, Edit Attributes, Manage, Delete, Owner) avec ACL parentes. | `components/Dialog/Asset/Acl.tsx`, `AssetAclForm.tsx` |
| Workflow | Derniers workflows, statut, « View », « Trigger workflow again ». | `components/Dialog/Asset/AssetWorkflow.tsx` |
| Operations | Collection de référence, raccourcis (suppression), zone de danger : suppression / restauration avec saisie du nom. | `components/Dialog/Asset/OperationsAsset.tsx` |
| ES Document | Document Elasticsearch brut + resynchronisation (rôle `tech`). | `components/Dialog/Asset/ESDocument.tsx` |

### 6.2 Actions

| Action | Description | Fichiers |
|---|---|---|
| Export / Téléchargement | Choix des renditions, téléchargement, suivi de l'export. | `components/Media/Asset/Actions/ExportAssetsDialog.tsx`, `api/export.ts` |
| Copier / Déplacer / Supprimer / Restaurer | Voir §3.5. | `components/Media/Asset/Actions/*` |
| Substituer le fichier source | Upload multipart ou URL. | `Actions/ReplaceAssetSourceDialog.tsx`, `SingleFileUploadWidget.tsx` |
| « Save as » | Menu : **Nouvel asset**, **Rendition**, **Remplacer le fichier source**. | `Actions/SaveAsButton.tsx`, `SaveFileAsNewAssetDialog.tsx`, `SaveFileAsRenditionDialog.tsx`, `ReplaceAssetWithFileDialog.tsx` |
| Upload de rendition | Fichier ou URL pour une définition de rendition. | `Actions/UploadRenditionDialog.tsx` |
| Rendition dynamique personnalisée | Source, **recadrage interactif** (Original / 1:1 / 4:3 / 3:4 / 16:9 / personnalisé, zoom 1→5), dimensions max, format (Identique / JPEG / PNG / WebP), noir & blanc, écriture des attributs en métadonnées. | `components/Dialog/Asset/Rendition/CreateDynamicRenditionDialog.tsx` |
| Ajouter une pièce jointe | Upload créant un asset rattaché. | `Actions/AddAttachmentDialog.tsx`, `api/attachment.ts` |
| Partager | Voir §12. | `components/Share/ShareAssetDialog.tsx` |
| Raccourcis d'asset | Un asset a une collection propriétaire et des raccourcis dans d'autres collections ; suppression individuelle. | `components/Dialog/Asset/OperationsAsset.tsx`, `components/Media/Asset/Widgets/AssetCollectionList.tsx` |
| Stories | Une collection peut être portée par un asset « story » ; chips dédiés. | `components/Ui/CollectionStoryChip.tsx`, `CollectionOrStoryChip.tsx` |

---

## 7. Fichiers, analyse et quarantaine

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Modale fichier (`/files/:id/manage/:tab`) | Onglets Info et Metadata. | `components/Dialog/File/FileDialog.tsx` |
| Onglet Info | ID, URL, type MIME, « Referenced by » (source / version / rendition de quel asset), taille, état d'analyse. | `components/Dialog/File/InfoFile.tsx` |
| Onglet Metadata | Métadonnées techniques brutes JSON avec « Refresh ». | `components/Dialog/File/FileMetadata.tsx`, `api/file.ts` |
| Rapport d'analyse | Statut global (succès / ignoré / rejeté) + détail par analyseur. | `components/Dialog/File/FileAnalysisReport.tsx`, `AnalyzerResults.tsx` |
| Puce d'analyse | « Analysis in progress… » ou « Rejected », affichée à la place du lecteur. | `components/Media/Asset/FileAnalysisChip.tsx`, `FileAnalysisChipWrapper.tsx` |
| Bandeau quarantaine | Détail des analyses d'un asset en quarantaine. | `components/Media/Asset/QuarantineActions.tsx`, `Quarantine/AnalyzerCard.tsx`, `AnalyzerResult.tsx` |
| Analyseurs supportés | `checksum`, `doc_unique_id`, `filename`, `image_colorspace`, `image_dimension`, `debug`, avec niveaux Debug → Critical. | `Quarantine/analysisTypes.ts`, `Quarantine/analyzers/*`, `Quarantine/severity.ts` |
| Détection de doublons | Liste des doublons avec vignette, date, aperçu au survol, navigation. | `Quarantine/DuplicateAssets.tsx`, `DuplicateAssetRow.tsx`, `api/file.ts` |
| Actions de quarantaine | **By pass**, **Merge duplicates**, **Move to trash**, **Delete permanently**. | `Quarantine/QuarantineActionBar.tsx`, `api/asset.ts` |
| Fusion de doublons | Choix champ par champ entre fichier entrant et asset existant. | `Quarantine/MergeDuplicatesDialog.tsx` |
| Ajout comme nouvelle version | Rattacher le fichier en quarantaine comme version d'un asset existant. | `Quarantine/AddAsVersionDialog.tsx` |
| Permissions liées | `QUARANTINE` (voir les assets en quarantaine) et `QUARANTINE_BY_PASS` dans les ACL workspace / collection. | `components/Permissions/permissionsTypes.ts` |

---

## 8. Upload

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Drag & drop | Zone de dépôt avec surbrillance ; types acceptés issus de la configuration serveur. | `components/Upload/UploadDropzone.tsx`, `Upload/useAccept.tsx` |
| Dépôt global | Toute la liste d'assets est une zone de dépôt. | `components/Media/Asset/AssetDropzone.tsx` |
| Liste des fichiers | Cartes avec vignette (images), taille, type, suppression individuelle, « Reset », compteur. | `Upload/FileToUploadCard.tsx`, `FileCard.tsx`, `FileBlobThumb.tsx` |
| Upload par URL | Plusieurs URL (une par ligne), validation, case « Importer le(s) fichier(s) » (copie serveur vs référence). | `Upload/UploadDialog.tsx`, `api/asset.ts` |
| Destination | Arbre workspace / collections filtré sur `createAsset`, création d'une collection à la volée. | `Upload/UploadForm.tsx`, `components/Form/CollectionTreeWidget.tsx` |
| Confidentialité | Champ privacy, désactivé si la collection l'interdit, affichage de la valeur héritée. | `Upload/UploadForm.tsx`, `api/collection.ts` |
| Tags | Sélecteur multi-tags du workspace. | `Upload/UploadForm.tsx` |
| Attributs à l'upload | Formulaire d'attributs complet appliqué au lot. | `Upload/UploadAttributes.tsx` |
| Mode Story | Titre, tags et attributs distincts (cible Story) ; crée la story puis y verse les fichiers. | `Upload/StoryForm.tsx`, `api/asset.ts` |
| Mode Quiet | « Quiet (no notification, no webhook) » → en-têtes `X-Webhook-Disabled` / `X-Notification-Disabled`. | `Upload/UploadForm.tsx` |
| Templates de valeurs | Sélection de templates pré-remplissant tags, privacy et attributs. | `Upload/UploadForm.tsx`, `api/templates.ts`, `components/Form/AssetDataTemplateSelect.tsx` |
| Enregistrer comme template | Nom, remplacer le template appliqué, mémoriser collection (+ sous-collections), attributs, privacy, tags, rendre public. | `Upload/SaveAsTemplateForm.tsx`, `Attribute/useAssetDataTemplateOptions.ts` |
| Upload multipart et progression | Paramétré par la config (`maxPartNumber`, `minChunkSize`, `maxChunkSize`, `maxFileSize`), 2 fichiers en parallèle, progression par fichier. | `api/asset.ts`, `store/uploadStore.ts` |
| Uploads en cours | Modale « Pending Uploads » : progression, erreurs, « Cancel », puce « Uploaded ». | `Upload/PendingUploadsDialog.tsx`, `FileProgressCard.tsx` |
| Nommage automatique | Nom dérivé du fichier ou de l'URL (tronqué à 255). | `api/asset.ts` |
| Protection de navigation | Prompt si fichiers en attente ou formulaire sale. | `Upload/UploadDialog.tsx`, `UploadForm.tsx` |

---

## 9. Workspaces (administration)

Dialogue `/workspaces/:id/manage/:tab`, onglets conditionnés par `capabilities.edit` / `editPermissions`.

| Onglet / fonctionnalité | Description | Fichiers |
|---|---|---|
| Info | ID (copiable), propriétaire, date de création. | `components/Dialog/Workspace/InfoWorkspace.tsx` |
| Édition | Titre (traduisible), Public, locales activées (ordonnées), locales de repli, rétention de la corbeille (jours), statut par défaut des assets, « Requires File Analysis ». | `components/Dialog/Workspace/EditWorkspace.tsx`, `components/Form/WorkspaceForm.tsx` |
| Permissions | ACL utilisateurs / groupes (voir §13). | `components/Dialog/Workspace/Acl.tsx`, `WorkspaceAclForm.tsx` |
| Tags | CRUD : nom traduisible, couleur, recherche. | `components/Dialog/Workspace/TagManager.tsx`, `api/tag.ts` |
| Entités | Listes d'entités et gestion des entités (voir §5.5). | `EntityListManager.tsx`, `AttributeEntityManager.tsx` |
| Définitions d'attributs | CRUD ordonnable (drag & drop) : nom traduisible, slug, type, activé, politique, liste d'entités, cible, type d'asset, recherchable, éditable, éditable dans l'IHM, triable, dans les suggestions, traduisible, multi-valeurs, valeurs invalides autorisées, facettes, fallback, valeurs initiales, lecture / écriture de métadonnées (toutes renditions ou sélection), remplissage depuis le nom, usage comme nom d'asset + priorité. | `components/Dialog/Workspace/AttributeDefinitionManager.tsx`, `DefinitionManager/*`, `api/attributes.ts` |
| Politiques d'attributs | Nom, Public, Éditable ; sinon ACL dédiée. Chips Public / Private / Editable / Read only. | `components/Dialog/Workspace/AttributePolicyManager.tsx` |
| Définitions de renditions | CRUD ordonnable : nom, politique, parent, cible, substituable, écriture de métadonnées, mode de construction, définition. | `components/Dialog/Workspace/RenditionDefinitionManager.tsx`, `api/rendition.ts` |
| Politiques de renditions | Nom, Public, ACL View / Edit. | `components/Dialog/Workspace/RenditionPolicyManager.tsx`, `RenditionPolicyPermissions.tsx` |
| Politiques d'assets | Nom, Enabled, utilisateurs et groupes ciblés, liste d'actions. | `components/Dialog/Workspace/AssetPolicyManager.tsx`, `api/assetPolicy.ts`, `components/Form/AssetPolicy/` |
| Intégrations | CRUD (voir §17.6). | `components/Dialog/Workspace/IntegrationManager.tsx` |
| Règles de filtrage | Voir §15. | `components/Dialog/Workspace/FilterRulesTab.tsx` |
| Menu contextuel (arbre) | Ajouter une collection, ajouter un asset, éditer le workspace. | `components/Media/WorkspaceMenuItem.tsx` |

---

## 10. Collections et arborescence

### 10.1 Arbre de navigation (panneau gauche)

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Arbre workspaces / collections | Workspaces en sous-en-têtes collants, collections imbriquées, expansion / repli, enfants paginés (« Load more »), double-clic sur la flèche = rechargement. | `components/Media/CollectionsPanel.tsx`, `WorkspaceMenuItem.tsx`, `CollectionMenuItem.tsx`, `components/Media/Collection/LoadMoreCollections.tsx`, `store/collectionStore.ts` |
| Filtrage de la recherche | Clic = filtre sur le workspace ou la collection, hiérarchie surlignée. | `components/Media/Search/SearchContext.tsx` |
| Recherche de collections | Barre dédiée ; résultats en liste plate avec chip du workspace et surlignage. | `CollectionsPanel.tsx`, `hooks/useSearch.ts`, `components/Ui/SearchBar.tsx` |
| Indicateurs visuels | Dossier plein (privé), outlined (public), partagé ; nom barré si supprimée. | `CollectionMenuItem.tsx` |
| Menu contextuel de collection | Notifications, Ajouter un asset, Créer une sous-collection, Éditer, Supprimer / Restaurer, selon capabilities. | `CollectionMenuItem.tsx` |
| Corbeille et Quarantaine | Entrées fixes en bas de l'arbre. | `CollectionsPanel.tsx` |
| Arbre sélectionnable réutilisable | Sélection simple / multiple, branches désactivables, expansion automatique, nœuds virtuels « New Collection » créés à la validation. | `components/Media/Collection/CollectionTree/CollectionsTreeView.tsx`, `CollectionEdit.tsx` |
| Renommage inline | `Entrée` valide, `Échap` annule. | `CollectionTree/CollectionEdit.tsx` |

### 10.2 Gestion d'une collection (`/collections/:id/manage/:tab`)

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Info | ID, propriétaire, dates, workspace (lien), chemin absolu. | `components/Dialog/Collection/InfoCollection.tsx` |
| Édition | Nom traduisible, confidentialité ; alerte si héritée du parent. | `components/Dialog/Collection/EditCollection.tsx`, `components/Form/CollectionForm.tsx` |
| Notifications | « Follow » avec 4 sujets : asset ajouté, retiré, mis à jour, nouveau commentaire. | `components/Dialog/Collection/CollectionNotifications.tsx`, `components/Ui/FollowButton.tsx` |
| Permissions | ACL avec héritage (parent récursif puis workspace). | `components/Dialog/Collection/Acl.tsx`, `CollectionAclForm.tsx`, `components/Permissions/ParentAcl.tsx` |
| Opérations | Déplacement + zone de danger (suppression / restauration). | `components/Dialog/Collection/Operations.tsx` |
| Déplacer | Arbre de destination avec branche courante désactivée ; déplacement vers la racine possible. | `components/Media/Collection/CollectionMoveSection.tsx`, `api/collection.ts` |
| Supprimer | Confirmation par saisie du nom ; mise en corbeille. | `components/Media/Collection/CollectionDeleteConfirmDialog.tsx` |
| Restaurer | Confirmation puis restauration. | `components/Media/Collection/CollectionRestoreConfirmDialog.tsx` |
| Créer | Nom + confidentialité (défaut Secret), chemin affiché en chips. | `components/Media/Collection/CreateCollection.tsx` |
| ES Document | Document Elasticsearch de la collection (rôle `tech`). | `components/Dialog/Asset/ESDocument.tsx` |

---

## 11. Paniers

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Panneau des paniers | Liste avec recherche, « Create new Basket », chargement incrémental. | `components/Basket/BasketsPanel.tsx`, `hooks/useBasketList.ts` |
| Afficher les archives | Interrupteur « Display Archive ». | `components/Basket/BasketsPanel.tsx` |
| Créer | Nom + description. | `components/Basket/CreateBasket.tsx`, `components/Form/BasketForm.tsx` |
| Panier courant | Bouton groupé : nom + badge du nombre d'assets ; clic sans sélection = ouvrir, avec sélection = ajouter ; flèche = changer de panier. | `components/Basket/BasketSwitcher.tsx`, `store/basketStore.ts` |
| Sélection du panier courant | Dialogue avec recherche ; paniers non éditables désactivés. | `components/Basket/BasketListDialog.tsx`, `BasketMenuItem.tsx` |
| Vue du panier (`/baskets/:id/view`) | Modale plein écran : paniers à gauche, grille d'assets avec numéro d'ordre, compteur, ouverture d'asset, « Remove from basket ». Bouton Edit / Info / Integrations. | `components/Basket/BasketViewDialog.tsx`, `BasketItem.tsx` |
| Menu contextuel | Éditer, Archiver / Désarchiver, Supprimer. | `components/Basket/BasketContextMenu.tsx` |
| Gestion (`/baskets/:id/manage/:tab`) | Info, Edit, Permissions, Operations (suppression avec saisie du nom), Integrations. | `components/Dialog/Basket/BasketDialog.tsx`, `InfoBasket.tsx`, `EditBasket.tsx`, `Acl.tsx`, `Operations.tsx`, `Integrations.tsx` |
| Intégrations de panier | Notamment Phrasea Expose (voir §17.5). | `components/Dialog/Basket/Integrations.tsx` |
| API | Liste (avec archivés), assets, création, édition, suppression, archivage, ajout (panier `default` si non précisé), retrait. | `api/basket.ts` |

---

## 12. Partage

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Mode simple | Interrupteur « Create a public link » + URL copiable, « Copy Link », ouverture dans un nouvel onglet. | `components/Share/ShareAssetDialog.tsx` |
| Mode avancé | Liste des liens existants + « Create new Share Link » (auto-activé si plusieurs liens ou lien nommé / daté). | `components/Share/ShareAssetDialog.tsx` |
| Création d'un lien | Nom (audience), date de début, date d'expiration. | `components/Share/CreateShareDialog.tsx`, `api/asset.ts` |
| Élément de partage | Nom, dates, URL copiable, **Revoke**. | `components/Share/ShareItem.tsx` |
| Rendition partagée | Menu « Asset » + URLs alternatives ; l'URL change selon la rendition. | `components/Share/SelectShareAlternateUrl.tsx` |
| Réseaux sociaux | Email, Facebook, X, LinkedIn, Pinterest, Telegram, WhatsApp, Tumblr, Workplace, Pocket, Instapaper. | `components/Share/ShareSocials.tsx` |
| Code d'intégration | `<img>` pour une image, sinon `<iframe>` responsive ; code éditable, Reset, copie, prévisualisation. | `components/Share/EmbedDialog.tsx` |
| Page publique | `s/:id/:token` : titre, lecteur, attributs. | `pages/SharePage.tsx`, `components/Share/AssetShare.tsx` |
| Permission | `SHARE` / `CHILD_SHARE` (« Share Assets ») dans les ACL workspace et collection. | `components/Dialog/Workspace/WorkspaceAclForm.tsx`, `components/Dialog/Collection/CollectionAclForm.tsx` |

---

## 13. Permissions et confidentialité

### 13.1 ACL

Fichiers cœur : `components/Permissions/` (`AclForm.tsx`, `PermissionList.tsx`, `PermissionTable.tsx`, `PermissionRow.tsx`, `ParentAcl.tsx`, `PermissionsHelper.tsx`, `permissionsTypes.ts`), `api/acl.ts`.

| Fonctionnalité | Description |
|---|---|
| Attribution | Par **utilisateur** ou **groupe** (deux sélecteurs), initialisée à VIEW. |
| Tableau de cases | Une colonne par permission + colonne **All** (état indéterminé), bouton Delete par ligne. |
| Lignes wildcard | « All users » / « All groups » en lecture seule et semi-transparentes. |
| Aide | Bloc « Permission levels » décrivant chaque permission. |
| Héritage | Affichage à la demande des ACL de la collection parente (récursif) puis du workspace. |
| Masque | VIEW 1, CREATE 2, EDIT 4, DELETE 8, UNDELETE 16, OPERATOR 32, MASTER 64, OWNER 128, SHARE 256, puis les CHILD_* (512 → 131072). |
| Permissions extra | `EDIT_PERMISSIONS`, `MANAGE_USERS`, `QUARANTINE`, `QUARANTINE_BY_PASS`. |
| Objets protégés | asset, attribute_policy, basket, collection, profile, rendition_policy, saved_search, workspace, integration. |

Libellés métier par objet :

- **Workspace** : Access, Create Collections, Manage, Delete, Owner, Edit Permissions, Share Assets, View Assets, Create Assets, Edit Asset Attributes, Manage Assets, Delete Assets, Owner of Assets, View Quarantined, Bypass Quarantine (`components/Dialog/Workspace/WorkspaceAclForm.tsx`).
- **Collection** : View, Create Collections, Manage Collection, Manage permissions of owned content, Delete, Owner, Share Assets, View Assets, Create Assets, Edit Asset Attributes, Manage Assets, Delete Assets, Owner of Assets, View Quarantined, Bypass Quarantine (`components/Dialog/Collection/CollectionAclForm.tsx`).
- **Asset** : View, Edit Attributes, Manage, Delete, Owner (`components/Dialog/Asset/AssetAclForm.tsx`).
- **Intégration** : View, Edit, Use, Interact.
- **Politique de rendition** : View / Edit.
- **Panier, profil, recherche sauvegardée** : table ACL générique.

### 13.2 Confidentialité (Privacy)

Six niveaux (`api/privacy.ts`, libellés dans `translations/privacyTranslations.ts`) :

| Valeur | Clé | Libellé |
|---|---|---|
| 0 | `Secret` | Secret |
| 1 | `PrivateInWorkspace` | Private in workspace |
| 2 | `PublicInWorkspace` | Public in workspace |
| 3 | `Private` | Private |
| 4 | `PublicForUsers` | Public for users |
| 5 | `Public` | Public |

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Widget de saisie | Select à 3 choix (Secret / Private / Public) + cases « Only visible to workspace » et « User must be authenticated », verrouillées selon l'héritage ; alerte « Privacy is inherited from parent » ; option « Not set ». | `components/Form/PrivacyWidget.tsx` |
| Héritage | Valeur effective = `max(héritée, valeur)` ; options plus permissives que le parent désactivées. | `components/Form/PrivacyWidget.tsx` |
| Affichage | Chip avec cadenas, icône et tooltip sur les vignettes, état « No Access ». | `components/Ui/PrivacyChip.tsx` |
| Champ contrôlé | Utilisé par les formulaires de collection et d'asset (défaut Secret). | `components/Ui/PrivacyField.tsx` |
| Info de confidentialité à l'upload | `privacy`, `computedPrivacy`, `canEditAssetPrivacy` d'une collection. | `api/collection.ts`, `components/Upload/UploadForm.tsx` |

---

## 14. Profils d'affichage

Dialogue `/profiles/:id/manage/:tab`.

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Sélecteur de profil courant | Entrée du menu paramètres affichant le profil actif ou « Default Display Profile ». | `components/Profile/DisplayProfileSwitcher.tsx` |
| Dialogue de sélection | Liste des profils + défaut, création, édition, suppression (confirmation), interrupteur **Auto-sync preferences**, bouton de synchronisation manuelle si désynchronisé. | `components/Profile/SelectDisplayProfileDialog.tsx`, `ProfileMenuItem.tsx`, `store/profileStore.ts` |
| Créer | Nom, description, Public ; initialisé avec les préférences courantes, puis ouverture de l'onglet Organize. | `components/Profile/CreateProfileDialog.tsx`, `components/Form/DisplayProfileForm.tsx` |
| Profils partagés | « Shared by {owner} » avec icône Public / Person. | `components/Profile/ProfileMenuItem.tsx` |
| Organize | Transfer list : définitions disponibles (built-in + par workspace, recherche) vs affichées, réordonnables. | `components/Dialog/Profile/OrganizeProfile.tsx`, `components/Profile/Transfer/AttributeDefinitionTransferList.tsx`, `Item.tsx`, `ItemForm.tsx` |
| Grid card | Éditeur drag & drop de la carte grille : palette d'attributs, maquette 3×3, par attribut : Display as (Rich / Chip / Text), Format, Size, couleur, Show label, Show if empty, ordre. | `components/Dialog/Profile/GridProfileEditor.tsx`, `api/profile.ts` |
| Edit | Nom, Public. | `components/Dialog/Profile/EditDisplayProfile.tsx` |
| Permissions | ACL `profile`. | `components/Dialog/Profile/Acl.tsx` |
| Info | Nom, propriétaire, visibilité, dates. | `components/Dialog/Profile/ProfileInfo.tsx` |
| Application | Le profil appliqué réinitialise et surcharge les préférences utilisateur ; auto-sync des modifications vers le profil. | `store/profileStore.ts`, `store/userPreferencesStore.ts` |

---

## 15. Règles de filtrage d'attributs

Onglet « Filter rules » du workspace (AttributeFilterRule, conditions AQL avec cibles utilisateurs / groupes).

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Liste des règles | Une carte par règle : utilisateurs (chips), groupes (chip avec icône), ou « Everyone », et condition AQL. | `components/Media/AttributeFilterRule/AttributeFilterRules.tsx`, `components/Dialog/Workspace/FilterRulesTab.tsx` |
| Création / édition | Sélection multiple d'utilisateurs et de groupes (vides = tous), condition éditée via le dialogue AQL. Les assets ne sont visibles que s'ils correspondent à la condition. Sauvegarde impossible sans condition. | `components/Media/AttributeFilterRule/FilterRuleForm.tsx`, `components/Media/Search/AQL/SearchConditionDialog.tsx` |
| Suppression | Bouton Delete avec confirmation. | `FilterRuleForm.tsx`, `api/attribute-filter-rule.ts` |

---

## 16. Pages CMS / Landing

Menu « Pages » (chip BETA) réservé au rôle `databox-admin`.

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Page d'accueil éditable | Page de slug vide ; redirige vers `/assets` si absente. | `pages/HomePage.tsx` |
| Page publique | Rendu par slug. | `pages/PagePage.tsx`, `components/Landing/PageContent.tsx`, `PageWrapper.tsx` |
| Index des pages | Liste, édition, suppression (saisie du titre), « Create Page », chargement incrémental. | `pages/PageIndexPage.tsx` |
| Métadonnées | Titre, Slug (unique), Enabled, Public. | `components/Landing/Editor/form/PageCreateDialog.tsx`, `PageEditDialog.tsx`, `PageEditFields.tsx` |
| Éditeur TipTap | Save, View, Undo, Redo, police, gras, italique, souligné, barré, listes, citation, bloc de code, code, clear formatting, lien, alignements, couleurs / surligneur. | `components/Landing/Editor/PageEditor.tsx`, `MenuBar.tsx`, `menu/ColorPalette.tsx`, `extensions/highlighter/`, `extensions/link/LinkDialog.tsx` |
| Widgets | Bouton « + » flottant → **Asset**, **Carousel**, **Grid**, **Search Grid**, **Spacer**, **Header Bar**, **Footer** ; dialogue d'options et « Remove widget ». | `components/Landing/Editor/menu/AddMenu.tsx`, `InsertMenu.tsx`, `components/Landing/widgets/index.ts`, `widgets/components/WidgetOptionsDialogWrapper.tsx` |
| Widget Search Grid | Basé sur une recherche sauvegardée : max d'éléments, taille des vignettes, hauteur, ouverture d'asset, facettes, activation fine des actions. | `components/Landing/widgets/search-grid/SearchGridWidget.tsx`, `SearchGrid.tsx` |
| Aperçu | « View » ouvre l'URL publique dans un nouvel onglet. | `pages/PageEditPage.tsx` |

---

## 17. Intégrations

### 17.1 Socle commun

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Panneau d'intégrations d'un fichier | Accordéons des intégrations du workspace pour le contexte `asset-view`, filtrées par type de fichier supporté. | `components/Media/Asset/FileIntegrations.tsx` |
| Panneau d'intégrations d'un panier | Onglet « Integrations » du panier (contexte `basket`). | `components/Dialog/Basket/Integrations.tsx` |
| Overlay sur le média | Une intégration peut remplacer ou superposer l'aperçu. | `components/Media/Asset/View/AssetView.tsx` |
| Permissions | `use` (voir), `interact` (déclencher), `edit`. | `FileIntegrations.tsx` |
| Données d'intégration | Chargement paginé, ajout / suppression optimiste. | `components/Integration/useIntegrationData.ts`, `api/integrations.ts` |
| Actions | `POST /integrations/{id}/actions/{action}` (analyze, process, save, delete, sync, force-sync, stop). | `api/integrations.ts` |
| Temps réel | Canal Pusher `file-{id}` / `basket-{id}`, événement `integration:{type}`. | `lib/pusher.ts` |
| Autorisation OAuth | Popup d'auth, surveillance de fermeture, rechargement des jetons, détection d'expiration. | `components/Integration/useIntegrationAuth.ts` |

### 17.2 AWS Rekognition

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Détection labels / textes / visages | Trois analyses indépendantes selon la configuration. | `components/Integration/AwsRekognition/AwsRekognitionAssetEditorActions.tsx` |
| Résultats avec confiance | Pourcentage de confiance par résultat. | `AwsRekognition/ValueConfidence.tsx` |
| Annotations visuelles | Rectangles : labels bleus, textes verts, visages rouges ; clic = sélection. | `AwsRekognitionAssetEditorActions.tsx` |
| Détail d'un visage | Tranche d'âge, genre, barbe, sourire, yeux ouverts, lunettes, bouche ouverte, moustache. | `AwsRekognition/FaceDetailTooltip.tsx` |

### 17.3 Remove.bg

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Suppression du fond | Bouton « Remove BG ». | `components/Integration/RemoveBG/RemoveBGAssetEditorActions.tsx` |
| Comparateur avant / après | Slider de comparaison sur fond damier. | idem |
| Enregistrer sous | Nouvel asset « {nom} - BG removed ». | idem |

### 17.4 TUI Photo Editor

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Édition d'image | Éditeur Toast UI complet (filtres, recadrage, dessin…) en overlay. | `components/Integration/TuiPhotoEditor/TUIPhotoEditor.tsx` |
| Enregistrer une version | Nom de fichier + Save (upload multipart puis action `save`). | idem |
| Historique « Open recent » | Versions éditées rechargeables, entrée « Original ». | `TuiPhotoEditor/FileItem.tsx` |
| Menu par version | Supprimer, « Save as » nouvel asset. | `TuiPhotoEditor/FileItem.tsx` |

### 17.5 Phrasea Expose (panier)

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Autorisation | Bouton « Authorize » (OAuth). | `components/Integration/Phrasea/Expose/ExposeBasketIntegration.tsx` |
| Créer et synchroniser une publication | Publication parente, titre, slug, description, activé, profil de publication ; « Create & Sync ». | `Phrasea/Expose/CreatePublicationDialog.tsx`, `ExposeProfileSelect.tsx`, `ExposePublicationSelect.tsx`, `exposeApi.ts` |
| Liste des synchronisations | Carte par publication avec lien public. | `ExposeBasketIntegration.tsx` |
| Force Sync / Éditer / Arrêter | Forcer la synchro, ouvrir Expose, arrêter (option « Also delete the Publication »). | idem |
| Progression temps réel | « Sync in progress… / Cleaning assets… / Sync Complete! » avec compteur. | idem |

### 17.6 Administration des intégrations

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Gestionnaire | Liste filtrable, création, édition, suppression ; désactivées en rouge. | `components/Dialog/Workspace/IntegrationManager.tsx` |
| Configuration | Type, nom, activation, condition `IF`, dépendances « Needs », YAML dans un éditeur de code, Public, ACL. | idem, `components/Form/IntegrationTypeSelect.tsx`, `WorkspaceIntegrationSelect.tsx`, `components/Form/CodeEditor/*` |
| Référence et clés | Référence YAML copiable, « Integration Keys » (ex. URL de webhook). | `components/Dialog/Workspace/ReferenceSections.tsx` |
| Dernières erreurs | Liste des dernières erreurs. | `components/Dialog/Workspace/LastErrorsList.tsx` |

---

## 18. Workflows

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Vue workflow (`/workflows/:id`) | Graphe visuel des jobs (React Flow via `@alchemy/visual-workflow`), thématisé. | `components/Workflow/WorkflowView.tsx` |
| Temps réel | Canal `workflow-{id}`, événement `job_update`. | idem, `lib/pusher.ts` |
| Relancer un job | Action « rerun » sur un job. | `api/workflow.ts` |
| Annuler le workflow | Bouton dans l'en-tête. | idem |
| Rafraîchir | Bouton de rechargement. | idem |
| Retour contextuel | Fermeture vers l'onglet Workflow de l'asset d'origine. | idem |
| Onglet Workflow d'un asset | Liste des derniers workflows avec statut coloré (Started / Success / Failure / Cancelled) et « View ». | `components/Dialog/Asset/AssetWorkflow.tsx` |
| Relancer l'ingestion | « Trigger workflow again ». | `api/asset.ts` |

---

## 19. Tâches d'opération

Route `/admin/tasks`, réservée au rôle `databox-admin`.

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Historique | Liste des exécutions, « Refresh », « New Task ». | `components/OperationTasks/TasksListDialog.tsx`, `api/operationTask.ts` |
| Carte de tâche | Statut (jauge circulaire, erreur, succès, en attente, annulée), libellé, auteur, date, durée, temps restant estimé. | `components/OperationTasks/TaskCard.tsx`, `lib/duration.ts` |
| Catalogue (`/admin/tasks/new`) | Types de tâches avec description. | `components/OperationTasks/OperationTasksDialog.tsx`, `tasks/useTasks.ts` |
| Lancement (`/admin/tasks/:task/run`) | Formulaire dynamique, « Run », toast, redirection. | `components/OperationTasks/RunTaskDialog.tsx` |
| Détails (`/admin/tasks/:id/details`) | Payload JSON copiable, dates, sortie, items traités / total, durée, propriétaire. | `components/OperationTasks/TaskDetailsDialog.tsx` |

Types de tâches disponibles :

| Tâche | Description | Champs | Fichier |
|---|---|---|---|
| Switch attribute locales | Change la locale d'attributs existants. | Workspace, Attribut, From Locale, To Locale | `tasks/SwitchAttributeLocaleTask.tsx` |
| Index assets | Réindexe les assets. | Workspace (optionnel) | `tasks/IndexAssetsTask.tsx` |
| Ingest workspace assets | Rejoue l'ingestion sur tous les assets d'un workspace. | Workspace | `tasks/IngestWorkspaceAssetsTask.tsx` |
| Store fallback as attribute | Persiste la valeur de fallback comme valeur réelle. | Workspace, Attribut | `tasks/AttributeDefinitionTask.tsx` |
| Recompute initial values | Recalcule la valeur initiale d'un attribut. | Workspace, Attribut | `tasks/AttributeDefinitionTask.tsx` |

Statuts : Pending, InProgress, Completed, Failed, Cancelled (`api/types.ts`).

---

## 20. Discussion et pièces jointes

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Fil de discussion | Messages attachés à une clé de thread (asset), squelettes de chargement, formulaire en bas. | `components/Discussion/Thread.tsx`, `components/Media/Asset/AssetDiscussion.tsx` |
| Temps réel | Canal `thread-{key}` : `message` (avec déduplication) et `message-delete`. | `Thread.tsx`, `lib/pusher.ts` |
| Deep link vers un message | Hash `#discussion-{id}` : sélection, surlignage vert, scroll. | `Thread.tsx`, `hooks/useNotificationUriHandler.ts` |
| Écriture | Zone multiligne, bouton « Send ». `Ctrl+Entrée` envoie, `Échap` quitte le champ. | `components/Discussion/MessageForm.tsx`, `MessageField.tsx` |
| Mentions @utilisateur | Autocomplétion serveur déclenchée par `@` ; désactivée silencieusement si l'API users est interdite. | `components/Discussion/MentionTextarea.tsx`, `api/user.ts` |
| Rendu enrichi | Mentions en pastille, URLs en liens (tronquées au-delà de 50 caractères), sauts de ligne préservés. | `components/Discussion/formatMessage.tsx`, `lib/reactText.tsx` |
| Émojis | Picker emoji-mart au curseur. | `components/Discussion/EmojiPicker.tsx` |
| Pièces jointes de message | Chips (annotations ou fichiers), cliquables, supprimables avant envoi. | `components/Discussion/Attachments.tsx` |
| Annotations en pièces jointes | Dessiner une annotation l'attache au message ; cliquer une pièce jointe réaffiche l'annotation. | `components/Media/Asset/View/useBindAnnotationMessage.ts` |
| Éditer / supprimer un message | Menu « … » selon capabilities, édition en place, confirmation de suppression. | `components/Discussion/DiscussionMessage.tsx`, `EditMessage.tsx` |
| Métadonnées | Avatar, nom, date calendaire avec date complète en infobulle. | `DiscussionMessage.tsx` |
| Garde-fou | Prompt si un message est en cours de rédaction. | `MessageForm.tsx` |
| Pièces jointes d'asset | Accordéon « Attachments » : vignette, téléchargement, renommage, suppression, détachement, ajout. | `components/Media/Asset/AssetAttachments.tsx`, `api/attachment.ts` |

---

## 21. Carte et géolocalisation

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Carte OpenStreetMap | Composant Leaflet avec tuiles OSM, dimensions paramétrables. | `components/Map/OpenStreetMap.tsx`, `lib/leaflet.ts` |
| Attribut geo_point sur carte | Format Map (zoom 13, marqueur + popup), Coords ou JSON. | `components/Media/Asset/Attribute/types/GeoPointType.tsx` |
| Facette geo-distance | Cercles concentriques par tranche de distance, popups de comptage. | `components/Media/Asset/Facets/types/GeoDistanceFacet.tsx` |
| Filtre « autour de moi » | Voir §2.8. | `components/Media/Search/GeoPointFilter.tsx` |
| Opérateurs géo AQL | `WITHIN CIRCLE`, `WITHIN RECTANGLE`. | `components/Media/Search/AQL/grammar.ne` |

Pas de clustering de marqueurs ni de vue « tous les assets sur une carte ».

---

## 22. Utilisateur : préférences, thèmes, langues

| Fonctionnalité | Description | Fichiers |
|---|---|---|
| Préférences persistées | Stockées côté serveur (`GET/PUT /preferences`) et en `sessionStorage` ; écriture dédupliquée ; mode local si non authentifié. | `store/userPreferencesStore.ts`, `api/user.ts` |
| Écran de chargement | « Loading user preferences… » avant l'affichage. | `components/User/Preferences/UserPreferencesProvider.tsx` |
| Choix du thème | Dialogue « Choose a theme » : default, one, two, three, four ; appliqué et persisté. | `components/Layout/ChangeThemeDialog.tsx` |
| Thème injecté par la stack | Thème MUI et CSS global surchargés au déploiement (`stack-config.json`). | `config-compiler.js`, `components/Root.tsx` |
| Langue de l'interface | en, fr, de, es (avec drapeau). `it.json` présent mais non exporté. | `components/Locale/LocaleDialog.tsx`, `translations/index.ts` |
| Langue des données | Locales du serveur avec recherche, option « Default — Use UI language » ; envoyée via l'en-tête `X-Data-Locale`. Changement avec confirmation de rechargement. | `components/Locale/LocaleDialog.tsx`, `api/locale.ts`, `store/useDataLocaleStore.ts` |
| Synchronisation des locales | Moment.js, client API et framework mis à jour à chaque changement. | `i18n.ts` |
| Champs traduisibles | Onglets par locale pour les noms de workspace, collection, tag ; sauvegarde immédiate. | `components/Form/ObjectTranslationField.tsx`, `hooks/useCreateSaveTranslations.ts`, `components/Ui/Flag.tsx` |
| Recherche d'utilisateurs et groupes | Autocomplétion serveur (mentions, permissions, règles de filtrage). | `api/user.ts`, `components/Form/UserSelect.tsx`, `GroupSelect.tsx` |
| Bouton Follow / Unfollow | Bouton simple ou scindé multi-sujets. | `components/Ui/FollowButton.tsx` |

---

## 23. Récapitulatif des raccourcis clavier

| Raccourci | Effet | Fichier |
|---|---|---|
| `Ctrl/Cmd + A` | Sélectionner tous les assets chargés (liste, éditeur en masse). | `hooks/useSelectAllKey.ts` |
| `Ctrl/Cmd + clic` | Ajouter / retirer un asset de la sélection. | `components/AssetList/selection.ts` |
| `Shift + clic` | Sélection de plage. | `components/AssetList/selection.ts` |
| `Double-clic` | Ouvrir l'asset. | `components/AssetList/Layouts/Grid/GridPage.tsx` |
| `Clic droit` | Menu contextuel (asset, chip de condition, panier). | `components/AssetList/AssetContextMenu.tsx` |
| `Ctrl/Cmd + V` | Upload d'une image du presse-papiers. | `components/Media/Asset/AssetDropzone.tsx` |
| `←` / `→` | Asset précédent / suivant dans la visionneuse. | `components/Media/Asset/AssetViewNavigation.tsx` |
| `Ctrl + Entrée` | Envoyer un message de discussion. | `components/Discussion/MessageField.tsx` |
| `Échap` (message) | Quitter le champ de message. | `components/Discussion/MessageField.tsx` |
| `Échap` (annotation) | Annuler le dessin / désélectionner. | `components/Media/Asset/Annotations/AnnotateWrapper.tsx` |
| `Suppr` (annotation) | Supprimer l'annotation sélectionnée. | `AnnotateWrapper.tsx` |
| `Espace` maintenu | Pan sur le média annoté. | `AnnotateWrapper.tsx` |
| `Tab` / `Shift+Tab` | Définition suivante / précédente dans l'éditeur en masse. | `components/AttributeEditor/shortcuts.ts` |
| `Entrée` | Valider une valeur multi (éditeur en masse) ; valider un renommage de collection. | `components/AttributeEditor/MultiAttributeRow.tsx`, `components/Media/Collection/CollectionTree/CollectionEdit.tsx` |
| `Échap` (renommage) | Annuler le renommage d'une collection. | `CollectionTree/CollectionEdit.tsx` |
| `Entrée` (recherche) | Soumettre la recherche. | `components/Media/Search/SearchBar.tsx` |

---

## 24. Récapitulatif des préférences persistées

Store : `store/userPreferencesStore.ts` (serveur + `sessionStorage`).

| Clé | Contenu |
|---|---|
| `theme` | Thème choisi. |
| `layout` | Layout de la liste d'assets. |
| `dataLocale` | Langue des données. |
| `profile` | Profil d'affichage courant. |
| `autoSync` | Synchronisation automatique des préférences vers le profil. |
| `display` | Layout, taille de vignette, preview au survol, autoplay vidéo, options de preview (ratios, fichier, attributs), verrouillage. |
| `displayBatchEdit` | Même jeu, dédié à l'éditeur d'attributs en masse. |
| `facets` | Facettes masquées / épinglées / ordre. |

État porté par l'URL (non persisté) : requête, conditions, tri, géolocalisation, recherche sauvegardée chargée, modale ouverte et onglet actif.

État de session en mémoire : formats d'affichage des attributs par type / définition, dernières options d'annotation par forme.

---

## 25. Configuration runtime

Exposée via `config-compiler.js` → `window.config` :

- `baseUrl` (API Databox), Keycloak (`keycloakUrl`, `realmName`, `clientId`, `autoConnectIdP`).
- `devMode`, `displayServicesMenu`, `dashboardBaseUrl`.
- `logo`, thème MUI et `globalCSS` issus de `stack-config.json`.
- Matomo, Sentry, Pusher/Soketi (`pusherHost`, `pusherKey`), `notifications`.
- `requestSignatureTtl`.
- Upload : `minChunkSize`, `maxChunkSize`, `maxPartNumber`, `maxFileSize`, `allowedTypes` (MIME → extensions).

Un `phrasea-manifest.json` est généré avec les URLs d'auth, token, userinfo, logout, OIDC et l'URL de l'API.

Build : Vite (port 3000), SVGR, vérification TypeScript continue, tests Vitest (grammaire AQL notamment).
