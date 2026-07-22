@props(['status'])
@php
    $value = strtoupper($status instanceof BackedEnum ? $status->value : (string) $status);
    $color = match($value) {
        'ACTIVE','ONLINE','SUCCEEDED','PAID','RESOLVED' => 'emerald',
        'RUNNING','PROVISIONING','QUEUED','PAYMENT_CONFIRMED' => 'blue',
        'FAILED','OFFLINE','PROVISIONING_FAILED' => 'red',
        'DEGRADED','PAYMENT_PAST_DUE','PENDING_CAPACITY','PENDING_DOMAIN','OPEN' => 'amber',
        default => 'slate'
    };
    $label = match($value) {
        'ACTIVE' => 'Actif', 'ONLINE' => 'En ligne', 'SUCCEEDED' => 'Réussie', 'PAID' => 'Payé',
        'RUNNING' => 'En cours', 'PROVISIONING' => 'Déploiement', 'QUEUED' => 'En attente',
        'PAYMENT_CONFIRMED' => 'Paiement confirmé', 'PENDING_PAYMENT' => 'Paiement attendu',
        'PENDING_DOMAIN' => 'Domaine en attente',
        'PENDING_CAPACITY' => 'Capacité en attente', 'PAYMENT_PAST_DUE' => 'Paiement en retard',
        'FAILED' => 'Échec', 'OFFLINE' => 'Hors ligne', 'PROVISIONING_FAILED' => 'Échec du déploiement',
        'DEGRADED' => 'Dégradé', 'SUSPENDED' => 'Suspendu', 'CANCELLED' => 'Annulé',
        'PENDING_DELETION' => 'Suppression en cours', 'MAINTENANCE' => 'Maintenance',
        default => ucfirst(strtolower(str_replace('_',' ', $value)))
    };
@endphp
<x-badge :color="$color"><span class="size-1.5 rounded-full bg-current opacity-70"></span>{{ $label }}</x-badge>
