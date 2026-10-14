# Synthèse de la campagne de tests : Dressing

Branche `feature/test` (non fusionnée dans `develop`). Exécution du 14/10/2026, en local, sous Docker (PHP 8.5, MariaDB 12.3).

| Contrôle | Commande | Résultat |
|---|---|---|
| Tests unitaires | `make test-unitaires` | **131 tests, 214 assertions : OK** |
| Tests d'intégration | `make test-integration` | **15 tests, 67 assertions : OK** |
| Tests fonctionnels | `make test-fonctionnels` | **69 tests, 445 assertions : OK** |
| Couverture du code métier | `make couverture` | **100 % des lignes (776/776), méthodes (265/265) et classes (44/44)** |
| Analyse statique | `make phpstan` | **PHPStan niveau 6 : aucune erreur** |
| Linters Symfony | `make lint` | **Twig, YAML, conteneur et `composer.json` valides** |
| Sécurité des dépendances | `make securite` | **Aucune vulnérabilité connue (Composer et importmap)** |
| Bout en bout | `make e2e` | **14 scénarios Cypress, 5 fichiers : OK** |

Le choix des tests est justifié dans [coverage.md](coverage.md).

## Commandes (Makefile)

```bash
make test               # les 3 suites PHPUnit
make test-unitaires     # sans noyau ni base
make test-integration   # vrais services, base penderie_test
make test-fonctionnels  # parcours HTTP complets
make couverture         # couverture texte + HTML (var/couverture/html)
make qualite            # lint + phpstan + securite
make fixtures           # charge les données de test dans la base de dev
make sauvegarde         # copie la base de dev dans var/sauvegardes
make restauration       # remet la dernière sauvegarde (ou FICHIER=…)
make e2e                # Cypress sans fenêtre (base sauvegardée puis restaurée)
make e2e-ouvrir         # Cypress en mode interactif, pour la démonstration
make tests              # toute la campagne : qualite + test + e2e
```

---

## 1. Tests PHPUnit (3 suites)

![Les trois suites PHPUnit](docs/captures/01-phpunit-suites.png)

## 2. Couverture du code métier

![Couverture en texte](docs/captures/02-couverture-texte.png)

![Rapport de couverture HTML](docs/captures/03-couverture-html.png)

## 3. Analyse statique (PHPStan niveau 6)

![PHPStan](docs/captures/04-phpstan.png)

## 4. Linters Symfony et sécurité des dépendances

![Lint et sécurité](docs/captures/05-lint-securite.png)

## 5. Tests de bout en bout (Cypress)

`make e2e` sauvegarde la base de développement, recharge les fixtures avant chaque fichier de scénarios, lance Cypress, puis restaure la base, même en cas d'échec.

![Résultat Cypress](docs/captures/06-cypress-run.png)

| Fichier | Ce qui est vérifié |
|---|---|
| `connexion.cy.js` | Mauvais mot de passe refusé, accueil (statistiques, catégories, derniers objets), déconnexion |
| `inventaire.cy.js` | Inventaire complet, filtre par catégorie, recherche par marque, modification d'une fiche |
| `scan.cy.js` | Photo réduite et envoyée en base64, fiche pré-remplie par l'IA (simulée par `cy.intercept`), catégorie cochée, objet ajouté. Saisie manuelle quand l'IA échoue. |
| `categories.cy.js` | Création d'une catégorie perso, proposée au scan, puis supprimée ; nom en double refusé |
| `droits.cy.js` | Une amie voit les looks sans pouvoir les modifier ; un inconnu reçoit une 403 ; un objet personnel reste privé |

### Démonstration

`make e2e-ouvrir` ouvre l'interface de Cypress : on choisit un fichier, et chaque étape se rejoue dans le navigateur, avec l'état de la page à chaque commande. Les scénarios tournent en taille téléphone (390 × 844), l'application étant mobile first.

Captures prises par Cypress pendant les scénarios :

| Accueil | Inventaire | Scan : analyse terminée |
|---|---|---|
| ![Accueil](docs/captures/e2e-accueil.png) | ![Inventaire](docs/captures/e2e-inventaire.png) | ![Scan](docs/captures/e2e-scan-analyse-terminee.png) |

| Fiche de l'objet ajouté | Catégories | Look vu par une amie |
|---|---|---|
| ![Fiche objet](docs/captures/e2e-fiche-objet.png) | ![Catégories](docs/captures/e2e-categories.png) | ![Look vu par une amie](docs/captures/e2e-look-vu-par-une-amie.png) |

---

## Vers l'intégration continue

La campagne est découpée pour passer telle quelle en pipeline CI/CD (à mettre en place avec Thomas). Ordre proposé, du plus rapide au plus lent :

1. **Qualité** : `make qualite`, sans base, en quelques secondes.
2. **Unitaires** : `make test-unitaires`, sans base.
3. **Intégration et fonctionnels** : service MariaDB, puis `make test-integration test-fonctionnels`.
4. **Couverture** : `make couverture`, avec publication du rapport HTML en artefact.
5. **Bout en bout** : `make up`, `make fixtures`, puis `npx cypress run`. Les captures sont publiées en artefact en cas d'échec.

Aucune étape n'appelle de service payant ou externe : Zernio et OpenRouter sont simulés en PHPUnit, et l'IA est interceptée par Cypress.
