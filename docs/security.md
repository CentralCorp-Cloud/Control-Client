# Sécurité

Menaces principales : compromission de compte admin, appels Agent falsifiés/rejoués, SSRF via endpoint Node, double provisioning Stripe, fuite de bootstrap, accès croisé à un Project et purge accidentelle.

Contre-mesures : email vérifié, throttling, regeneration de session Laravel, TOTP obligatoire pour les rôles admin, Policies backend, confirmation password, CSRF, échappement Blade, CSP/headers, webhooks signés et uniques, mTLS strict, allowlist CIDR, redirects désactivées, transactions/verrous et audit sanitisé.

La clé mTLS, sa private key, la CA, les secrets Stripe et master secrets restent dans les fichiers/environnement. Les mots de passe bootstrap/reset sont temporairement chiffrés avec `APP_KEY`, exclus des modèles sérialisés, logs et audits, puis écrasés après acceptation certaine. Les sauvegardes MySQL doivent être chiffrées car elles peuvent contenir une enveloppe en attente.

La purge exige ownership ou privilège SUPER_ADMIN, confirmation password et token Agent gardé côté backend. La V1 ne fournit aucun terminal, SSH, choix d’image, commande arbitraire ou accès Docker.
