# Facturation

Chaque Project possède un abonnement Stripe indépendant. Le client choisit un Plan local; le navigateur ne fournit jamais un prix Stripe. Checkout est hébergé par Stripe et le retour `/billing/success` indique seulement que la confirmation est en attente.

Chaque Project réserve un hostname canonique. Les plans gratuits utilisent obligatoirement un sous-domaine de `panels.centralcloud.fr`. Un plan payant peut également utiliser un hostname personnalisé : après confirmation Stripe, le Project reste `PENDING_DOMAIN` jusqu’à ce qu’un CNAME direct vers son hostname canonique soit vérifié, puis le provisioning reprend automatiquement.

Un Plan marqué `is_free` suit un cycle commercial interne `FREE` : aucun Customer, Price, Checkout ou abonnement Stripe n’est créé. Le backend confirme directement l’éligibilité et déclenche le provisioning normal. Une transaction verrouillée limite l’offre gratuite à un Project actif par utilisateur vérifié.

Le webhook vérifie `Stripe-Signature`, persiste `stripe_event_id` sous contrainte unique et répond après écriture durable. `centralcloud:billing:reconcile` traite les événements reçus. `checkout.session.completed` payé confirme le Project et déclenche le provisioning. Les événements subscription et invoice synchronisent l’état commercial.

Un paiement échoué place le Project en `PAYMENT_PAST_DUE` et notifie le client. Après sept jours configurables, `billing:enforce` effectue un soft delete et passe à `SUSPENDED`. Il n’existe aucune purge automatique. Une annulation reste active jusqu’à fin de période, puis le Project devient `CANCELLED`.

Les changements de Plan modifiant CPU/RAM ne sont pas proposés en V1 : l’Agent ne fournit pas de resize sûr. Modifier un Plan utilisé ne modifie jamais les Deployments existants.
