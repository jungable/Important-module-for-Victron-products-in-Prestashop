# Fonctionnalités et Logique Technique du Module Victron

Ce document détaille le fonctionnement interne de chaque fonctionnalité clé du module `ps_victronproducts`.

## 1. Importation "Intelligente" (Smart Update)
**Objectif** : Optimiser les performances (temps d'exécution) et réduire la charge sur la base de données.

**Logique** :
1.  Le module télécharge le flux complet des produits depuis l'API Victron.
2.  Pour chaque produit, il le recherche dans la base PrestaShop via sa référence (`VIC-{SKU}`).
3.  Si le produit existe, il effectue une **comparaison champ par champ** (Prix, Nom, Description, Poids, Dimensions) entre la donnée reçue et la donnée en base.
4.  Si **aucune différence** n'est détectée (avec une tolérance minimale pour les flottants), **aucune écriture** n'est faite en base de données.
5.  Le produit n'est sauvegardé (`$product->save()`) que si une modification réelle est nécessaire ou si c'est un nouveau produit.

## 2. Nettoyage Automatique (Pruning)
**Objectif** : Garantir que le catalogue PrestaShop reflète exactement l'offre Victron actuelle, sans doublons ni produits fantômes.

**Logique** :
1.  **Inventaire** : Durant la boucle d'importation, le module stocke en mémoire vive (`$seenSkus`) la liste de toutes les références produits valides reçues de l'API lors de *cette* exécution.
2.  **Comparaison** : Une fois l'import terminé, la méthode `pruneProducts` interroge la base de données pour récupérer *tous* les produits existants ayant le préfixe "VIC-".
3.  **Purge** : Pour chaque produit en base, le module vérifie si sa référence est présente dans la liste `$seenSkus`.
    *   **Absent** de la liste = Le produit n'est plus distribué par Victron -> **Suppression** immédiate (`$product->delete()`).
    *   **Présent** = Le produit est valide -> Conservation.

## 3. Protection SEO (URLs Stables)
**Objectif** : Préserver le référencement naturel (Google) en évitant les changements d'URL intempestifs.

**Logique** :
*   Le champ `link_rewrite` (qui définit l'URL du produit, ex: `batterie-gel-12v`) est généré automatiquement à la **création** du produit, basé sur son nom.
*   Lors des **mises à jour** futures, même si Victron change le nom commercial du produit dans son API, le module **ne modifie pas** le `link_rewrite` existant.
*   Cela évite les erreurs 404 et la perte de "jus" SEO sur des pages déjà indexées.

## 4. Gestion des Catégories et Hiérarchie
**Objectif** : Reproduire automatiquement l'arborescence Victron sans intervention manuelle.

**Logique** :
1.  Le module récupère la liste des catégories via l'API.
2.  Il crée (si elle n'existe pas) une catégorie racine "Produits Victron Energy".
3.  Il analyse les champs `category` (Groupe) et `subcategory` (Sous-groupe) de chaque produit.
4.  Il vérifie l'existence de ces catégories dans PrestaShop (par nom). Si elles n'existent pas, il les crée à la volée à l'intérieur de la catégorie racine.
5.  Il associe le produit à la sous-catégorie la plus précise possible. Les images des catégories sont également importées.

## 5. Sécurité de la Tâche Planifiée (CRON)
**Objectif** : Sécuriser l'automatisation des mises à jour contre les appels malveillants par des tiers.

**Logique** :
*   Un **token unique** (clé MD5 de 32 caractères) est généré aléatoirement lors de l'installation du module.
*   Le script `cron.php` exige que ce token soit passé en paramètre (GET ou argument CLI).
*   **Vérification** :
    *   Pas de token ou token invalide -> Arrêt immédiat avec code **HTTP 403 Forbidden**.
    *   Token valide -> Démarrage de la synchronisation (`runSync`).
*   Le script gère spécifiquement l’environnement CLI (Ligne de commande) pour permettre une exécution via `crontab` sans passer par le serveur web (Apache/Nginx), évitant les timeouts HTTP.

## 6. Calcul des Prix et Marge
**Objectif** : Automatiser la politique tarifaire.

**Logique** :
*   Le module récupère le "Prix Brut" du flux Victron.
*   Il applique la formule : `Prix Boutique = Prix Victron * Coefficient`.
*   Le coefficient est défini dans la configuration du module (Back Office). Une valeur de `1.0` applique le prix brut. Une valeur de `1.5` ajoute 50% de marge.
