# Rapport du Projet GOAS (Gestion des congés)

## 1. Présentation du Projet
GOAS est une application web de gestion des congés développée pour faciliter la gestion des absences et des congés du personnel. L'application permet une gestion complète du processus de demande de congés, depuis la soumission jusqu'à la validation, en passant par le suivi des soldes.

## 2. Architecture Technique

### 2.1 Technologies Utilisées
- Backend : PHP 8.1
- Base de données : MySQL/MariaDB
- Frontend : HTML5, CSS3, JavaScript
- Bibliothèques : Font Awesome 6.0
- Serveur : Apache (XAMPP)

### 2.2 Structure de la Base de Données
- `admin` : Gestion des administrateurs
- `employees` : Informations des employés
- `demandes_conges` : Demandes de congés
- `conges_annuels` : Suivi des soldes
- `types_conges` : Types de congés disponibles
- `services` : Départements/Services
- `conge_returns` : Suivi des retours de congés
- `licenses` : Gestion des licences

## 3. Fonctionnalités Principales

### 3.1 Gestion des Utilisateurs
- Authentification sécurisée
- Gestion des profils administrateurs
- Système de licence avec date d'expiration

### 3.2 Gestion des Employés
- Ajout/Modification/Suppression d'employés
- Gestion des informations personnelles
- Attribution aux services/projets

### 3.3 Gestion des Congés
- Demande de congés
- Validation/Refus des demandes
- Suivi des soldes de congés
- Calcul automatique des jours ouvrés
- Vérification des chevauchements

### 3.4 Calendrier et Planning
- Vue calendrier des congés
- Filtrage par période/service
- Export des plannings

### 3.5 Rapports et Statistiques
- Statistiques par service
- Suivi des absences
- Rapports personnalisables
- Export des données

## 4. Sécurité

### 4.1 Mesures Implémentées
- Protection contre les injections SQL (PDO)
- Hashage des mots de passe (Bcrypt)
- Validation des sessions
- Contrôle d'accès par rôle
- Protection des routes

### 4.2 Gestion des Licences
- Système de licence avec signature
- Vérification automatique
- Processus de renouvellement

## 5. Interface Utilisateur

### 5.1 Design
- Interface responsive
- Design moderne et épuré
- Navigation intuitive
- Thème cohérent

### 5.2 Expérience Utilisateur
- Messages de confirmation
- Gestion des erreurs
- Tooltips d'aide
- Formulaires validés

## 6. Performance et Optimisation
- Requêtes SQL optimisées
- Cache des données
- Chargement asynchrone
- Pagination des résultats

## 7. Évolutions Futures Possibles
1. Application mobile
2. Intégration avec le système de pointage
3. Module de notification par email
4. Génération automatique de documents
5. API REST pour intégrations externes

## 8. Conclusion
GOAS représente une solution complète et professionnelle pour la gestion des congés. Son architecture modulaire permet des évolutions futures tout en maintenant une base solide et sécurisée. 