# Guide Docker - Lead Manager

Ce guide explique comment utiliser Docker pour déployer l'application Lead Manager.

## Prérequis

- Docker (version 20.10 ou supérieure)
- Docker Compose (version 2.0 ou supérieure)

## Structure des fichiers Docker

- `Dockerfile` : Image Docker multi-stage pour la production
- `docker-compose.yml` : Configuration Docker Compose avec services
- `docker/nginx.conf` : Configuration Nginx
- `docker/php.ini` : Configuration PHP personnalisée
- `docker/supervisord.conf` : Configuration Supervisor pour gérer les processus
- `docker/entrypoint.sh` : Script d'initialisation du conteneur

## Démarrage rapide

### 1. Configuration de l'environnement

Créez un fichier `.env` à la racine du projet avec les variables suivantes :

```env
APP_NAME="Lead Manager"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=lead_manager
DB_USERNAME=lead_manager
DB_PASSWORD=your_secure_password
DB_ROOT_PASSWORD=your_root_password

REDIS_HOST=redis
REDIS_PORT=6379

QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

### 2. Construction et démarrage

```bash
# Construire les images
docker-compose build

# Démarrer les services
docker-compose up -d

# Voir les logs
docker-compose logs -f

# Arrêter les services
docker-compose down
```

### 3. Initialisation de l'application

```bash
# Exécuter les migrations
docker-compose exec app php artisan migrate

# Créer les données de base
docker-compose exec app php artisan db:seed

# Créer les données de démonstration
docker-compose exec app php artisan db:seed --class=LeadDemoSeeder

# Générer la clé d'application (si nécessaire)
docker-compose exec app php artisan key:generate
```

## Commandes utiles

### Accéder au conteneur

```bash
docker-compose exec app sh
```

### Exécuter des commandes Artisan

```bash
# Lister les routes
docker-compose exec app php artisan route:list

# Vider le cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan view:clear

# Optimiser l'application
docker-compose exec app php artisan optimize
```

### Gérer les queues

```bash
# Voir les jobs en attente
docker-compose exec app php artisan queue:work

# Redémarrer le worker de queue
docker-compose restart app
```

### Accéder à la base de données

```bash
# MySQL CLI
docker-compose exec db mysql -u lead_manager -p lead_manager

# Ou avec root
docker-compose exec db mysql -u root -p
```

### Voir les logs

```bash
# Logs de l'application
docker-compose logs app

# Logs de la base de données
docker-compose logs db

# Logs en temps réel
docker-compose logs -f app
```

## Architecture

L'application utilise une architecture multi-conteneurs :

- **app** : Application Laravel avec PHP-FPM, Nginx et Supervisor
- **db** : Base de données MySQL 8.0
- **redis** : Cache et queues Redis

### Ports exposés

- `8000` : Application web (Nginx)
- `3306` : MySQL (optionnel, pour accès externe)
- `6379` : Redis (optionnel, pour accès externe)

## Production

### Optimisations recommandées

1. **Variables d'environnement** : Utilisez des secrets Docker ou un gestionnaire de secrets
2. **Volumes persistants** : Les données de la base de données sont stockées dans un volume Docker
3. **SSL/TLS** : Configurez un reverse proxy (Nginx/Traefik) avec certificats SSL
4. **Backup** : Configurez des sauvegardes régulières de la base de données

### Exemple de configuration avec reverse proxy

```nginx
server {
    listen 80;
    server_name lead-manager.example.com;
    
    location / {
        proxy_pass http://localhost:8000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## Dépannage

### L'application ne démarre pas

```bash
# Vérifier les logs
docker-compose logs app

# Vérifier que les conteneurs sont en cours d'exécution
docker-compose ps

# Redémarrer les services
docker-compose restart
```

### Problèmes de permissions

```bash
# Corriger les permissions
docker-compose exec app chown -R www:www /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/storage
```

### Base de données non accessible

```bash
# Vérifier la connexion
docker-compose exec app php artisan tinker
# Puis dans tinker: DB::connection()->getPdo();
```

### Reconstruire l'image

```bash
# Reconstruire sans cache
docker-compose build --no-cache

# Redémarrer
docker-compose up -d
```

## Maintenance

### Mise à jour de l'application

```bash
# Pull les dernières modifications
git pull

# Reconstruire l'image
docker-compose build

# Redémarrer les services
docker-compose up -d

# Exécuter les migrations
docker-compose exec app php artisan migrate
```

### Sauvegarde de la base de données

```bash
# Créer une sauvegarde
docker-compose exec db mysqldump -u root -p lead_manager > backup.sql

# Restaurer une sauvegarde
docker-compose exec -T db mysql -u root -p lead_manager < backup.sql
```

## Sécurité

- Changez tous les mots de passe par défaut
- Utilisez des secrets Docker pour les informations sensibles
- Configurez un firewall pour limiter l'accès aux ports
- Activez HTTPS en production
- Configurez des sauvegardes automatiques


