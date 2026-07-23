# Guide administrateur

- Overview : comptes, Projects, santé Nodes, opérations et incidents.
- Utilisateurs : recherche, statut, rôle, vérification, Projects; les rôles critiques exigent SUPER_ADMIN.
- CentralPanels : propriétaire, Plan, Deployment, Node et opérations.
- Nodes : test HTTPS Bearer, identité Agent, ressources, refresh, scheduling et maintenance.
- Opérations : type, statut, durée, idempotency key, correlation ID et erreur nettoyée.
- Plans : prix futurs et ressources. Les champs techniques/Stripe utilisés restent immuables.
- Versions CentralPanel : publication des seules images officielles épinglées par digest et choix d’une version recommandée.
- Billing : abonnements, transactions et événements Stripe, sans donnée carte.
- Incidents : acknowledge/resolve avec audit.
- Settings : paramètres métier allowlistés; aucun secret ne peut être stocké ici.

SUPPORT lit les données de support, BILLING_ADMIN gère billing/Plans, INFRA_ADMIN gère Nodes/opérations, ADMIN couvre l’exploitation courante, SUPER_ADMIN gère rôles critiques et purge.
