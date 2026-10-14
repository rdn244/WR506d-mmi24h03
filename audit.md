# Audit qualité : Dressing

Séance 15 : linter, PHPStan, qualité de code.
Audit réalisé le 14/10/2026 sur la branche `develop`. Elle contient toute l'application :
- comptes, logements et droits ;
- Instagram, looks ;
- scan IA, inventaire et catégories.

| | |
|---|---|
| Stack | Symfony 7.4, PHP 8.5 (Docker), MariaDB 12.3, Doctrine ORM 3, AssetMapper + Stimulus + Turbo |
| Volume | 77 fichiers PHP dans `src/` (5 790 lignes), 43 templates Twig, 1 feuille de style (3 334 lignes), 9 fichiers de tests |
| Tests | 144 tests PHPUnit (unitaires et fonctionnels) |

## Synthèse

| Axe | État | En bref |
|---|---|---|
| Qualité du code PHP | Bon | PHPStan niveau 6 : 42 erreurs corrigées, **0 restante**. Il reste un peu de dette : contrôleurs longs, filtrage en PHP. |
| Qualité technique Symfony | Bon | Twig, YAML, conteneur et `composer.json` valides. **Aucune vulnérabilité connue** (Composer et importmap). Pas de limite de tentatives de connexion. |
| Accessibilité | À améliorer | Bonne base (labels, `alt`, focus visible, `lang`, mouvements réduits). En revanche, **3 couleurs sous le seuil AA de contraste**, et les erreurs de formulaire ne sont pas reliées aux champs. |
| Écoconception | À améliorer | Pas de bundler, icônes en SVG inline, lazy loading. Mais **les photos de looks ne sont pas redimensionnées** (jusqu'à 8 Mo) et la police est chargée depuis Google Fonts. |
| Expérience utilisateur | Bon | Mobile first, retours systématiques, états vides et de chargement, confirmations. Mais plusieurs entrées « Bientôt disponible » restent visibles. |
| Testabilité | Bon | API externes simulées, tests des droits cellule par cellule. Manquent la mesure de couverture, les tests JavaScript (Stimulus) et l'intégration continue. |

## Outils mis en place

Toutes les commandes passent par le `Makefile`. Elles tournent dans le conteneur `php`, ou directement sur la machine avec `PHP=` (par exemple `make qualite PHP=`) si Docker est arrêté.

| Commande | Outil | Ce qu'elle vérifie |
|---|---|---|
| `make phpstan` | PHPStan 2.3, niveau 6, avec les extensions Symfony, Doctrine et PHPUnit | Types, appels invalides, génériques, incohérences entre les entités et le mapping Doctrine |
| `make lint` | `lint:twig`, `lint:yaml`, `lint:container`, `composer validate` | Syntaxe des templates et de la configuration, types injectés dans les services, validité de `composer.json` |
| `make securite` | `composer audit`, `importmap:audit` | Vulnérabilités connues des dépendances PHP et JavaScript |
| `make qualite` | Les trois commandes précédentes | Le contrôle complet, avant chaque fusion dans `develop` |
| `make test` | PHPUnit 13 | Base `penderie_test` migrée, puis la suite complète |

Fichiers ajoutés :
- `phpstan.dist.neon` : configuration ;
- `tests/object-manager.php` : chargeur Doctrine pour PHPStan ;
- dépendances de développement `phpstan/*`.

---

## 1. Qualité du code PHP

### PHPStan niveau 6

Premier passage : **42 erreurs**. Toutes sont corrigées à la source, sans `@phpstan-ignore` ni baseline.

| Erreurs | Type | Correction |
|---|---|---|
| 19 | `doctrine.columnType` / `doctrine.associationType` : propriété `?string` sur une colonne obligatoire | Choix assumé : les formulaires Symfony hydratent des entités neuves avant validation. Les champs restent donc nullables en PHP, et `NotBlank` garantit une valeur avant l'enregistrement. Ce choix est déclaré par l'option officielle `allowNullablePropertyForRequiredField` de l'extension Doctrine. |
| 13 | `missingType.generics` : formulaires sans type de données | `@extends AbstractType<Logement>` (ou `<User>`, `<Objet>`, `<array<string, mixed>>`…) sur chaque formulaire |
| 3 | `missingType.generics` : `FormInterface` et `InputBag` | `FormInterface<DemandePublication>`, `InputBag<string>` |
| 1 | `match.unhandled` dans `MembreController::action()` | Ajout d'une branche `default` qui renvoie une 404. Jusque-là, seule la contrainte de la route empêchait une action inconnue. |
| 1 | `arrayValues.list` dans `TypePiece::predefinies()` | La liste est construite explicitement, ce qui reste juste même si l'ordre des cas change |
| 4 | Tests : champ `DomCrawler` mal typé, `assertCount` | Assertions de type (`assertInstanceOf(FileFormField::class, …)`) et `assertCount` |
| 1 | Test de matrice toujours vrai (`staticMethod.alreadyNarrowedType`) | Le test compare désormais les ressources de l'application aux cellules **réellement testées**, et non plus à une constante recopiée |

Résultat : `[OK] No errors`.

### Inspection manuelle

**Points forts**
- **Une responsabilité par classe** : les droits (`MatriceDroits`, `ControleAcces`, `ResolveurRelation`), les clients d'API (`ClientZernio`, `AnalyseurObjet`), le stockage (`StockagePhotos`) et la validation d'entrée (`PhotoBase64`) sont séparés.
- **Contrôleurs** : ils restent minces sur les accès, grâce au voter `DroitsVoter` et à `#[IsGranted]`.
- **Données externes jamais crues sur parole** : photo en base64 vérifiée (type, poids, dimensions), réponse de l'IA nettoyée et tronquée, identifiant Instagram revérifié auprès de Zernio.
- **Typage strict partout** : propriétés `readonly`, enums PHP pour les types métiers, constantes nommées plutôt que nombres magiques.

**Dette technique repérée**

| Priorité | Constat | Piste |
|---|---|---|
| Moyenne | `ObjetController` (269 lignes) porte l'inventaire, le scan, l'analyse IA, la fiche et la modification | Extraire l'analyse dans un contrôleur `ScanController`, et le filtrage dans un service `FiltreInventaire` |
| Moyenne | L'inventaire et l'accueil chargent **tous** les objets, puis filtrent en PHP (droits, catégorie, recherche) | Filtrer catégorie et recherche en requête SQL, et paginer au-delà d'environ 100 objets |
| Basse | `ProfilExtension` mélange profil actif, Instagram et droits de création | La découper en `DroitsExtension` et `InstagramExtension` |
| Basse | `assets/styles/app.css` fait 3 334 lignes (65 Ko) en un seul fichier | Un fichier par domaine (`formulaires.css`, `inventaire.css`…), importés par AssetMapper |

---

## 2. Qualité technique Symfony

| Contrôle | Résultat |
|---|---|
| `lint:twig templates` | 43 fichiers valides |
| `lint:yaml config --parse-tags` | 28 fichiers valides |
| `lint:container` | Tous les services reçoivent des arguments du bon type |
| `composer validate` | `composer.json` valide |
| `composer audit` | Aucune vulnérabilité connue |
| `importmap:audit` | Aucune vulnérabilité connue (Turbo, Stimulus) |
| Snyk.io | Non exécuté : il faut relier le dépôt GitHub à un compte Snyk. `composer audit` et `importmap:audit` couvrent la même base de vulnérabilités connues pour les dépendances. |

**Sécurité, points vérifiés**
- CSRF : sur tous les formulaires, et `#[IsCsrfTokenValid]` sur les actions POST isolées.
- Secrets : `.env` n'est pas versionné ; les clés d'API vivent dans les variables d'environnement.
- Photos : rangées hors de `public/`, servies uniquement après le contrôle `VOIR`.
- Analyse IA : limitée à 10 par minute et par compte (`symfony/rate-limiter`).

**À corriger**

| Priorité | Constat | Correction |
|---|---|---|
| Haute | Pas de limite de tentatives sur `/connexion` : un mot de passe peut être deviné par force brute | `login_throttling: { max_attempts: 5 }` dans `security.yaml` |
| Moyenne | En mode dev, la barre Symfony (`/_profiler`) affiche les variables d'environnement, donc les clés d'API, à quiconque est sur le même réseau | Pour un test sur téléphone : `APP_ENV=prod`, ou un tunnel HTTPS avec le profiler désactivé |

---

## 3. Accessibilité numérique (A11Y)

### Ce qui est conforme
- **Langue** : `<html lang="fr">`, et le zoom n'est pas bloqué (pas de `user-scalable=no`).
- **Images** : toutes ont un attribut `alt`, vide quand elles sont décoratives.
- **Formulaires** : chaque champ a un `<label>`. Les recherches ont un label caché visuellement (`.visuellement-cache`).
- **Navigation au clavier** : focus visible partout (`:focus-visible`, contour bordeaux). La feuille Instagram est un `<dialog>` natif, avec focus piégé et fermeture par Échap.
- **Repères pour les aides techniques** : boutons-icônes nommés (`aria-label`), filtre actif signalé par `aria-current`, groupes de choix en `role="radiogroup"`.
- **Messages** : notifications annoncées (`role="status"`, `role="alert"`), statut du scan en `aria-live="polite"`.
- **Titres** : un `h1` par page, des `h2` pour les sections.
- **Animations** : toutes respectent `prefers-reduced-motion`.

### Contrastes (WCAG 2.2, seuil AA de 4,5:1 pour le texte)

| Couleur, fond | Ratio | Verdict | Où |
|---|---|---|---|
| `gray-400` #9c9c9c, blanc | 2,75:1 | **Échec** | Titres de section, textes vides, mentions, placeholders |
| `gray-400`, `cream-100` | 2,55:1 | **Échec** | Textes secondaires sur fond crème |
| `gold-500` #c9a15a, blanc | 2,41:1 | **Échec** pour un texte (acceptable pour une icône décorative) | Étincelle « détecté par l'IA » |
| blanc, `bordeaux-300` | 4,39:1 | Grand texte seulement | Avatars |
| `gray-700`, blanc | 8,45:1 | Conforme | Sous-titres |
| `bordeaux-900`, blanc | 15,29:1 | Conforme | Texte principal |
| blanc, `bordeaux-500` | 10,13:1 | Conforme | Boutons |
| `error` #c1382b, blanc | 5,43:1 | Conforme | Erreurs |

**Correction proposée** : remplacer `gray-400` par un gris d'au moins #767676 (4,54:1) pour tout texte, et garder `gray-400` pour les bordures et les icônes.

### À corriger
1. **Erreurs non reliées aux champs** : les erreurs de formulaire ne portent ni `aria-invalid` ni `aria-describedby`. Un lecteur d'écran n'associe donc pas le message au champ. Il faut l'ajouter dans `templates/form/theme.html.twig`.
2. **Entrées inactives sans explication** : les entrées « Bientôt disponible » (Recherche, Styliste, Notifications…) sont des `span aria-disabled` dont la seule explication est un `title`, invisible au toucher.
3. **Petites cibles tactiles** : les compteurs des puces et l'étincelle sont petits. Ils sont décoratifs, mais la cible minimale de 24 × 24 px (WCAG 2.5.8) est à vérifier sur les boutons « Voir ».

---

## 4. Écoconception

| Ressource | Poids (non compressé) | Remarque |
|---|---|---|
| CSS (`app.css`) | 65 Ko | Un seul fichier, mis en cache par AssetMapper (nom versionné) |
| JS Turbo + Stimulus | 94 Ko + 46 Ko | Pas de bundler ni de framework lourd ; contrôleurs Stimulus chargés à la demande |
| Contrôleurs Stimulus | 36 Ko au total | |
| Icônes | 0 requête | SVG inline via `ux_icon`, seules les icônes utilisées sont importées |
| Police Inter | 4 graisses, depuis Google Fonts | Requête vers un tiers et chargement bloquant |
| Photos d'objets | ~100 à 200 Ko | Réduites à 1 024 px dans le navigateur avant l'envoi |
| Photos de looks | **jusqu'à 8 Mo** | Envoyées et servies telles quelles, y compris dans les vignettes de la galerie |

**Bonnes pratiques déjà en place**
- Images en `loading="lazy"`.
- Cache privé d'une heure sur les photos.
- Navigation Turbo : pas de rechargement complet entre les pages.
- Aucune vidéo, aucun traceur ni script d'analyse.
- Photo du scan envoyée en base64 à l'IA, sans hébergement intermédiaire.

**Pistes, par impact**
1. **Redimensionner les photos de looks**, comme pour le scan (1 080 px, JPEG ou WebP), et produire des vignettes de 400 px pour les galeries. C'est le gain principal : jusqu'à 8 Mo par vignette aujourd'hui.
2. **Héberger la police** sur le serveur, en `woff2` avec 2 graisses, ou utiliser la police système : une requête externe en moins.
3. **Paginer l'inventaire** et filtrer en SQL : moins de données chargées et de HTML généré.
4. **Compresser en production** : activer gzip/brotli dans nginx, puis lancer `asset-map:compile`.

Une mesure Lighthouse ou EcoIndex sur le site en marche reste à faire. Docker était indisponible pendant l'audit, faute d'espace disque (2,3 Go libres) ; la commande à lancer figure dans le plan d'actions.

---

## 5. Expérience utilisateur (UX)

**Points forts**
- **Mobile first** avec trois mises en page (mobile, tablette, desktop) : barre du bas sur mobile, barre latérale sur desktop.
- **Retour à chaque action** : notifications, état « Analyse en cours… » du scan avec balayage animé, bouton « Publication… » pendant l'envoi Instagram, fond animé pendant le chargement des photos.
- **Prévention des erreurs** :
  - confirmation avant toute suppression ;
  - formulaire renvoyé avec ses valeurs et la feuille rouverte en cas d'erreur ;
  - choix manuels jamais écrasés par l'IA ;
  - photo trop étirée pour le feed signalée avant l'envoi.
- **États vides guidés** : « Scanne ton premier objet », « Aucun objet ne correspond » avec un lien « Tout afficher ».
- **Repères visuels** : l'étincelle signale les champs remplis par l'IA, l'icône de catégorie apparaît sur les vignettes, la catégorie et le rangement sur la fiche.

**À améliorer**

| Priorité | Constat | Piste |
|---|---|---|
| Moyenne | Le bouton « Recherche » de la barre du bas est inactif alors que la recherche d'inventaire existe | Le faire pointer vers `/objets` avec le champ de recherche actif |
| Moyenne | « Scanner » et « Ajouter un objet » mènent à la même page | Fusionner, ou faire de « Ajouter un objet » une saisie sans photo |
| Moyenne | « Mot de passe oublié ? » est un texte inactif | Mettre en place la réinitialisation par e-mail (`symfony/reset-password-bundle`) |
| Basse | Sur mobile, les 4 statistiques de l'accueil prennent beaucoup de hauteur | Une rangée compacte qui défile, comme les catégories |
| Basse | Envoyer un look en 4G peut être long (photo non réduite) | Réduction dans le navigateur, comme pour le scan |

---

## 6. Testabilité et fiabilité

**Existant**
- **144 tests** :
  - matrice des droits testée cellule par cellule ;
  - parcours fonctionnels avec `WebTestCase` : comptes, logements, droits, Instagram, looks, scan, catégories, inventaire.
- **API externes simulées** (`mock_response_factory`, `tests/Http/ReponsesHttp.php`) : Zernio et OpenRouter ne sont jamais appelés en test. Les pannes se simulent modèle par modèle : quota, erreur dans une réponse 200, JSON entouré de texte, clé refusée.
- **Données de test** :
  - chaque test repart d'une base vide (`DELETE FROM utilisateur` en cascade) ;
  - les catégories prédéfinies viennent des migrations ;
  - les photos de test sont dans `tests/fixtures/`.
- **Aucune dépréciation tolérée** : `failOnDeprecation`, `failOnNotice` et `failOnWarning` sont activés.

**Fonctionnalités critiques à couvrir en priorité**

| Fonctionnalité | Couverture | Manque |
|---|---|---|
| Droits d'accès (matrice, sous-comptes, liens) | Élevée | |
| Scan IA et chaîne de secours | Élevée côté serveur | Le contrôleur Stimulus (réduction de l'image, remplissage des champs) n'est pas testé |
| Publication Instagram | Élevée (Zernio simulé) | Aucun test contre l'API réelle (bac à sable) |
| Connexion et inscription | Moyenne | Limite de tentatives, à ajouter |

**Scénarios à automatiser (prochaines séances)**
1. **Tests de bout en bout** dans un vrai navigateur (Panther ou Playwright) : scan complet, puis validation et fiche ; ouverture et fermeture de la feuille Instagram.
2. **Couverture** : installer `pcov` dans l'image Docker et ajouter `make couverture` (objectif : 80 %).
3. **Intégration continue** : un workflow GitHub Actions qui lance `make qualite` et `make test` sur chaque pull request vers `develop`.

**Limite de cet audit** : les tests unitaires (75) passent après les corrections PHPStan. La suite fonctionnelle complète (144 tests) passait juste avant ces corrections, mais doit être relancée (`make test`) quand Docker redémarre.

---

## Plan d'actions

| # | Action | Axe | Priorité | Effort |
|---|---|---|---|---|
| 1 | Limiter les tentatives de connexion (`login_throttling`) | Sécurité | Haute | 10 min |
| 2 | Remplacer `gray-400` par une couleur conforme AA pour le texte | A11Y | Haute | 15 min |
| 3 | Relier les erreurs aux champs (`aria-invalid`, `aria-describedby`) dans le thème | A11Y | Haute | 30 min |
| 4 | Réduire les photos de looks et générer des vignettes | Éco, UX | Haute | 2 h |
| 5 | Relancer `make test`, puis mesurer avec Lighthouse : `npx lighthouse http://localhost:8080/accueil --form-factor=mobile` | Fiabilité | Haute | 30 min |
| 6 | Workflow GitHub Actions : `make qualite` et `make test` | Testabilité | Moyenne | 1 h |
| 7 | Brancher le bouton « Recherche » sur l'inventaire | UX | Moyenne | 15 min |
| 8 | Filtrage SQL et pagination de l'inventaire | Code, éco | Moyenne | 2 h |
| 9 | Découper `ObjetController`, `ProfilExtension` et `app.css` | Code | Basse | 2 h |
| 10 | Héberger la police (`woff2`, 2 graisses) | Éco | Basse | 30 min |
