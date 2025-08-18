# Guide d'Utilisation - Système de Rôles GOAS

## Vue d'ensemble

Le système GOAS a été mis à jour pour inclure un système de gestion des rôles avec trois niveaux d'accès :

- **Superadmin** : Accès complet à toutes les fonctionnalités
- **Admin** : Accès aux fonctionnalités de gestion (employés, congés, rapports)
- **User** : Accès limité (à définir selon vos besoins)

## Installation et Configuration

### 1. Mise à jour de la base de données

Exécutez le script de configuration pour ajouter le champ `role` à la table `admin` :

```
http://votre-domaine/setup_roles.php
```

Ce script va :
- Ajouter la colonne `role` à la table `admin`
- Définir le premier utilisateur comme superadmin
- Créer un utilisateur de test si aucun n'existe

### 2. Structure de la table admin

La table `admin` a été modifiée pour inclure :

```sql
ALTER TABLE admin ADD COLUMN role ENUM('superadmin', 'admin', 'user') DEFAULT 'admin';
```

## Hiérarchie des Rôles

### Superadmin
- **Accès complet** à toutes les fonctionnalités
- **Gestion des utilisateurs** : créer, modifier, supprimer des comptes
- **Gestion des rôles** : attribuer les rôles aux utilisateurs
- **Accès à toutes les pages** du système

### Admin
- **Gestion des employés** : ajouter, modifier, supprimer des employés
- **Gestion des congés** : approuver, refuser, modifier les demandes
- **Rapports et statistiques** : accès aux rapports
- **Calendrier** : vue d'ensemble des congés

### User
- **Accès limité** (à configurer selon vos besoins)
- Peut être utilisé pour des utilisateurs avec des permissions restreintes

## Fonctionnalités

### Page de Gestion des Utilisateurs

**Accès** : Seuls les superadmins peuvent accéder à cette page via le menu "Gestion des Utilisateurs" dans le tableau de bord.

**Fonctionnalités** :
- **Créer un nouvel utilisateur** : nom d'utilisateur, email, mot de passe, nom, prénoms, rôle
- **Modifier un utilisateur** : email, nom, prénoms, rôle
- **Supprimer un utilisateur** : suppression définitive du compte
- **Réinitialiser le mot de passe** : génération d'un nouveau mot de passe sécurisé

### Sécurité

- **Protection des rôles** : Un utilisateur ne peut pas modifier son propre rôle
- **Protection de suppression** : Un utilisateur ne peut pas supprimer son propre compte
- **Mots de passe sécurisés** : Génération automatique de mots de passe respectant les critères de sécurité
- **Validation des données** : Toutes les entrées sont validées et nettoyées

## Utilisation

### Pour les Superadmins

1. **Connexion** : Connectez-vous avec un compte superadmin
2. **Accès au menu** : Le menu "Gestion des Utilisateurs" apparaît dans le tableau de bord
3. **Création d'utilisateurs** : Cliquez sur "Nouvel Utilisateur" pour créer des comptes
4. **Gestion des rôles** : Attribuez les rôles appropriés aux utilisateurs

### Pour les Admins

1. **Connexion** : Connectez-vous avec un compte admin
2. **Accès limité** : Seules les fonctionnalités de gestion sont disponibles
3. **Pas d'accès** à la gestion des utilisateurs

### Pour les Users

1. **Connexion** : Connectez-vous avec un compte user
2. **Accès restreint** : Fonctionnalités limitées selon la configuration

## Fichiers Modifiés/Créés

### Nouveaux fichiers :
- `gestion_utilisateurs.php` : Page de gestion des utilisateurs
- `check_permissions.php` : Système de vérification des permissions
- `setup_roles.php` : Script de configuration
- `guide_roles.md` : Ce guide d'utilisation

### Fichiers modifiés :
- `check_auth.php` : Ajout de la récupération du rôle
- `functions.php` : Ajout de la fonction `generateSecurePassword`
- `gestion_conges.php` : Ajout du menu de gestion des utilisateurs
- `index.php` : Ajout du rôle dans la session de connexion
- `modify_admin_table.php` : Mise à jour pour ajouter le champ role

## Fonctions Utilitaires

### Vérification des permissions

```php
require_once 'check_permissions.php';

// Vérifier si l'utilisateur a un rôle spécifique
if (hasRole('admin')) {
    // Code pour les admins
}

// Vérifier si l'utilisateur est superadmin
if (isSuperAdmin()) {
    // Code pour les superadmins
}

// Rediriger si l'utilisateur n'a pas les permissions
requireRole('superadmin', 'gestion_conges.php');
```

### Affichage conditionnel

```php
// Afficher un contenu seulement pour les superadmins
ifHasRole('superadmin', function() {
    echo '<div>Contenu réservé aux superadmins</div>';
});
```

## Maintenance

### Ajout d'un nouveau rôle

1. Modifier l'énumération dans la base de données
2. Mettre à jour la hiérarchie dans `check_permissions.php`
3. Ajouter les vérifications appropriées dans les pages

### Sauvegarde

Avant toute modification, effectuez une sauvegarde de :
- La base de données
- Les fichiers PHP modifiés

## Support

En cas de problème :
1. Vérifiez les logs d'erreur PHP
2. Consultez la console du navigateur
3. Vérifiez les permissions de la base de données
4. Contactez l'administrateur système

---

**Note** : Ce système de rôles améliore la sécurité et la gestion des utilisateurs de votre application GOAS. Assurez-vous de tester toutes les fonctionnalités après la mise en place. 