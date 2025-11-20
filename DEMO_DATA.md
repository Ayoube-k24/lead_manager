# Données de Démonstration

Ce document explique comment utiliser les données de démonstration pour tester la plateforme de gestion de leads.

## Commandes Disponibles

### Créer toutes les données de démonstration

```bash
php artisan app:seed-demo-data
```

Cette commande crée :
- ✅ Les rôles et utilisateurs de base (si nécessaire)
- ✅ 3 centres d'appels (Paris, Lyon, Marseille)
- ✅ Des propriétaires pour chaque centre d'appels
- ✅ 12 agents répartis dans les centres d'appels
- ✅ Des formulaires supplémentaires
- ✅ Plus de 200 leads avec différents statuts

### Réinitialiser complètement la base de données

```bash
php artisan app:seed-demo-data --fresh
```

Cette commande supprime toutes les tables, réexécute les migrations, puis crée toutes les données de démonstration.

## Comptes de Test

### Super Admin
- **Email:** `admin@leadmanager.com`
- **Mot de passe:** `password`

### Propriétaires de Centres d'Appels
- **Paris:** `owner.paris@demo.com` / `password`
- **Lyon:** `owner.lyon@demo.com` / `password`
- **Marseille:** `owner.marseille@demo.com` / `password`

### Agents
- **Paris:** `agent.paris1@demo.com` à `agent.paris5@demo.com` / `password`
- **Lyon:** `agent.lyon1@demo.com` à `agent.lyon3@demo.com` / `password`
- **Marseille:** `agent.marseille1@demo.com` à `agent.marseille4@demo.com` / `password`

## Données Créées

### Centres d'Appels
- **Centre d'Appels Paris** - Distribution: Round Robin
- **Centre d'Appels Lyon** - Distribution: Weighted
- **Centre d'Appels Marseille** - Distribution: Round Robin

### Formulaires
Les formulaires de base sont créés, plus :
- Formulaire de génération de leads (Landing Page)
- Formulaire de demande de rappel

### Leads
Pour chaque centre d'appels, environ 75 leads sont créés avec la répartition suivante :
- **70%** - Leads confirmés (avec email confirmé et appel effectué)
- **15%** - Leads rejetés (avec commentaires)
- **5%** - Leads en attente de confirmation email
- **5%** - Leads email confirmé mais pas encore appelé
- **5%** - Leads en attente d'appel

Les leads sont répartis sur les 60 derniers jours pour des statistiques réalistes.

## Utilisation

1. **Pour une nouvelle installation:**
   ```bash
   php artisan migrate
   php artisan app:seed-demo-data
   ```

2. **Pour réinitialiser complètement:**
   ```bash
   php artisan app:seed-demo-data --fresh
   ```

3. **Pour ajouter des données sans réinitialiser:**
   ```bash
   php artisan app:seed-demo-data
   ```
   (Les données existantes ne seront pas dupliquées grâce à `firstOrCreate`)

## Notes

- Les données sont créées de manière idempotente : si elles existent déjà, elles ne seront pas dupliquées
- Les leads sont assignés aléatoirement aux agents de chaque centre d'appels
- Les dates sont réparties sur les 60 derniers jours pour des statistiques réalistes
- Tous les mots de passe sont `password` pour faciliter les tests

