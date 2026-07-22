# Intégration Node Agent

`NodeAgentClient` centralise mTLS, CA, timeouts et absence de redirects. L’ajout d’un Node valide URL/port/CIDR, appelle `/v1/health` et `/v1/resources`, compare les deux `node_id`, puis enregistre l’identité. Toute divergence future désactive le scheduling et ouvre un incident critique.

Endpoints intégrés : health, resources, liste/détail deployments, create, start, stop, restart, upgrade, admin-reset, soft delete, purge-token/purge, logs et opérations. `/metrics` n’est pas utilisé comme API Control Plane.

Les domaines personnalisés nécessitent la capability Agent `hostname_aliases`. Le Control Plane conserve `hostname` sous `CENTRALPANEL_DOMAIN_SUFFIX`, valide le CNAME du client, puis transmet le domaine public dans `aliases`. Un Project personnalisé n’est planifié que sur un Node annonçant cette capability.

Chaque POST/DELETE inclut `Idempotency-Key`, `X-Correlation-ID` et `X-Request-Timestamp`. CentralCloud persiste méthode, chemin, hash, payload chiffré et clés avant l’appel. Un timeout incertain conserve la même requête. Après `202` certain, les secrets du payload sont effacés et seul l’Agent Operation est pollé.

Les codes Agent sont conservés pour l’admin mais transformés en messages client. Les logs sont récupérés temporairement avec `limit`/`cursor`, soumis à authorization et jamais archivés intégralement dans MySQL.
