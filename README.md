# Lead Manager - Plateforme de Gestion et de Confirmation des Leads

## Description

Plateforme complète de gestion et de confirmation des leads avec validation double (email et appel téléphonique), distribution automatique des leads, statistiques avancées et système d'audit.

## Technologies

- **Backend** : Laravel 12
- **Frontend** : Livewire 3, Volt, Flux UI
- **Base de données** : MySQL/PostgreSQL
- **Authentification** : Laravel Fortify avec MFA
- **Tests** : Pest PHP

## Fonctionnalités principales

✅ **Gestion des formulaires dynamiques**
- Création de formulaires avec champs personnalisables
- Validation côté client et serveur
- Association à des profils SMTP et templates d'email

✅ **Validation double des leads**
- Double opt-in par email
- Confirmation manuelle par appel téléphonique
- Suivi du cycle de vie des leads

✅ **Distribution automatique des leads**
- Round-robin équilibré
- Distribution pondérée par performance
- Attribution manuelle

✅ **Statistiques et reporting**
- Tableaux de bord interactifs
- Export CSV/PDF
- Alertes et notifications

✅ **Sécurité et audit**
- Authentification multi-facteurs (MFA)
- Journal d'audit complet
- Permissions par rôle

## Installation rapide

```bash
# Cloner le dépôt
git clone <repository-url>
cd lead-manager

# Installer les dépendances
composer install
npm install

# Configurer l'environnement
cp .env.example .env
php artisan key:generate

# Configurer la base de données dans .env puis :
php artisan migrate
php artisan db:seed

# Compiler les assets
npm run build

# Démarrer le serveur
php artisan serve
```

## Rôles

- **Super Administrateur** : Gestion complète de la plateforme
- **Propriétaire de Centre d'Appels** : Gestion de son centre et de ses agents
- **Agent** : Gestion des leads attribués

## Tests

```bash
# Exécuter tous les tests
php artisan test

# Exécuter un fichier de test spécifique
php artisan test tests/Feature/SecurityTest.php

# Avec filtrage
php artisan test --filter="logs form creation"
```

## Documentation

Consultez [DOCUMENTATION.md](DOCUMENTATION.md) pour la documentation complète.

## Licence

Propriétaire - Tous droits réservés

