# Plan de Tests Complet - Lead Manager

**Version** : 1.0  
**Date** : 2025-01-27  
**Auteur** : Équipe de développement

---

## 📋 Table des Matières

1. [Vue d'ensemble](#vue-densemble)
2. [Stratégie de Tests](#stratégie-de-tests)
3. [Tests Unitaires](#tests-unitaires)
4. [Tests Feature](#tests-feature)
5. [Tests d'Intégration](#tests-dintégration)
6. [Tests de Performance](#tests-de-performance)
7. [Tests de Sécurité](#tests-de-sécurité)
8. [Guide d'Exécution](#guide-dexécution)
9. [Couverture Cible](#couverture-cible)

---

## 📊 Vue d'ensemble

### Objectif

Ce document définit la stratégie de tests complète pour l'application **Lead Manager**, une plateforme de gestion de leads avec validation double (email + appel téléphonique), distribution automatique, statistiques et audit.

### Technologies de Test

- **Framework** : Pest PHP 3
- **Assertions** : Pest assertions + PHPUnit
- **Base de données** : SQLite en mémoire pour les tests
- **Queue** : Sync (pas de queue réelle en test)
- **Mocking** : Mockery via Pest

### Principes de Test

1. **AAA Pattern** : Arrange, Act, Assert
2. **Tests isolés** : Chaque test est indépendant
3. **Nommage descriptif** : Les noms de tests décrivent le comportement
4. **Couverture minimale** : 80% de couverture de code
5. **Tests rapides** : Exécution en moins de 30 secondes

---

## 🎯 Stratégie de Tests

### Pyramide de Tests

```
        /\
       /  \     E2E Tests (5%)
      /____\    
     /      \   Integration Tests (15%)
    /________\  
   /          \ Feature Tests (60%)
  /____________\
 /              \ Unit Tests (20%)
/________________\
```

### Types de Tests

1. **Tests Unitaires (20%)** : Modèles, Services, Helpers
2. **Tests Feature (60%)** : Routes, Controllers, Livewire Components
3. **Tests d'Intégration (15%)** : Workflows complets
4. **Tests E2E (5%)** : Scénarios utilisateur complets

---

## 🔬 Tests Unitaires

### 1. Modèles (Models)

#### 1.1. User Model

**Fichier** : `tests/Unit/Models/UserTest.php`

**Tests à implémenter** :

- ✅ Vérifier que `isSuperAdmin()` retourne true pour un super admin
- ✅ Vérifier que `isCallCenterOwner()` retourne true pour un propriétaire
- ✅ Vérifier que `isAgent()` retourne true pour un agent
- ✅ Vérifier que `isSupervisor()` retourne true pour un superviseur
- ✅ Vérifier que `initials()` génère correctement les initiales
- ✅ Vérifier les différents niveaux d'expérience (beginner, intermediate, advanced)
- ✅ Vérifier la relation avec `Role`
- ✅ Vérifier la relation avec `CallCenter`
- ✅ Vérifier la relation avec `assignedLeads`
- ✅ Vérifier la relation avec `supervisor`
- ✅ Vérifier la relation avec `supervisedAgents`
- ✅ Vérifier la relation avec `activityLogs`
- ✅ Vérifier la relation avec `apiTokens`

#### 1.2. Lead Model

**Fichier** : `tests/Unit/Models/LeadTest.php`

**Tests à implémenter** :

- ✅ Vérifier que `isEmailConfirmed()` retourne true quand email confirmé
- ✅ Vérifier que `isConfirmationTokenValid()` valide correctement les tokens
- ✅ Vérifier que les tokens expirés sont rejetés
- ✅ Vérifier que `getStatusEnum()` retourne le bon enum
- ✅ Vérifier que `setStatus()` fonctionne avec enum et string
- ✅ Vérifier que `isActive()` identifie correctement les statuts actifs
- ✅ Vérifier que `isFinal()` identifie correctement les statuts finaux
- ✅ Vérifier que `confirmEmail()` met à jour le statut correctement
- ✅ Vérifier que `markAsPendingCall()` change le statut
- ✅ Vérifier que `updateAfterCall()` fonctionne avec statuts valides
- ✅ Vérifier que `updateAfterCall()` lance une exception pour statuts invalides
- ✅ Vérifier la relation avec `Form`
- ✅ Vérifier la relation avec `assignedAgent`
- ✅ Vérifier la relation avec `callCenter`
- ✅ Vérifier que `getStatusHistory()` récupère l'historique

#### 1.3. Form Model

**Fichier** : `tests/Unit/Models/FormTest.php`

**Tests à implémenter** :

- ✅ Vérifier que l'UID est généré automatiquement à la création
- ✅ Vérifier que les UIDs sont uniques
- ✅ Vérifier que `fields` est casté en array
- ✅ Vérifier que `is_active` est casté en boolean
- ✅ Vérifier la relation avec `callCenter`
- ✅ Vérifier la relation avec `smtpProfile`
- ✅ Vérifier la relation avec `emailTemplate`
- ✅ Vérifier la relation avec `leads`

#### 1.4. CallCenter Model

**Fichier** : `tests/Unit/Models/CallCenterTest.php`

**Tests à implémenter** :

- ✅ Vérifier que `is_active` est casté en boolean
- ✅ Vérifier la relation avec `owner`
- ✅ Vérifier la relation avec `users`
- ✅ Vérifier la relation avec `leads`
- ✅ Vérifier la relation avec `forms`

#### 1.5. Autres Modèles

**Fichiers à créer** :

- `tests/Unit/Models/RoleTest.php`
- `tests/Unit/Models/SmtpProfileTest.php`
- `tests/Unit/Models/EmailTemplateTest.php`
- `tests/Unit/Models/ActivityLogTest.php`
- `tests/Unit/Models/ApiTokenTest.php`

### 2. Services (Services)

#### 2.1. LeadDistributionService

**Fichier** : `tests/Unit/Services/LeadDistributionServiceTest.php`

**Tests à implémenter** :

- ✅ Distribuer un lead avec méthode round-robin
- ✅ Distribuer un lead avec méthode weighted
- ✅ Retourner null pour méthode manuelle
- ✅ Retourner null quand aucun agent actif
- ✅ Assigner manuellement un lead à un agent
- ✅ Échouer si agent d'un autre call center
- ✅ Échouer si agent inactif
- ✅ Distribuer équitablement entre plusieurs agents
- ✅ Considérer la charge de travail dans round-robin
- ✅ Considérer la performance dans weighted

#### 2.2. StatisticsService

**Fichier** : `tests/Unit/Services/StatisticsServiceTest.php`

**Tests à implémenter** :

- ✅ Calculer les statistiques globales correctement
- ✅ Calculer les statistiques par call center
- ✅ Calculer les statistiques par agent
- ✅ Calculer le taux de conversion
- ✅ Calculer le temps de traitement moyen
- ✅ Identifier les leads nécessitant attention
- ✅ Identifier les agents sous-performants
- ✅ Calculer les leads dans le temps
- ✅ Calculer la performance des agents

#### 2.3. AuditService

**Fichier** : `tests/Unit/Services/AuditServiceTest.php`

**Tests à implémenter** :

- ✅ Logger une activité générique
- ✅ Logger la création d'un formulaire
- ✅ Logger la mise à jour d'un formulaire
- ✅ Logger la suppression d'un formulaire
- ✅ Logger la mise à jour de statut d'un lead
- ✅ Logger l'assignation d'un lead
- ✅ Logger la création d'un agent
- ✅ Logger la mise à jour d'un agent
- ✅ Logger la création d'un profil SMTP
- ✅ Logger la mise à jour d'un profil SMTP
- ✅ Logger un login
- ✅ Logger un logout
- ✅ Logger un échec de login
- ✅ Logger un changement de méthode de distribution

#### 2.4. Autres Services

**Fichiers à créer** :

- `tests/Unit/Services/LeadConfirmationServiceTest.php`
- `tests/Unit/Services/FormValidationServiceTest.php`
- `tests/Unit/Services/LeadStatusServiceTest.php`
- `tests/Unit/Services/SmtpTestServiceTest.php`

### 3. Enums et Classes Utilitaires

#### 3.1. LeadStatus Enum

**Fichier** : `tests/Unit/LeadStatusTest.php`

**Tests à implémenter** :

- ✅ Vérifier que tous les statuts existent (18 statuts)
- ✅ Vérifier que chaque statut a un label
- ✅ Vérifier que chaque statut a une classe de couleur
- ✅ Vérifier que `isActive()` identifie les statuts actifs
- ✅ Vérifier que `isFinal()` identifie les statuts finaux
- ✅ Vérifier que `canBeSetAfterCall()` identifie les statuts post-appel
- ✅ Vérifier que `options()` retourne tous les statuts
- ✅ Vérifier que `beginnerStatuses()` retourne les statuts pour débutants
- ✅ Vérifier que `activeStatuses()` retourne les statuts actifs
- ✅ Vérifier que `finalStatuses()` retourne les statuts finaux
- ✅ Vérifier que `postCallStatuses()` retourne les statuts post-appel
- ✅ Vérifier que `description()` retourne une description pour chaque statut

---

## 🎭 Tests Feature

### 1. Authentification

#### 1.1. Connexion

**Fichier** : `tests/Feature/Auth/AuthenticationTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Connexion avec identifiants valides
- ✅ Échec de connexion avec identifiants invalides
- ✅ Redirection des utilisateurs authentifiés depuis la page de login
- ✅ Journalisation des tentatives de connexion échouées
- ✅ Validation des champs requis
- ✅ Protection contre les attaques par force brute

#### 1.2. Inscription

**Fichier** : `tests/Feature/Auth/RegistrationTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Inscription d'un nouvel utilisateur
- ✅ Validation des champs requis
- ✅ Validation du format email
- ✅ Validation de la confirmation de mot de passe
- ✅ Validation de la force du mot de passe
- ✅ Vérification email après inscription

#### 1.3. MFA (Authentification Multi-Facteurs)

**Fichier** : `tests/Feature/Auth/TwoFactorAuthenticationTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Activer l'authentification à deux facteurs
- ✅ Désactiver l'authentification à deux facteurs
- ✅ Afficher les codes de récupération lors de l'activation
- ✅ Utiliser les codes de récupération
- ✅ Valider le code 2FA lors de la connexion
- ✅ Gérer les codes de récupération

#### 1.4. Réinitialisation de Mot de Passe

**Fichier** : `tests/Feature/Auth/PasswordResetTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Demander une réinitialisation de mot de passe
- ✅ Valider le token de réinitialisation
- ✅ Réinitialiser le mot de passe avec un token valide
- ✅ Rejeter les tokens expirés
- ✅ Valider la force du nouveau mot de passe

### 2. Gestion des Leads

#### 2.1. Soumission Publique de Formulaire

**Fichier** : `tests/Feature/PublicFormSubmissionTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Soumettre un formulaire avec données valides
- ✅ Valider les champs requis
- ✅ Valider le format email
- ✅ Mettre en queue l'email de confirmation
- ✅ Respecter le rate limiting
- ✅ Gérer les formulaires inactifs
- ✅ Valider les types de champs (email, text, tel, etc.)
- ✅ Gérer les champs optionnels

#### 2.2. Confirmation Email

**Fichier** : `tests/Feature/LeadConfirmationTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Confirmer l'email avec un token valide
- ✅ Rejeter les tokens expirés
- ✅ Rejeter les tokens invalides
- ✅ Déclencher la distribution après confirmation
- ✅ Mettre à jour le statut après confirmation
- ✅ Gérer les confirmations multiples (idempotence)

#### 2.3. Gestion des Leads par Agent

**Fichier** : `tests/Feature/AgentLeadManagementTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Agent peut voir ses leads assignés
- ✅ Agent peut voir les détails d'un lead
- ✅ Agent peut mettre à jour le statut après appel
- ✅ Agent ne peut pas mettre à jour vers un statut invalide
- ✅ Agent ne peut pas voir les leads d'autres agents
- ✅ Agent peut ajouter un commentaire lors de la mise à jour
- ✅ Validation des statuts post-appel
- ✅ Historique des changements de statut

#### 2.4. Gestion des Leads par Superviseur

**Fichier** : `tests/Feature/SupervisorLeadManagementTest.php` (à créer)

**Tests à implémenter** :

- ✅ Superviseur peut voir tous les leads de ses agents
- ✅ Superviseur peut voir les statistiques de ses agents
- ✅ Superviseur peut réassigner un lead
- ✅ Superviseur peut voir l'historique complet

#### 2.5. Gestion des Leads par Propriétaire

**Fichier** : `tests/Feature/OwnerLeadManagementTest.php` (à créer)

**Tests à implémenter** :

- ✅ Propriétaire peut voir tous les leads de son call center
- ✅ Propriétaire peut assigner manuellement un lead
- ✅ Propriétaire peut filtrer les leads
- ✅ Propriétaire peut exporter les leads
- ✅ Propriétaire ne peut pas voir les leads d'autres call centers

### 3. Distribution des Leads

**Fichier** : `tests/Feature/LeadDistributionTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Distribution automatique après confirmation email
- ✅ Distribution équitable avec round-robin
- ✅ Distribution pondérée par performance
- ✅ Propriétaire peut assigner manuellement
- ✅ Échec si aucun agent disponible
- ✅ Respect des call centers (isolation)
- ✅ Distribution basée sur la charge de travail
- ✅ Distribution basée sur l'expérience des agents

### 4. Gestion des Formulaires (Admin)

**Fichier** : `tests/Feature/Livewire/Admin/FormsTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Admin peut créer un nouveau formulaire
- ✅ Validation des champs requis
- ✅ Admin peut éditer un formulaire existant
- ✅ Admin peut prévisualiser un formulaire
- ✅ Admin peut voir les informations d'un formulaire
- ✅ Admin peut activer/désactiver un formulaire
- ✅ Validation des champs du formulaire
- ✅ Génération automatique de l'UID

#### 4.1. Création de Formulaire

**Fichier** : `tests/Feature/Livewire/Admin/Forms/CreateTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Créer un formulaire avec tous les champs
- ✅ Valider les champs requis
- ✅ Valider l'association au call center
- ✅ Valider l'association au profil SMTP
- ✅ Valider l'association au template email
- ✅ Générer un UID unique

#### 4.2. Édition de Formulaire

**Fichier** : `tests/Feature/Livewire/Admin/Forms/EditTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Éditer un formulaire existant
- ✅ Valider les modifications
- ✅ Logger les changements dans l'audit
- ✅ Préserver l'UID lors de l'édition

#### 4.3. Prévisualisation de Formulaire

**Fichier** : `tests/Feature/Livewire/Admin/Forms/PreviewTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Prévisualiser un formulaire
- ✅ Afficher tous les champs correctement
- ✅ Valider le rendu HTML

### 5. Gestion des Profils SMTP (Admin)

**Fichier** : `tests/Feature/Livewire/Admin/SmtpProfilesTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Admin peut créer un profil SMTP
- ✅ Admin peut éditer un profil SMTP
- ✅ Admin peut tester la connexion SMTP
- ✅ Validation des paramètres SMTP
- ✅ Chiffrement du mot de passe SMTP

#### 5.1. Test de Connexion SMTP

**Fichier** : `tests/Feature/Livewire/Admin/SmtpProfiles/TestConnectionTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Tester une connexion SMTP valide
- ✅ Échouer avec des identifiants invalides
- ✅ Échouer avec un serveur inaccessible
- ✅ Afficher les erreurs de connexion

### 6. Gestion des Templates Email (Admin)

**Fichier** : `tests/Feature/Livewire/Admin/EmailTemplatesTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Admin peut créer un template
- ✅ Admin peut éditer un template
- ✅ Validation des variables de template
- ✅ Prévisualisation du template

### 7. Statistiques

**Fichier** : `tests/Feature/StatisticsServiceTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Afficher les statistiques sur le dashboard admin
- ✅ Afficher les statistiques par call center pour le propriétaire
- ✅ Afficher les statistiques par agent pour le superviseur
- ✅ Exporter les statistiques en CSV
- ✅ Exporter les statistiques en PDF
- ✅ Calculer le taux de conversion
- ✅ Calculer le temps de traitement moyen
- ✅ Identifier les leads nécessitant attention
- ✅ Identifier les agents sous-performants

### 8. Export de Données

**Fichier** : `tests/Feature/ExportControllerTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Exporter les statistiques en CSV
- ✅ Exporter les statistiques en PDF
- ✅ Exporter les leads en CSV
- ✅ Valider le format CSV
- ✅ Valider le format PDF
- ✅ Inclure tous les champs pertinents

### 9. Audit et Sécurité

**Fichier** : `tests/Feature/SecurityTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Logger toutes les créations de formulaires
- ✅ Logger toutes les mises à jour de statut
- ✅ Logger toutes les assignations de leads
- ✅ Empêcher l'accès non autorisé aux routes admin
- ✅ Empêcher les attaques CSRF
- ✅ Appliquer le rate limiting sur les endpoints API
- ✅ Valider les tokens API
- ✅ Isoler les données par call center

### 10. Gestion des Agents

#### 10.1. Par Propriétaire

**Fichier** : `tests/Feature/OwnerManageAgentsTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Propriétaire peut créer un agent
- ✅ Propriétaire peut éditer un agent
- ✅ Propriétaire peut voir les statistiques d'un agent
- ✅ Propriétaire ne peut pas gérer les agents d'autres call centers
- ✅ Validation des champs lors de la création
- ✅ Assignation automatique au call center du propriétaire

#### 10.2. Par Superviseur

**Fichier** : `tests/Feature/SupervisorTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Superviseur peut voir ses agents
- ✅ Superviseur peut voir les statistiques de ses agents
- ✅ Superviseur peut voir les leads de ses agents

### 11. API

**Fichier** : `tests/Feature/ApiFormsTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Lister les formulaires avec token API valide
- ✅ Créer un formulaire via API
- ✅ Éditer un formulaire via API
- ✅ Supprimer un formulaire via API
- ✅ Rejeter les requêtes sans token
- ✅ Rejeter les tokens invalides
- ✅ Valider les permissions API
- ✅ Appliquer le rate limiting

### 12. Queue et Emails

**Fichier** : `tests/Feature/QueueEmailTest.php` (existe, à compléter)

**Tests à ajouter** :

- ✅ Mettre en queue l'email de confirmation
- ✅ Réessayer en cas d'échec SMTP
- ✅ Gérer les échecs après plusieurs tentatives
- ✅ Logger les échecs d'envoi
- ✅ Envoyer l'email de confirmation
- ✅ Envoyer l'email de rappel

---

## 🔗 Tests d'Intégration

### 1. Workflow Complet Lead

**Fichier** : `tests/Feature/Integration/CompleteLeadWorkflowTest.php` (à créer)

**Tests à implémenter** :

- ✅ Cycle de vie complet : soumission → confirmation → distribution → appel → conversion
- ✅ Vérifier tous les changements de statut
- ✅ Vérifier l'audit complet
- ✅ Vérifier les notifications
- ✅ Vérifier la distribution automatique

### 2. Distribution Multi-Agents

**Fichier** : `tests/Feature/Integration/MultiAgentDistributionTest.php` (à créer)

**Tests à implémenter** :

- ✅ Distribuer équitablement entre plusieurs agents
- ✅ Gérer la distribution pondérée par performance
- ✅ Considérer la charge de travail
- ✅ Isoler les call centers

### 3. Workflow Formulaire Complet

**Fichier** : `tests/Feature/Integration/CompleteFormWorkflowTest.php` (à créer)

**Tests à implémenter** :

- ✅ Créer un formulaire → Soumettre → Confirmer → Distribuer
- ✅ Vérifier l'association SMTP et template
- ✅ Vérifier la génération des leads
- ✅ Vérifier l'envoi des emails

---

## ⚡ Tests de Performance

### 1. Performance Distribution

**Fichier** : `tests/Feature/Performance/LeadDistributionPerformanceTest.php` (à créer)

**Tests à implémenter** :

- ✅ Distribuer 100 leads en moins de 5 secondes
- ✅ Gérer 1000 leads efficacement
- ✅ Optimiser les requêtes (éviter N+1)

### 2. Performance Statistiques

**Fichier** : `tests/Feature/Performance/StatisticsPerformanceTest.php` (à créer)

**Tests à implémenter** :

- ✅ Calculer les statistiques pour 1000 leads en moins de 2 secondes
- ✅ Calculer les statistiques par call center efficacement
- ✅ Optimiser les requêtes d'agrégation

### 3. Performance Recherche

**Fichier** : `tests/Feature/Performance/SearchPerformanceTest.php` (à créer)

**Tests à implémenter** :

- ✅ Rechercher dans 1000 leads en moins de 500ms
- ✅ Filtrer efficacement
- ✅ Paginer les résultats rapidement

---

## 🔒 Tests de Sécurité

### 1. Autorisation

**Fichier** : `tests/Feature/Security/AuthorizationTest.php` (à créer)

**Tests à implémenter** :

- ✅ Empêcher les agents d'accéder aux routes admin
- ✅ Empêcher les agents de voir les leads d'autres agents
- ✅ Empêcher les propriétaires d'accéder à d'autres call centers
- ✅ Valider l'authentification API
- ✅ Valider les permissions API

### 2. Validation des Données

**Fichier** : `tests/Feature/Security/DataValidationTest.php` (à créer)

**Tests à implémenter** :

- ✅ Prévenir les injections SQL
- ✅ Prévenir les attaques XSS
- ✅ Valider strictement le format email
- ✅ Appliquer les limites de longueur de champ
- ✅ Valider les types de données

### 3. Protection CSRF

**Fichier** : `tests/Feature/Security/CsrfProtectionTest.php` (à créer)

**Tests à implémenter** :

- ✅ Rejeter les requêtes sans token CSRF
- ✅ Valider les tokens CSRF
- ✅ Protéger les formulaires publics

### 4. Rate Limiting

**Fichier** : `tests/Feature/Security/RateLimitingTest.php` (à créer)

**Tests à implémenter** :

- ✅ Appliquer le rate limiting sur les soumissions de formulaires
- ✅ Appliquer le rate limiting sur les endpoints API
- ✅ Appliquer le rate limiting sur les tentatives de connexion

---

## 📈 Guide d'Exécution

### Commandes de Base

```bash
# Exécuter tous les tests
php artisan test

# Exécuter avec couverture
php artisan test --coverage

# Exécuter un fichier spécifique
php artisan test tests/Feature/LeadDistributionTest.php

# Exécuter avec filtre
php artisan test --filter="can distribute lead"

# Exécuter en parallèle (si Paratest installé)
php artisan test --parallel
```

### Tests par Catégorie

```bash
# Tests unitaires uniquement
php artisan test tests/Unit

# Tests feature uniquement
php artisan test tests/Feature

# Tests d'intégration
php artisan test tests/Feature/Integration

# Tests de performance
php artisan test tests/Feature/Performance

# Tests de sécurité
php artisan test tests/Feature/Security
```

### Tests par Modèle/Service

```bash
# Tests pour User
php artisan test --filter="User"

# Tests pour Lead
php artisan test --filter="Lead"

# Tests pour LeadDistributionService
php artisan test --filter="LeadDistribution"

# Tests pour StatisticsService
php artisan test --filter="Statistics"
```

### Tests par Fonctionnalité

```bash
# Tests d'authentification
php artisan test tests/Feature/Auth

# Tests de distribution
php artisan test tests/Feature/LeadDistributionTest.php

# Tests de formulaires
php artisan test tests/Feature/Livewire/Admin/Forms

# Tests de statistiques
php artisan test tests/Feature/Statistics
```

---

## 🎯 Couverture Cible

### Objectifs de Couverture

| Composant | Couverture Minimale | Couverture Cible |
|-----------|---------------------|------------------|
| Modèles | 90% | 95% |
| Services | 85% | 90% |
| Controllers | 80% | 85% |
| Livewire Components | 75% | 80% |
| Middleware | 90% | 95% |
| **Global** | **80%** | **85%** |

### Métriques de Qualité

- **Temps d'exécution** : < 30 secondes pour tous les tests
- **Tests par fonctionnalité** : Minimum 5 tests
- **Tests d'intégration** : Au moins 1 test par workflow majeur
- **Tests de régression** : Tous les bugs corrigés doivent avoir un test

---

## ✅ Checklist de Tests

### Avant chaque commit

- [ ] Tous les tests unitaires passent
- [ ] Tous les tests feature passent
- [ ] Aucune régression introduite
- [ ] Nouveaux tests ajoutés pour nouvelles fonctionnalités
- [ ] Code formaté avec Pint (`vendor/bin/pint`)

### Avant chaque release

- [ ] Tous les tests passent (100%)
- [ ] Couverture de code ≥ 80%
- [ ] Tests de performance validés
- [ ] Tests de sécurité validés
- [ ] Tests d'intégration validés
- [ ] Documentation des tests à jour

---

## 📝 Notes Importantes

### Bonnes Pratiques

1. **Nommage** : Utiliser des noms descriptifs qui expliquent le comportement testé
2. **Isolation** : Chaque test doit être indépendant et pouvoir s'exécuter seul
3. **AAA Pattern** : Arrange (setup), Act (action), Assert (vérification)
4. **Données de test** : Utiliser les factories plutôt que des données hardcodées
5. **Mocking** : Mocker les dépendances externes (API, emails, etc.)

### À Éviter

1. ❌ Tests qui dépendent d'autres tests
2. ❌ Tests qui modifient l'état global
3. ❌ Tests trop complexes (diviser en plusieurs tests)
4. ❌ Tests sans assertions claires
5. ❌ Tests qui testent plusieurs choses à la fois

### Structure Recommandée

```
tests/
├── Feature/
│   ├── Auth/
│   ├── Integration/
│   ├── Livewire/
│   ├── Models/
│   ├── Performance/
│   ├── Security/
│   └── Services/
└── Unit/
    ├── Models/
    └── Services/
```

---

## 🚀 Prochaines Étapes

1. **Implémenter les tests unitaires manquants** pour les modèles
2. **Implémenter les tests unitaires manquants** pour les services
3. **Compléter les tests feature existants** avec les cas manquants
4. **Créer les tests d'intégration** pour les workflows complets
5. **Créer les tests de performance** pour les opérations critiques
6. **Créer les tests de sécurité** pour protéger l'application
7. **Configurer CI/CD** pour exécuter les tests automatiquement
8. **Documenter les cas limites** et edge cases

---

## 📊 État Actuel des Tests

### Tests Existants

- ✅ 58 fichiers de tests Feature
- ✅ Tests d'authentification complets
- ✅ Tests de formulaires (partiels)
- ✅ Tests de distribution (partiels)
- ✅ Tests de statistiques (partiels)
- ✅ Tests de sécurité (partiels)

### Tests Manquants

- ⚠️ Tests unitaires pour modèles (partiels)
- ⚠️ Tests unitaires pour services (partiels)
- ⚠️ Tests d'intégration (manquants)
- ⚠️ Tests de performance (manquants)
- ⚠️ Tests de sécurité complets (partiels)
- ⚠️ Tests pour LeadStatus enum (manquants)
- ⚠️ Tests pour tous les workflows complets

---

## 🔍 Priorités d'Implémentation

### Priorité 1 (Critique)

1. Tests unitaires pour `Lead` model (tous les cas)
2. Tests unitaires pour `User` model (tous les cas)
3. Tests unitaires pour `LeadDistributionService` (tous les cas)
4. Tests unitaires pour `StatisticsService` (tous les cas)
5. Tests d'intégration pour workflow complet lead

### Priorité 2 (Important)

1. Tests unitaires pour `Form` model
2. Tests unitaires pour `CallCenter` model
3. Tests unitaires pour `AuditService`
4. Tests unitaires pour `LeadStatus` enum
5. Tests de sécurité complets

### Priorité 3 (Amélioration)

1. Tests de performance
2. Tests pour tous les modèles restants
3. Tests pour tous les services restants
4. Tests E2E pour workflows critiques

---

**Document créé le** : 2025-01-27  
**Dernière mise à jour** : 2025-01-27  
**Version** : 1.0

