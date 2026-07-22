<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PanelReadyNotification extends Notification
{
    use Queueable;

    public function __construct(public Project $project) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Votre CentralPanel est prêt', 'message' => $this->project->name.' est maintenant disponible.', 'project_uuid' => $this->project->uuid];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Votre CentralPanel est prêt')->greeting('Bonjour '.$notifiable->name)->line($this->project->name.' est maintenant disponible.')->action('Ouvrir CentralCloud', route('projects.show', $this->project->uuid));
    }
}
