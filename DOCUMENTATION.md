# Documentation - Plateforme de Gestion et de Confirmation des Leads

## Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Fonctionnalités](#fonctionnalités)
5. [Sécurité](#sécurité)
6. [API](#api)
7. [Dépannage](#dépannage)

## Introduction

Cette plateforme permet de gérer et de confirmer les leads provenant de différentes landing pages. Elle offre une validation double (email et appel téléphonique) et des outils de suivi, de relance et de reporting.

### Rôles et permissions

- **Super Administrateur** : Accès complet à toutes les fonctionnalités
- **Propriétaire de Centre d'Appels** : Gestion de son centre d'appels, de ses agents et de ses leads
- **Agent** : Accès aux leads qui lui sont attribués et possibilité de mettre à jour leur statut

## Installation

### Prérequis

- PHP 8.2.12 ou supérieur
- Composer
- Node.js et npm
- Base de données MySQL/PostgreSQL

### Étapes d'installation

1. Cloner le dépôt :
```bash
git clone <repository-url>
cd lead-manager
```

2. Installer les dépendances PHP :
```bash
composer install
```

3. Installer les dépendances JavaScript :
```bash
npm install
```

4. Copier le fichier d'environnement :
```bash
cp .env.example .env
```

5. Générer la clé d'application :
```bash
php artisan key:generate
```

6. Configurer la base de données dans `.env` :
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lead_manager
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

7. Exécuter les migrations :
```bash
php artisan migrate
```

8. Créer les rôles et utilisateurs initiaux :
```bash
php artisan db:seed
```

9. Compiler les assets :
```bash
npm run build
```

10. Démarrer le serveur de développement :
```bash
php artisan serve
```

## Configuration

### Variables d'environnement importantes

```env
APP_NAME="Lead Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Base de données
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lead_manager
DB_USERNAME=your_username
DB_PASSWORD=your_password

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Authentification Multi-Facteurs (MFA)

L'authentification à deux facteurs est activée par défaut. Les utilisateurs peuvent l'activer depuis leur page de paramètres.

Pour désactiver le MFA, modifiez `config/fortify.php` :

```php
'features' => [
    // Features::twoFactorAuthentication([...]),
],
```

## Fonctionnalités

### 1. Gestion des Formulaires

Les Super Administrateurs peuvent créer des formulaires dynamiques avec :
- Champs personnalisables (texte, email, téléphone, listes déroulantes, etc.)
- Règles de validation par champ
- Association à un profil SMTP et un template d'email

### 2. Validation Double des Leads

#### Validation par Email (Double Opt-In)

1. Le lead soumet le formulaire
2. Un email de confirmation est envoyé avec un lien unique
3. Le lead clique sur le lien pour confirmer son email
4. Le statut passe à `email_confirmed`

#### Validation par Appel Téléphonique

1. Après confirmation de l'email, le lead est attribué à un agent
2. L'agent contacte le lead par téléphone
3. L'agent met à jour le statut (Confirmé, Rejeté, En attente de rappel)

### 3. Distribution des Leads

Trois méthodes de distribution sont disponibles :

- **Round-Robin** : Distribution équilibrée entre les agents
- **Weighted** : Distribution basée sur les performances des agents
- **Manual** : Attribution manuelle par le propriétaire du centre d'appels

### 4. Statistiques et Reporting

- Tableaux de bord interactifs avec graphiques
- Export CSV/PDF des statistiques
- Alertes pour les leads nécessitant une attention
- Identification des agents sous-performants

### 5. Journal d'Audit

Toutes les actions importantes sont enregistrées dans le journal d'audit :
- Création/modification/suppression de formulaires
- Mise à jour des statuts de leads
- Attribution de leads
- Changements de configuration
- Connexions/déconnexions

## Sécurité

### Authentification Multi-Facteurs (MFA)

L'application utilise Laravel Fortify pour l'authentification à deux facteurs. Les utilisateurs peuvent :
- Activer le MFA depuis leur page de paramètres
- Scanner un code QR avec leur application d'authentification
- Utiliser des codes de récupération en cas de perte d'accès

### Journal d'Audit

Toutes les actions critiques sont enregistrées avec :
- Utilisateur ayant effectué l'action
- Date et heure
- Adresse IP
- User Agent
- Détails de l'action

### Permissions par Rôle

- Les Super Administrateurs ont accès à toutes les fonctionnalités
- Les Propriétaires de Centres d'Appels ne peuvent gérer que leur propre centre
- Les Agents ne peuvent voir que leurs leads attribués

## API

### Endpoints publics

#### Soumission de formulaire

```http
POST /api/forms/{form_id}/submit
Content-Type: application/json

{
  "email": "lead@example.com",
  "name": "John Doe",
  "phone": "+33123456789",
  ...
}
```

#### Confirmation d'email

```http
GET /leads/confirm/{token}
```

### Endpoints authentifiés

Tous les endpoints nécessitent une authentification. Consultez la documentation API complète pour plus de détails.

## Dépannage

### Problèmes courants

#### Erreur "Unable to locate file in Vite manifest"

Exécutez :
```bash
npm run build
# ou
npm run dev
```

#### Erreur de connexion à la base de données

Vérifiez les paramètres dans `.env` et assurez-vous que la base de données existe.

#### Emails non envoyés

Vérifiez la configuration SMTP dans `.env` ou créez un profil SMTP dans l'interface d'administration.

#### Erreur 403 (Forbidden)

Vérifiez que l'utilisateur a le bon rôle et les permissions nécessaires.

### Logs

Les logs de l'application sont disponibles dans `storage/logs/laravel.log`.

Pour voir les logs en temps réel :
```bash
tail -f storage/logs/laravel.log
```

## Support

Pour toute question ou problème, contactez l'équipe de développement.

