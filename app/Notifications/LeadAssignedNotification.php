<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeadAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Lead $lead
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $leadName = $this->lead->data['name'] ?? $this->lead->data['first_name'] ?? $this->lead->email;

        return (new MailMessage)
            ->subject('Nouveau lead attribué')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Un nouveau lead vous a été attribué.')
            ->line('**Lead :** '.$leadName)
            ->line('**Email :** '.$this->lead->email)
            ->line('**Statut :** '.$this->getStatusLabel())
            ->action('Voir le lead', route('agent.leads.show', $this->lead))
            ->line('Merci d\'utiliser notre application !');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lead_id' => $this->lead->id,
            'lead_email' => $this->lead->email,
            'lead_status' => $this->lead->status,
            'message' => 'Un nouveau lead vous a été attribué : '.($this->lead->data['name'] ?? $this->lead->email),
        ];
    }

    /**
     * Get status label in French.
     */
    protected function getStatusLabel(): string
    {
        return match ($this->lead->status) {
            'pending_email' => 'En attente de confirmation email',
            'email_confirmed' => 'Email confirmé',
            'pending_call' => 'En attente d\'appel',
            'confirmed' => 'Confirmé',
            'rejected' => 'Rejeté',
            'callback_pending' => 'En attente de rappel',
            default => $this->lead->status,
        };
    }
}
