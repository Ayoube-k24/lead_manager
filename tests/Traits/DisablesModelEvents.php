<?php

namespace Tests\Traits;

use Illuminate\Database\Eloquent\Model;

trait DisablesModelEvents
{
    /**
     * Disable model events during migrations to prevent recursion.
     */
    protected function disableModelEvents(): void
    {
        Model::unsetEventDispatcher();
    }

    /**
     * Re-enable model events after migrations.
     */
    protected function enableModelEvents(): void
    {
        Model::setEventDispatcher($this->app['events']);
    }
}

