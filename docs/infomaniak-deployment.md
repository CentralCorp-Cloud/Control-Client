# Déploiement Infomaniak

Le document root du site doit pointer vers `CentralCloud/public`, jamais vers la racine du projet. Sélectionner PHP 8.3 ou supérieur et une base MariaDB dédiée. Déployer `vendor/` et `public/build/` si Composer ou Node ne sont pas disponibles durant le déploiement.

## WebCron mutualisé

Le planificateur mutualisé fonctionne au minimum toutes les 15 minutes. Configurer une tâche HTTPS vers `/_system/webcron`, protégée par HTTP Basic avec `CENTRALCLOUD_CRON_USERNAME` et un secret aléatoire long dans `CENTRALCLOUD_CRON_SECRET`. La route possède rate limit et verrou distribué database, puis lance `centralcloud:tick` avec des lots bornés.

Lorsque CronJob SSH est disponible, préférer :

```cron
* * * * * cd /chemin/centralcloud && php artisan schedule:run >> /dev/null 2>&1
```

La queue utilise la base. Le tick appelle `queue:work --stop-when-empty --max-time=30`; Supervisor et Redis ne sont pas requis.

Installer les certificats client, clé privée et CA hors du document root, permissions minimales, puis renseigner leurs chemins absolus. Vérifier que l’hébergement possède une sortie réseau vers les ports Agent autorisés. Une IP de sortie fixe, un VPN ou un réseau privé reste préférable.
