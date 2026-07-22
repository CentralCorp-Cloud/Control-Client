# Implémenter les domaines personnalisés dans CentralCloud Node Agent

Tu travailles dans le dépôt du Node Agent CentralCloud en Go. Implémente le support rétrocompatible des alias de hostname demandé par le Control Plane. Ne modifie pas la règle existante qui impose que le champ principal `hostname` soit égal ou sous-domaine de `traefik.domain_suffix`.

## Contrat API attendu

- Étends le payload de `POST /v1/deployments` avec `aliases`, tableau JSON optionnel de chaînes. Une requête historique sans ce champ doit continuer à fonctionner.
- Pour cette première version, accepte zéro ou un alias.
- Un alias doit être un hostname DNS ASCII normalisé en minuscules : longueur totale maximale 253, labels de 1 à 63 caractères, lettres ASCII/chiffres/tirets, aucun tiret en début ou fin de label.
- Refuse IP, port, URL, wildcard, userinfo, chemin, query string, fragment, doublon du hostname principal et tableau contenant des doublons.
- L’alias n’est pas limité à `traefik.domain_suffix`. La sécurité repose sur l’API Agent privée authentifiée et sur la validation de propriété CNAME effectuée par le Control Plane.
- Persiste les alias dans SQLite avec le Deployment. Ils font partie du contenu comparé lors d’un rejeu idempotent : même clé et mêmes données réussissent, même clé avec des alias différents produit le conflit prévu par le contrat existant.
- Retourne `aliases` dans les réponses de création, détail et liste des Deployments. Retourne toujours un tableau, y compris vide.
- Expose `hostname_aliases` dans un tableau `capabilities` de `GET /v1/health`.

Exemple de création :

```json
{
  "deployment_id": "123e4567-e89b-42d3-a456-426614174000",
  "project_id": "123e4567-e89b-42d3-a456-426614174001",
  "hostname": "panel-a1b2c3d4.panels.centralcloud.fr",
  "aliases": ["panel.example.com"],
  "image": "ghcr.io/centralcorp/centralpanel@sha256:...",
  "environment": {},
  "resources": {"memory_bytes": 536870912, "cpu_limit": 0.5},
  "database": {"database_name": "panel_a1b2_db", "username": "panel_a1b2_user"},
  "healthcheck": {"path": "/up", "timeout_seconds": 60},
  "bootstrap": {
    "admin_name": "Alice",
    "admin_email": "alice@example.com",
    "admin_password": "secret transmis par le Control Plane",
    "internal_secret": "secret transmis par le Control Plane"
  }
}
```

## Routage Traefik et TLS

- Garde le hostname canonique dans la règle du routeur et ajoute l’alias avec une règle équivalente à `Host(`canonique`) || Host(`alias`)`.
- Construis les labels sans concaténation non validée et avec l’échappement attendu par Docker/Traefik.
- Utilise l’entrypoint et le `certificate_resolver` existants. Le resolver doit demander un certificat couvrant le hostname canonique et l’alias à partir de la règle du routeur.
- Un create sans alias doit produire exactement le comportement actuel.
- Les opérations start, stop, restart, upgrade, soft delete et purge doivent conserver ou supprimer les alias avec le Deployment, sans créer de ressource orpheline.
- Le Control Plane vérifie le CNAME avant l’appel Agent ; l’Agent ne doit pas effectuer de requête DNS et ne doit pas accepter de nouveau secret pour cette fonctionnalité.

## Migration et compatibilité

- Ajoute une migration SQLite rétrocompatible pour stocker les alias des Deployments existants, initialisés à une liste vide.
- La migration doit être transactionnelle et idempotente selon les conventions du dépôt.
- Les Deployments existants doivent continuer à être listés, synchronisés et opérés sans modification de leur hostname.
- Incrémente la version Agent selon les conventions du dépôt, sans faire dépendre le Control Plane d’un numéro de version : la détection utilise uniquement la capability `hostname_aliases`.
- Mets à jour la documentation API, déploiement, sécurité et l’exemple de configuration si les labels Traefik ou le resolver exigent une précision opératoire.

## Tests d’acceptation

- Création sans `aliases` et avec `aliases: []` : succès et réponse contenant une liste vide.
- Création avec un alias valide : persistance, réponse API et règle Traefik contenant les deux hosts.
- Rejet des alias invalides : URL, IP, wildcard, port, label trop long, domaine trop long, caractères Unicode non punycodés, hostname canonique et plus d’un alias.
- Rejeu idempotent identique : succès ; rejeu avec alias différent : conflit.
- Liste et détail après redémarrage Agent : alias toujours présent.
- TLS/router : labels attendus avec le resolver configuré et aucune régression sans resolver.
- Lifecycle complet : start/stop/restart/upgrade conservent l’alias ; purge supprime toutes les données associées.
- `GET /v1/health` annonce `hostname_aliases`.
- Exécute les tests unitaires, d’intégration, le race detector et les outils de format/lint prévus par le dépôt.

Livre le code, les migrations, la documentation et les tests. Signale explicitement toute incompatibilité avec le schéma SQLite ou le générateur de labels Traefik actuel au lieu de contourner leurs invariants.
