# Runbooks opérations

## Ajouter un Node

Installer et sécuriser l’Agent, autoriser le SAN du certificat Control Plane et sa source réseau, puis saisir l’URL HTTPS dans Admin > Nodes. Laisser le scheduling désactivé jusqu’à validation des capacités et sauvegardes.

Pour accepter les domaines personnalisés, déployer d’abord une version Agent annonçant `hostname_aliases`, vérifier que Traefik possède un resolver de certificats fonctionnel, puis tester un CNAME de bout en bout. Les Nodes sans cette capability continuent à recevoir les projets sur domaine CentralCloud mais sont exclus du scheduling des projets personnalisés.

## Node offline ou identité changée

Consulter l’incident et le correlation ID, vérifier le DNS, le certificat HTTPS
Traefik et le jeton du Node, puis relancer health. Ne jamais réactiver le
scheduling si `node_id` diffère. Aucun déplacement inter-Node automatique
n’existe en V1.

## Deployment failed

Consulter l’Agent Operation, le code nettoyé et les logs temporaires. Corriger capacité/image/Node, puis relancer une nouvelle opération métier. Un timeout réseau réutilise la requête persistée; ne pas créer manuellement une nouvelle clé.

## Suspension et purge

STOP conserve le conteneur. Soft delete retire conteneur/réseaux mais conserve PostgreSQL et storage. PURGE détruit définitivement données, base et état Agent; elle reste manuelle, fortement confirmée et utilise purge-token côté backend uniquement.

## Upgrade

Publier une `panel_version` officielle épinglée par digest, puis lancer un upgrade individuel. L’Agent prend en charge dump, migrations, healthcheck et rollback. Aucun mass upgrade automatique n’est prévu.
