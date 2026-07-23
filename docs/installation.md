# Installation

Prérequis : PHP 8.3+, Composer 2, Node.js 20.19+ ou 22+, MySQL 8/MariaDB 10.6+, extensions PDO, OpenSSL, cURL, mbstring, intl et fileinfo.

1. Copier `.env.example` vers `.env`, configurer MySQL, mail et `APP_URL`.
2. Installer avec `composer install --no-dev --optimize-autoloader` en production.
3. Exécuter `php artisan key:generate`, puis conserver `APP_KEY` durablement : elle chiffre les enveloppes bootstrap temporaires.
4. Construire les assets avec `npm ci && npm run build`.
5. Exécuter `php artisan migrate --force`.
6. Créer le premier Super Admin par un seeder contrôlé localement ou une commande de maintenance hors dépôt; ne pas laisser les variables de développement après usage.
7. Configurer Stripe, l’image CentralPanel par digest, le manifeste Agent signé
   et le scheduler. Les chemins mTLS ne sont nécessaires que pour des Nodes
   historiques.

Les répertoires `storage/` et `bootstrap/cache/` doivent être inscriptibles. `APP_DEBUG=false`, cookies sécurisés et HTTPS sont obligatoires en production.
