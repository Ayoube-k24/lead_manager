<?php

namespace App\Providers;

use App\Events\LeadAssigned;
use App\Events\LeadConverted;
use App\Events\LeadCreated;
use App\Events\LeadEmailConfirmed;
use App\Events\LeadStatusUpdated;
use App\Listeners\SendWebhookForLeadEvent;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        LeadCreated::class => [
            SendWebhookForLeadEvent::class,
        ],
        LeadEmailConfirmed::class => [
            SendWebhookForLeadEvent::class,
        ],
        LeadAssigned::class => [
            SendWebhookForLeadEvent::class,
        ],
        LeadStatusUpdated::class => [
            SendWebhookForLeadEvent::class,
        ],
        LeadConverted::class => [
            SendWebhookForLeadEvent::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
