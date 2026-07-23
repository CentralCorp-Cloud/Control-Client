# CentralCloud

CentralCloud est le site client, le Control Plane et le back-office d’exploitation du service d’hébergement managé CentralPanel. Laravel décide; le Node Agent exécute; CentralPanel est l’unique produit hébergé.

## Stack

- Laravel 13, PHP 8.3+, Blade, Fortify et Cashier Stripe
- Tailwind CSS 4, Alpine.js et Vite
- MySQL/MariaDB, sessions/cache/queue database
- HTTPS + jeton Bearer propre à chaque CentralCloud Node Agent
- Scheduler CLI ou WebCron mutualisé, sans Redis ni worker permanent

## Démarrage local

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
npm run build
php artisan migrate --seed
php artisan serve
```

Pour créer le Super Admin local, définir `CENTRALCLOUD_DEV_ADMIN_EMAIL` et `CENTRALCLOUD_DEV_ADMIN_PASSWORD` avant le seeding. Ces variables ne doivent jamais servir de credentials de production.

## Vérifications

```bash
vendor/bin/pint --test
php artisan test
npm run build
php artisan route:list
```

## Documentation

- [Architecture](docs/architecture.md)
- [Installation](docs/installation.md)
- [Déploiement Infomaniak](docs/infomaniak-deployment.md)
- [Intégration Agent](docs/agent-integration.md)
- [Facturation](docs/billing.md)
- [Sécurité](docs/security.md)
- [Guide administrateur](docs/admin-guide.md)
- [Runbooks opérations](docs/operations.md)
- [Installation automatisée des Nodes](docs/node-enrollment.md)

## Contrats partagés

Le dépôt voisin `specs/` est normatif pour l’enrôlement, la configuration Agent,
les manifests signés et le runtime CentralPanel. CentralPanel fournit
actuellement l’image OCI multi-architecture, le healthcheck `/up`, le stockage
`/app/storage` et les commandes managées attendues.
