# Sprint 7 : Intégrations et Notifications en Temps Réel

## 📋 Vue d'ensemble

**Durée estimée** : 2-3 semaines  
**Objectif principal** : Améliorer l'intégration avec les systèmes externes et enrichir l'expérience utilisateur avec des notifications en temps réel et des fonctionnalités de recherche avancée.

---

## 🎯 Objectifs

* Mise en place d'un **système de webhooks** pour intégrer la plateforme avec des systèmes externes (CRM, outils d'analyse).
* Développement de **notifications en temps réel** dans l'interface utilisateur (Livewire/Flux).
* Implémentation d'un **système de notes et commentaires** sur les leads pour améliorer la traçabilité.
* Création d'une **recherche avancée** avec filtres multiples pour faciliter la gestion des leads.

---

## 📝 Tâches détaillées

### 1. Système de Webhooks

#### 1.1. Modèle et Migration
- Créer le modèle `Webhook` avec les champs :
  - `name` : Nom du webhook
  - `url` : URL de destination
  - `secret` : Secret pour signer les requêtes
  - `events` : Événements déclencheurs (JSON)
  - `is_active` : Statut actif/inactif
  - `form_id` : Association optionnelle à un formulaire
  - `call_center_id` : Association optionnelle à un centre d'appels
  - `user_id` : Propriétaire du webhook
- Créer la migration correspondante
- Créer le factory et seeder pour les tests

#### 1.2. Service de Gestion des Webhooks
- Créer `WebhookService` avec les méthodes :
  - `dispatch(string $event, array $payload, ?Form $form = null, ?CallCenter $callCenter = null)` : Envoi des webhooks
  - `signPayload(array $payload, string $secret)` : Signature des payloads
  - `validateWebhook(Webhook $webhook, array $payload)` : Validation de la signature
- Implémenter la gestion des retry en cas d'échec (3 tentatives avec backoff exponentiel)
- Logger tous les envois de webhooks (succès/échecs)

#### 1.3. Événements Déclencheurs
- Créer les événements Laravel :
  - `LeadCreated` : Lorsqu'un lead est créé
  - `LeadEmailConfirmed` : Lorsqu'un lead confirme son email
  - `LeadAssigned` : Lorsqu'un lead est attribué à un agent
  - `LeadStatusUpdated` : Lorsqu'un statut de lead change
  - `LeadConverted` : Lorsqu'un lead est converti en client
- Enregistrer les listeners dans `EventServiceProvider`
- Intégrer avec `LeadObserver` existant

#### 1.4. Interface de Gestion (Super Admin / Propriétaire)
- Créer la page Volt `admin.webhooks` et `owner.webhooks`
- Liste des webhooks avec filtres (actif/inactif, par formulaire, par centre)
- Formulaire de création/édition avec :
  - Nom et URL
  - Sélection des événements à écouter
  - Association à un formulaire ou centre d'appels
  - Génération automatique du secret
- Test de webhook depuis l'interface
- Historique des envois (derniers 50 webhooks envoyés)
- Statistiques (taux de succès, temps de réponse)

#### 1.5. Endpoints API
- `GET /api/webhooks` : Liste des webhooks
- `POST /api/webhooks` : Création d'un webhook
- `PUT /api/webhooks/{id}` : Mise à jour
- `DELETE /api/webhooks/{id}` : Suppression
- `POST /api/webhooks/{id}/test` : Test d'envoi

#### 1.6. Tests
- Tests unitaires pour `WebhookService`
- Tests feature pour la création/gestion des webhooks
- Tests d'intégration pour l'envoi des webhooks
- Tests de retry en cas d'échec

---

### 2. Notifications en Temps Réel

#### 2.1. Système de Notifications Livewire
- Créer le composant Livewire `NotificationsBell` :
  - Badge avec nombre de notifications non lues
  - Dropdown avec liste des notifications
  - Marquer comme lu au clic
  - Auto-refresh toutes les 5 secondes
- Utiliser les notifications Laravel existantes (table `notifications`)
- Ajouter le composant dans le layout principal

#### 2.2. Notifications Push avec Livewire Events
- Créer les événements Livewire :
  - `LeadAssignedNotification` : Notification quand un lead est attribué
  - `LeadStatusChangedNotification` : Notification de changement de statut
  - `NewLeadCreatedNotification` : Nouveau lead créé (pour superviseurs/propriétaires)
- Utiliser `$this->dispatch()` pour émettre les événements
- Écouter les événements dans le composant `NotificationsBell`

#### 2.3. Toast Notifications (Flux UI)
- Créer un composant Flux pour les toasts
- Afficher des notifications toast pour :
  - Attribution de lead
  - Changement de statut important
  - Erreurs de validation
  - Actions réussies (sauvegarde, suppression)
- Auto-dismiss après 5 secondes
- Support des types : success, error, warning, info

#### 4.4. Mise à Jour Automatique des Tableaux de Bord
- Utiliser `wire:poll` sur les composants de dashboard
- Rafraîchir automatiquement :
  - Liste des leads (toutes les 30 secondes)
  - Statistiques (toutes les 60 secondes)
  - Notifications (toutes les 5 secondes)
- Indicateur visuel de mise à jour en cours

#### 2.5. Tests
- Tests pour le composant `NotificationsBell`
- Tests pour les événements Livewire
- Tests pour les toasts Flux

---

### 3. Système de Notes et Commentaires

#### 3.1. Modèle et Migration
- Créer le modèle `LeadNote` avec les champs :
  - `lead_id` : Lead concerné
  - `user_id` : Auteur de la note
  - `content` : Contenu de la note
  - `is_private` : Note privée (visible uniquement par l'auteur et les admins)
  - `type` : Type de note (comment, call_log, internal_note)
  - `attachments` : Pièces jointes (JSON)
- Créer la migration avec index sur `lead_id` et `user_id`
- Créer le factory pour les tests

#### 3.2. Relations et Scopes
- Ajouter la relation `notes()` dans le modèle `Lead`
- Ajouter la relation `leadNotes()` dans le modèle `User`
- Créer des scopes :
  - `public()` : Notes publiques uniquement
  - `private()` : Notes privées
  - `byType(string $type)` : Filtrer par type

#### 3.3. Service de Gestion
- Créer `LeadNoteService` avec :
  - `createNote(Lead $lead, User $user, string $content, bool $isPrivate = false, ?string $type = null)` : Création
  - `updateNote(LeadNote $note, string $content)` : Mise à jour
  - `deleteNote(LeadNote $note)` : Suppression
  - `getNotesForLead(Lead $lead, ?User $user = null)` : Récupération avec filtrage des notes privées

#### 3.4. Interface Utilisateur
- Ajouter une section "Notes" dans la page de détail d'un lead (`agent.leads.show`, `owner.leads.show`)
- Formulaire d'ajout de note avec :
  - Champ texte riche (textarea)
  - Checkbox "Note privée"
  - Sélection du type de note
  - Upload de pièces jointes (optionnel)
- Affichage chronologique des notes avec :
  - Auteur et date
  - Badge pour les notes privées
  - Icône selon le type
  - Actions (éditer/supprimer) si auteur ou admin

#### 3.5. Historique des Actions
- Créer une timeline dans la page de détail du lead
- Afficher chronologiquement :
  - Création du lead
  - Confirmation email
  - Attribution à un agent
  - Changements de statut
  - Notes ajoutées
  - Appels effectués
- Utiliser des icônes Flux pour chaque type d'action

#### 3.6. Audit et Permissions
- Logger toutes les créations/modifications/suppressions de notes dans `ActivityLog`
- Vérifier les permissions :
  - Agents : peuvent voir leurs notes privées + toutes les notes publiques
  - Superviseurs : peuvent voir toutes les notes de leurs agents
  - Propriétaires : peuvent voir toutes les notes de leur centre
  - Super Admin : accès complet

#### 3.7. Tests
- Tests unitaires pour `LeadNoteService`
- Tests feature pour la création/édition/suppression
- Tests de permissions (visibilité des notes privées)
- Tests d'audit

---

### 4. Recherche Avancée et Filtres

#### 4.1. Service de Recherche
- Créer `LeadSearchService` avec :
  - `search(string $query, array $filters = [])` : Recherche full-text
  - `buildQuery(array $filters)` : Construction de la requête Eloquent
  - `getAvailableFilters()` : Liste des filtres disponibles
- Support de la recherche sur :
  - Email
  - Nom (dans `data`)
  - Téléphone (dans `data`)
  - Tous les champs personnalisés du formulaire
- Utiliser `whereLike` pour la recherche partielle

#### 4.2. Filtres Disponibles
- Statut (multi-sélection)
- Date de création (range)
- Date de confirmation email (range)
- Agent assigné
- Centre d'appels
- Formulaire source
- Statut de confirmation email
- Date d'appel (range)
- Notes présentes/absentes

#### 4.3. Interface de Recherche
- Créer un composant Livewire `LeadSearch` :
  - Champ de recherche full-text
  - Panneau de filtres dépliable (Flux UI)
  - Résultats en temps réel (debounce 300ms)
  - Pagination des résultats
  - Export des résultats filtrés (CSV)
- Intégrer dans les pages :
  - `admin.leads`
  - `owner.leads`
  - `supervisor.leads`
  - `agent.leads`

#### 4.4. Sauvegarde de Recherches
- Créer le modèle `SavedSearch` :
  - `user_id` : Propriétaire
  - `name` : Nom de la recherche
  - `filters` : Filtres sauvegardés (JSON)
  - `query` : Requête de recherche
- Interface pour sauvegarder/charger les recherches favorites
- Partage de recherches entre utilisateurs (optionnel)

#### 4.5. Performance
- Indexer les colonnes fréquemment recherchées :
  - `email` (index unique)
  - `status`
  - `created_at`
  - `email_confirmed_at`
  - `assigned_to`
- Utiliser `eager loading` pour éviter les N+1 queries
- Cache des résultats de recherche (5 minutes)

#### 4.6. Tests
- Tests unitaires pour `LeadSearchService`
- Tests feature pour la recherche avec différents filtres
- Tests de performance (recherche sur 1000+ leads)

---

## 📦 Livrables

### Fonctionnalités
* ✅ **Système de webhooks** opérationnel avec gestion complète
* ✅ **Notifications en temps réel** dans l'interface (badge, toasts, auto-refresh)
* ✅ **Système de notes** sur les leads avec historique complet
* ✅ **Recherche avancée** avec filtres multiples et sauvegarde

### Code
* Modèles : `Webhook`, `LeadNote`, `SavedSearch`
* Services : `WebhookService`, `LeadNoteService`, `LeadSearchService`
* Composants Livewire : `NotificationsBell`, `LeadSearch`
* Événements Laravel : `LeadCreated`, `LeadEmailConfirmed`, `LeadAssigned`, etc.
* Migrations pour toutes les nouvelles tables
* Tests complets (unitaires + feature)

### Documentation
* Documentation API pour les webhooks
* Guide utilisateur pour les notes et commentaires
* Guide de configuration des webhooks

---

## 🧪 Critères d'Acceptation

### Webhooks
- [ ] Un webhook peut être créé avec une URL et des événements sélectionnés
- [ ] Les webhooks sont envoyés automatiquement lors des événements configurés
- [ ] Les payloads sont signés avec le secret
- [ ] Les retry fonctionnent en cas d'échec (3 tentatives)
- [ ] L'historique des envois est consultable
- [ ] Les tests de webhook fonctionnent depuis l'interface

### Notifications Temps Réel
- [ ] Le badge de notifications affiche le nombre de notifications non lues
- [ ] Les notifications apparaissent automatiquement lors d'événements
- [ ] Les toasts s'affichent pour les actions importantes
- [ ] Les tableaux de bord se rafraîchissent automatiquement
- [ ] Les notifications peuvent être marquées comme lues

### Notes et Commentaires
- [ ] Les notes peuvent être ajoutées à un lead
- [ ] Les notes privées ne sont visibles que par l'auteur et les admins
- [ ] L'historique complet des actions est affiché
- [ ] Les permissions sont respectées (agents/superviseurs/propriétaires)
- [ ] Toutes les actions sont auditées

### Recherche Avancée
- [ ] La recherche full-text fonctionne sur tous les champs pertinents
- [ ] Tous les filtres sont fonctionnels
- [ ] Les recherches peuvent être sauvegardées
- [ ] Les résultats sont paginés
- [ ] L'export CSV fonctionne avec les filtres appliqués
- [ ] Les performances sont acceptables (< 500ms pour 1000 leads)

---

## 🔗 Dépendances

- **Sprint 6** : Le système d'audit doit être fonctionnel
- **Infrastructure** : Queue Laravel configurée pour les webhooks
- **Frontend** : Flux UI installé et configuré

---

## 📊 Estimation

| Tâche | Complexité | Temps estimé |
|-------|-----------|--------------|
| Système de Webhooks | Haute | 5 jours |
| Notifications Temps Réel | Moyenne | 3 jours |
| Notes et Commentaires | Moyenne | 4 jours |
| Recherche Avancée | Haute | 4 jours |
| Tests et Documentation | Moyenne | 2 jours |
| **Total** | | **18 jours** |

---

## 🚀 Prochaines Étapes

Après ce sprint, le **Sprint 8** se concentrera sur :
- Calendrier et planification de rappels
- Scoring automatique des leads
- Système de tags et catégories
- Alertes configurables
