# Protocole de Tests et Validation

Ce document recense les tests effectués pour valider la stabilité, la sécurité et la performance du module `ps_victronproducts`.

## 1. Tests de Sécurité (CRON)

L'objectif est de s'assurer que personne ne peut lancer la synchronisation sans autorisation.

| ID Test | Scénario | Commande / Action | Résultat Attendu | Résultat Obtenu | Statut |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **SEC-01** | Accès sans token | `curl .../cron.php` | HTTP 403 Forbidden ("Invalid or missing security token") | **403 Forbidden** | ✅ PASS |
| **SEC-02** | Accès avec token invalide | `curl .../cron.php?token=12345` | HTTP 403 Forbidden | **403 Forbidden** | ✅ PASS |
| **SEC-03** | Accès avec token valide | `curl .../cron.php?token={GOOD_TOKEN}` | HTTP 200 OK + "Synchronisation terminée" | **200 OK** | ✅ PASS |
| **SEC-04** | Exécution CLI (Serveur) | `php modules/ps_victronproducts/cron.php token={GOOD_TOKEN}` | Exécution du script sans erreur PHP | **Succès** | ✅ PASS |

## 2. Tests Fonctionnels (Logique Métier)

### A. Smart Update (Performance)
*   **Test** : Lancer une synchronisation deux fois de suite sans changement côté API Victron.
*   **Résultat** : La première exécution crée les produits. La seconde exécution détecte `0` changements et se termine en quelques secondes (vs minutes).
*   **Verification** : Logs de debug et temps d'exécution.
*   **Statut** : ✅ PASS

### B. Pruning (Nettoyage)
*   **Test** :
    1.  Importer 100 produits.
    2.  Simuler la disparition de 5 produits du flux API.
    3.  Relancer la synchronisation.
*   **Résultat** : Les 5 produits absents du flux sont supprimés de la base de données PrestaShop.
*   **Statut** : ✅ PASS

### C. SEO & URLs
*   **Test** :
    1.  Créer un produit "Victron Battery 12V" -> URL `victron-battery-12v`.
    2.  Renommer le produit dans le flux API en "Victron Super Battery 12V".
    3.  Lancer la synchronisation.
*   **Résultat** : Le nom est mis à jour sur la boutique, mais l'URL reste `victron-battery-12v`.
*   **Statut** : ✅ PASS

## 3. Environnement et Compatibilité

*   **Docker** : Tests validés sous conteneur PrestaShop 8.1 / PHP 8.1.
*   **Système de Fichiers** : Vérification des droits d'écriture pour les images (`chmod` géré par PrestaShop).
*   **Mémoire** : Tests de charge avec `memory_limit: 1024M` pour gérer le flux complet de Victron.
