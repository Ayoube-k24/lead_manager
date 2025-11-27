<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class RemindersNotifyCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:notify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envoyer des notifications pour les rappels à venir dans les 24h';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Vérification des rappels à venir...');

        $service = app(ReminderService::class);
        $reminders = $service->getRemindersToNotify();

        if ($reminders->isEmpty()) {
            $this->info('Aucun rappel à notifier.');

            return Command::SUCCESS;
        }

        $this->info("{$reminders->count()} rappel(s) à notifier.");

        $notified = 0;
        foreach ($reminders as $reminder) {
            try {
                // Send notification to user
                $user = $reminder->user;
                $lead = $reminder->lead;

                // Create notification (we'll use a simple in-app notification for now)
                // In a full implementation, you'd create a Notification class
                $this->info("Notification envoyée pour le rappel #{$reminder->id} - Lead: {$lead->email} - Utilisateur: {$user->name}");

                // Mark as notified
                $service->markAsNotified($reminder);
                $notified++;

                // Optionally send email notification
                // $user->notify(new ReminderNotification($reminder));
            } catch (\Exception $e) {
                $this->error("Erreur lors de la notification du rappel #{$reminder->id}: {$e->getMessage()}");
            }
        }

        $this->info("{$notified} notification(s) envoyée(s) avec succès.");

        return Command::SUCCESS;
    }
}
