# Architecture

```mermaid
flowchart TD
    U[Client HTTPS] --> C[CentralCloud Laravel + Blade]
    A[Administrateur] --> C
    S[Stripe] -->|webhooks signés| C
    C --> M[(MySQL / MariaDB métier)]
    C -->|HTTPS + mTLS| N1[Node Agent A]
    C -->|HTTPS + mTLS| N2[Node Agent B]
    N1 --> D1[Docker + Traefik]
    N1 --> P1[(PostgreSQL CentralPanel)]
    N1 --> Q1[(SQLite Agent)]
    N2 --> D2[Docker + Traefik]
    D1 --> CP1[CentralPanel officiel]
    D2 --> CP2[CentralPanel officiel]
```

MySQL contient les utilisateurs, Plans, abonnements, Projects, Deployments, Nodes, opérations, événements Stripe, notifications, incidents, audits et paramètres non secrets. SQLite appartient exclusivement à chaque Agent. PostgreSQL et `/app/storage` appartiennent aux CentralPanel présents sur le Node.

`Project` est le service commercial appartenant à un `User`. `Deployment` est sa réalité technique sur un `Node`. `AgentOperation` représente une mutation asynchrone. Le statut commercial Stripe, le statut métier Project et l’état technique Agent ne sont jamais confondus.

Le navigateur n’accède jamais à un endpoint Agent. CentralCloud ne gère ni Docker, ni PostgreSQL, ni Traefik, ni les secrets locaux : il sélectionne un Node, construit une spécification autorisée et demande à l’Agent de l’exécuter.
