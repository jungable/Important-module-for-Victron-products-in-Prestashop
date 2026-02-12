# Ps_VictronProducts - Module PrestaShop Victron Energy

Ce module permet d'importer et de synchroniser automatiquement les produits Victron Energy depuis leur API officielle (E-Order) vers votre boutique PrestaShop (1.7 / 8.x).

> [!TIP]
> Pour une **explication technique détaillée** de chaque fonctionnalité (Smart Update, Pruning, SEO...), consultez le fichier [FEATURES.md](FEATURES.md).

## Fonctionnalités Principales

*   **Synchronisation Intelligente ("Smart Update")** :
    *   Compare les données entrantes avec celles existantes.
    *   Ne met à jour que les produits modifiés, réduisant drastiquement le temps d'exécution (de 10+ min à <1 min).
*   **Gestion Automatique du Catalogue ("Pruning")** :
    *   Détecte les produits retirés du flux API Victron.
    *   Supprime automatiquement ces produits obsolètes de votre boutique pour garder un catalogue propre.
*   **Protection SEO** :
    *   Hérite et fige les URLs (`link_rewrite`) à la création du produit.
    *   Empêche les changements d'URL intempestifs lors des mises à jour de nom, préservant votre référencement.
*   **Tâche CRON Sécurisée** :
    *   Script `cron.php` inclus pour l'automatisation.
    *   Sécurisé par un jeton unique (`token`) vérifié à chaque appel.
    *   Compatible avec les appels HTTP (URL) et CLI (Ligne de commande serveur).

## Installation

1.  Téléchargez ou clonez ce dépôt dans le dossier `modules/` de votre PrestaShop.
2.  Accédez au Back Office > Modules > Gestionnaire de modules.
3.  Installez le module "Importateur de produits Victron".

## Configuration

Dans la page de configuration du module :

1.  **Clé API Victron E-Order** : Entrez votre clé d'accès fournie par Victron Energy.
2.  **Coefficient de prix** : Définissez le multiplicateur appliqué aux prix d'achat pour obtenir vos prix de vente (ex: 1.5).

## Utilisation Automatique (CRON)

Une URL sécurisée est générée et affichée dans la configuration du module.
Vous pouvez configurer une tâche planifiée sur votre serveur pour appeler cette URL régulièrement (ex: toutes les nuits).

Exemple d'appel CLI (recommandé pour crontab) :
```bash
php modules/ps_victronproducts/cron.php token=VOTRE_VISCTRON_SECURE_KEY
```

Ou via cURL :
```bash
curl "https://votre-boutique.com/modules/ps_victronproducts/cron.php?token=VOTRE_VICTRON_SECURE_KEY"
```
