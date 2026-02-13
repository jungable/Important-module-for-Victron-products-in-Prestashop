# Pistes d'Amélioration du Module

Voici une liste de fonctionnalités et d'optimisations techniques qui pourraient être ajoutées pour rendre le module encore plus robuste et complet.

## 1. Expérience Utilisateur (UI/UX)
*   **Barre de Progression** : Lors de la synchronisation manuelle depuis le Back Office, afficher une barre de progression en temps réel (via Ajax) pour éviter l'effet "page qui charge indéfiniment".
*   **Journal d'Activité (Logs)** : Créer un onglet dans la configuration affichant l'historique des dernières synchronisations (Date, Durée, Nombre de produits créés/mis à jour/supprimés, Erreurs éventuelles).

## 2. Fonctionnalités Avancées
*   **Support Multi-Boutique** : Adapter le module pour qu'il puisse gérer des configurations différentes (Clé API, Prix) selon la boutique sélectionnée dans un contexte multi-shop PrestaShop.
*   **Mapping de Catégories** : Ajouter une interface pour permettre à l'administrateur de choisir manuellement dans quelle catégorie PrestaShop chaque famille de produits Victron doit aller (au lieu de la création automatique).
*   **Notifications Email** : Envoyer un email automatique à l'administrateur en cas d'échec de la tâche CRON ou si le nombre de produits supprimés dépasse un seuil de sécurité.

## 3. Qualité Technique & Code
*   **Commande Symfony (CLI)** : Remplacer le script `cron.php` par une véritable commande console Symfony (`bin/console victron:sync`), ce qui est le standard moderne pour PrestaShop 8.
*   **Tests Unitaires** : Ajouter une suite de tests automatisés (PHPUnit) pour valider chaque fonction critique à chaque modification du code.
*   **Hooks Personnalisés** : Ajouter des "Hooks" PrestaShop (ex: `actionVictronProductImport`) pour permettre à d'autres modules de se greffer sur l'importation (par exemple pour envoyer une notif Slack à chaque nouveau produit).

## 4. Gestion Commerciale
*   **Règles de Prix Avancées** : Au lieu d'un coefficient unique, permettre de définir des marges par catégorie ou par tranche de prix.
*   **Gestion du Stock** : Si l'API Victron fournit le stock réel, l'intégrer pour mettre à jour les quantités disponibles (actuellement forcé à une valeur par défaut de `100`).
