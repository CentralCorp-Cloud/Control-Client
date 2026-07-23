# Sécurité

Menaces principales : compromission de compte admin, appels Agent falsifiés/rejoués, SSRF via endpoint Node, double provisioning Stripe, fuite de bootstrap, accès croisé à un Project et purge accidentelle.

Contre-mesures : email vérifié, throttling, regeneration de session Laravel,
TOTP obligatoire pour les rôles admin, Policies backend, confirmation
password, CSRF, échappement Blade, CSP/headers, webhooks signés et uniques,
HTTPS vérifié, jeton Bearer distinct par Node, redirects désactivées,
transactions/verrous et audit sanitisé.

Les jetons Agent et mots de passe bootstrap/reset sont chiffrés avec `APP_KEY`,
exclus des modèles sérialisés, logs et audits. Le Node ne stocke que le hash du
jeton Agent. Les secrets Stripe et master secrets restent dans les
fichiers/environnement. Les sauvegardes MySQL doivent être chiffrées car elles
contiennent les jetons Agent chiffrés.

La purge exige ownership ou privilège SUPER_ADMIN, confirmation password et token Agent gardé côté backend. La V1 ne fournit aucun terminal, SSH, choix d’image, commande arbitraire ou accès Docker.
