# Sprint 8 : Optimisation et Automatisation Avancée

## 📋 Vue d'ensemble

**Durée estimée** : 2-3 semaines  
**Objectif principal** : Automatiser davantage la gestion des leads avec un système de planification, de scoring intelligent, et d'alertes personnalisées pour améliorer l'efficacité des équipes.

---

## 🎯 Objectifs

* Mise en place d'un **calendrier et système de planification** pour les rappels et rendez-vous.
* Développement d'un **système de scoring automatique** des leads pour prioriser les actions.
* Implémentation d'un **système de tags et catégories** pour organiser et filtrer les leads.
* Création d'un **système d'alertes configurables** pour notifier les utilisateurs selon leurs préférences.

---

## 📝 Tâches détaillées

### 1. Calendrier et Planification de Rappels

#### 1.1. Modèle et Migration
- Créer le modèle `LeadReminder` avec les champs :
  - `lead_id` : Lead concerné
  - `user_id` : Utilisateur qui a créé le rappel
  - `reminder_date` : Date et heure du rappel
  - `reminder_type` : Type (call_back, follow_up, appointment)
  - `notes` : Notes additionnelles
  - `is_completed` : Statut de complétion
  - `completed_at` : Date de complétion
  - `notified_at` : Date de dernière notification
- Créer la migration avec index sur `lead_id`, `user_id`, `reminder_date`
- Créer le factory pour les tests

#### 1.2. Service de Planification
- Créer `ReminderService` avec :
  - `scheduleReminder(Lead $lead, User $user, Carbon $date, string $type, ?string $notes = null)` : Planification
  - `getUpcomingReminders(?User $user = null, int $days = 7)` : Récupération des rappels à venir
  - `completeReminder(LeadReminder $reminder)` : Marquer comme complété
  - `cancelReminder(LeadReminder $reminder)` : Annuler un rappel
  - `getRemindersForDate(Carbon $date, ?User $user = null)` : Rappels pour une date

#### 1.3. Interface Calendrier
- Créer le composant Livewire `ReminderCalendar` :
  - Vue mensuelle avec les rappels affichés
  - Vue hebdomadaire détaillée
  - Vue liste (prochains 30 jours)
  - Navigation mois/semaine/jour
- Utiliser une bibliothèque JavaScript (FullCalendar ou Alpine.js)
- Intégrer avec Flux UI pour le style

#### 1.4. Gestion des Rappels depuis les Leads
- Ajouter un bouton "Planifier un rappel" dans la page de détail du lead
- Modal Flux pour créer un rappel avec :
  - Sélection de date/heure
  - Type de rappel
  - Notes optionnelles
  - Rappel automatique (email/notification)
- Affichage des rappels planifiés dans la timeline du lead

#### 1.5. Notifications Automatiques
- Créer la commande Artisan `reminders:notify` :
  - Vérifie les rappels à venir dans les 24h
  - Envoie des notifications aux utilisateurs concernés
  - Marque `notified_at` pour éviter les doublons
- Programmer dans `app/Console/Kernel.php` (toutes les heures)
- Notifications par email et in-app

#### 1.6. Intégration Calendrier Externe (Optionnel)
- Support d'export iCal pour Google Calendar/Outlook
- Endpoint `GET /leads/{lead}/reminders.ics`
- Génération de fichiers `.ics` pour les rappels

#### 1.7. Tests
- Tests unitaires pour `ReminderService`
- Tests feature pour la création/gestion des rappels
- Tests de la commande de notification
- Tests d'intégration calendrier

---

### 2. Système de Scoring des Leads

#### 2.1. Modèle et Migration
- Ajouter les champs au modèle `Lead` :
  - `score` : Score numérique (0-100)
  - `score_updated_at` : Date de dernière mise à jour
  - `score_factors` : Facteurs de scoring (JSON)
- Créer la migration pour ajouter ces colonnes
- Index sur `score` pour les requêtes de tri

#### 2.2. Service de Scoring
- Créer `LeadScoringService` avec :
  - `calculateScore(Lead $lead)` : Calcul du score
  - `updateScore(Lead $lead)` : Mise à jour du score
  - `getScoreFactors()` : Liste des facteurs configurables
- Facteurs de scoring (pondérés) :
  - **Source du formulaire** (10%) : Formulaires premium = +20
  - **Temps de confirmation email** (15%) : < 1h = +30, < 24h = +15
  - **Complétude des données** (20%) : Tous les champs remplis = +20
  - **Historique du lead** (25%) : Nombre d'interactions, notes positives
  - **Statut actuel** (20%) : `email_confirmed` = +15, `pending_call` = +10
  - **Données comportementales** (10%) : Heure de soumission, jour de la semaine
- Configuration des poids dans `config/lead-scoring.php`

#### 2.3. Calcul Automatique
- Utiliser `LeadObserver` pour recalculer le score lors de :
  - Création du lead
  - Confirmation email
  - Changement de statut
  - Ajout de notes
- Job en queue pour le recalcul en arrière-plan si nécessaire

#### 2.4. Interface Utilisateur
- Afficher le score dans :
  - Liste des leads (badge coloré selon le score)
  - Page de détail du lead
  - Tableaux de bord
- Badge de score :
  - 80-100 : Vert (Priorité haute)
  - 60-79 : Orange (Priorité moyenne)
  - 0-59 : Rouge (Priorité basse)
- Tri par score dans les listes
- Filtre par plage de score

#### 2.5. Distribution Intelligente
- Modifier `LeadDistributionService` pour :
  - Option "Score-based" : Distribuer les leads à score élevé aux meilleurs agents
  - Combiner avec la méthode weighted existante
- Configuration dans les paramètres du centre d'appels

#### 2.6. Tests
- Tests unitaires pour `LeadScoringService`
- Tests de calcul avec différents scénarios
- Tests d'intégration avec la distribution

---

### 3. Système de Tags et Catégories

#### 3.1. Modèles et Migrations
- Créer le modèle `Tag` avec :
  - `name` : Nom du tag (unique)
  - `color` : Couleur hexadécimale
  - `description` : Description optionnelle
  - `is_system` : Tag système (non supprimable)
- Créer la table pivot `lead_tag` :
  - `lead_id`
  - `tag_id`
  - `user_id` : Utilisateur qui a ajouté le tag
  - `created_at`
- Créer les migrations avec index appropriés

#### 3.2. Relations Many-to-Many
- Ajouter la relation `tags()` dans `Lead`
- Ajouter la relation `leads()` dans `Tag`
- Créer des scopes :
  - `withTag(string $tagName)` : Filtrer par tag
  - `withAnyTag(array $tagNames)` : Filtrer par plusieurs tags
  - `withoutTag(string $tagName)` : Exclure un tag

#### 3.3. Service de Gestion
- Créer `TagService` avec :
  - `createTag(string $name, string $color, ?string $description = null)` : Création
  - `attachTag(Lead $lead, Tag $tag, ?User $user = null)` : Attacher un tag
  - `detachTag(Lead $lead, Tag $tag)` : Détacher un tag
  - `getTagsForLead(Lead $lead)` : Récupération des tags
  - `getPopularTags(?CallCenter $callCenter = null, int $limit = 10)` : Tags les plus utilisés

#### 3.4. Tags Système
- Créer des tags système au seeding :
  - `hot` : Lead chaud (priorité)
  - `cold` : Lead froid
  - `qualified` : Lead qualifié
  - `unqualified` : Lead non qualifié
  - `vip` : Client VIP
  - `do-not-call` : Ne pas appeler
- Protection contre la suppression des tags système

#### 3.5. Interface Utilisateur
- Ajouter une section "Tags" dans la page de détail du lead :
  - Affichage des tags existants (badges colorés)
  - Autocomplete pour ajouter des tags
  - Création rapide de nouveaux tags
  - Suppression de tags (si permissions)
- Filtres par tags dans les listes de leads :
  - Multi-sélection de tags
  - Combinaison AND/OR
- Nuage de tags dans les statistiques

#### 3.6. Catégories (Groupes de Tags)
- Créer le modèle `Category` :
  - `name` : Nom de la catégorie
  - `description` : Description
  - Relation `tags()` : Tags appartenant à la catégorie
- Exemples de catégories :
  - "Priorité" : hot, cold, vip
  - "Qualification" : qualified, unqualified
  - "Statut" : do-not-call, callback-required

#### 3.7. Tests
- Tests unitaires pour `TagService`
- Tests feature pour l'ajout/suppression de tags
- Tests de filtrage par tags
- Tests de permissions

---

### 4. Système d'Alertes Configurables

#### 4.1. Modèle et Migration
- Créer le modèle `Alert` avec :
  - `user_id` : Utilisateur propriétaire
  - `name` : Nom de l'alerte
  - `type` : Type d'alerte (lead_stale, agent_performance, conversion_rate, etc.)
  - `conditions` : Conditions de déclenchement (JSON)
  - `threshold` : Seuil de déclenchement
  - `is_active` : Statut actif/inactif
  - `notification_channels` : Canaux (email, in_app, sms) (JSON)
  - `last_triggered_at` : Dernier déclenchement
- Créer la migration avec index

#### 4.2. Types d'Alertes
- **Lead Stale** : Lead non traité depuis X heures
- **Agent Performance** : Agent sous-performant (taux de conversion < X%)
- **Conversion Rate** : Taux de conversion global < X%
- **High Volume** : Volume de leads > X par heure
- **Low Volume** : Volume de leads < X par heure
- **Form Performance** : Formulaire avec taux de conversion < X%
- **SMTP Failure** : Échec d'envoi d'email

#### 4.3. Service d'Alertes
- Créer `AlertService` avec :
  - `createAlert(User $user, string $type, array $conditions, float $threshold, array $channels)` : Création
  - `checkAlerts(?User $user = null)` : Vérification de toutes les alertes
  - `triggerAlert(Alert $alert, array $data)` : Déclenchement d'une alerte
  - `evaluateConditions(Alert $alert)` : Évaluation des conditions
- Conditions supportées :
  - Comparaisons numériques (>, <, =, >=, <=)
  - Comparaisons de dates
  - Agrégations (count, sum, avg)

#### 4.4. Commande de Vérification
- Créer la commande Artisan `alerts:check` :
  - Vérifie toutes les alertes actives
  - Déclenche les alertes si conditions remplies
  - Envoie les notifications selon les canaux configurés
  - Évite les doublons (cooldown de 1h par défaut)
- Programmer dans `app/Console/Kernel.php` (toutes les 15 minutes)

#### 4.5. Interface de Configuration
- Créer la page Volt `settings.alerts` :
  - Liste des alertes de l'utilisateur
  - Formulaire de création avec :
    - Sélection du type d'alerte
    - Configuration des conditions
    - Définition du seuil
    - Sélection des canaux de notification
  - Édition/suppression des alertes
  - Historique des déclenchements

#### 4.6. Notifications Multi-Canaux
- Support des canaux :
  - **Email** : Utiliser les notifications Laravel existantes
  - **In-App** : Notification dans l'interface
  - **SMS** : Intégration avec service SMS (optionnel, Sprint futur)
- Template de notification personnalisable par type d'alerte

#### 4.7. Alertes Système (Super Admin)
- Alertes système non modifiables par les utilisateurs :
  - Échecs SMTP critiques
  - Problèmes de queue
  - Erreurs système
- Affichage dans le dashboard admin

#### 4.8. Tests
- Tests unitaires pour `AlertService`
- Tests feature pour la création/gestion des alertes
- Tests de la commande de vérification
- Tests de déclenchement avec différents scénarios

---

## 📦 Livrables

### Fonctionnalités
* ✅ **Calendrier de planification** opérationnel avec rappels automatiques
* ✅ **Système de scoring** automatique des leads avec priorisation
* ✅ **Tags et catégories** pour organiser les leads
* ✅ **Système d'alertes** configurable par utilisateur

### Code
* Modèles : `LeadReminder`, `Tag`, `Category`, `Alert`
* Services : `ReminderService`, `LeadScoringService`, `TagService`, `AlertService`
* Composants Livewire : `ReminderCalendar`, `LeadTags`, `AlertSettings`
* Commandes Artisan : `reminders:notify`, `alerts:check`
* Migrations pour toutes les nouvelles tables
* Configuration : `config/lead-scoring.php`
* Tests complets (unitaires + feature)

### Documentation
* Guide utilisateur pour le calendrier et les rappels
* Documentation du système de scoring
* Guide de configuration des alertes

---

## 🧪 Critères d'Acceptation

### Calendrier et Planification
- [ ] Un rappel peut être planifié pour un lead
- [ ] Le calendrier affiche tous les rappels à venir
- [ ] Les notifications sont envoyées avant les rappels
- [ ] Les rappels peuvent être complétés ou annulés
- [ ] L'export iCal fonctionne (optionnel)

### Scoring des Leads
- [ ] Le score est calculé automatiquement à la création
- [ ] Le score est mis à jour lors des changements importants
- [ ] Le score est affiché dans les listes et détails
- [ ] Le tri par score fonctionne
- [ ] La distribution basée sur le score fonctionne

### Tags et Catégories
- [ ] Des tags peuvent être ajoutés/supprimés sur un lead
- [ ] Les tags sont affichés avec leurs couleurs
- [ ] Le filtrage par tags fonctionne
- [ ] Les tags système sont protégés
- [ ] Les catégories organisent les tags

### Alertes Configurables
- [ ] Un utilisateur peut créer des alertes personnalisées
- [ ] Les alertes sont vérifiées automatiquement
- [ ] Les notifications sont envoyées selon les canaux configurés
- [ ] L'historique des déclenchements est consultable
- [ ] Les alertes système fonctionnent pour les admins

---

## 🔗 Dépendances

- **Sprint 7** : Le système de notes doit être fonctionnel pour le scoring
- **Infrastructure** : Queue Laravel pour les notifications
- **Frontend** : Bibliothèque de calendrier (FullCalendar ou équivalent)

---

## 📊 Estimation

| Tâche | Complexité | Temps estimé |
|-------|-----------|--------------|
| Calendrier et Planification | Haute | 5 jours |
| Système de Scoring | Moyenne | 4 jours |
| Tags et Catégories | Moyenne | 3 jours |
| Système d'Alertes | Haute | 5 jours |
| Tests et Documentation | Moyenne | 2 jours |
| **Total** | | **19 jours** |

---

## 🚀 Prochaines Étapes

Après ce sprint, les **Sprints futurs** pourront inclure :
- Intégration téléphonie (VoIP)
- Analytics avancés (funnel, cohortes)
- Multi-langue (i18n)
- API webhooks entrants
- Tableau de bord temps réel avancé
```

