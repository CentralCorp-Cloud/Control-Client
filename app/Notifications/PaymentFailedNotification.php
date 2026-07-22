<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public Project $project) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Paiement échoué', 'message' => 'Le paiement de '.$this->project->name.' a échoué. Vous disposez de 7 jours avant suspension.'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Action requise : paiement échoué')->line('Mettez à jour votre moyen de paiement sous 7 jours pour éviter la suspension.')->action('Gérer la facturation', route('billing.index'));
    }
}
