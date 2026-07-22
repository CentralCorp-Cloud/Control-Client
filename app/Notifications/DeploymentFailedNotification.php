<?php

namespace App\Notifications;

use App\Models\Project;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DeploymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(public Project $project) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Déploiement interrompu', 'message' => 'Le déploiement de '.$this->project->name.' a rencontré un problème.', 'project_uuid' => $this->project->uuid];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Problème de déploiement')->line('Notre équipe a été alertée.')->action('Voir le statut', route('projects.show', $this->project->uuid));
    }
}
