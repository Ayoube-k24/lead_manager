<?php

namespace App\Traits;

use App\Services\AuditService;
use Illuminate\Support\Facades\App;

trait Auditable
{
    /**
     * Boot the trait.
     */
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            static::logModelEvent($model, 'created');
        });

        static::updated(function ($model) {
            static::logModelEvent($model, 'updated');
        });

        static::deleted(function ($model) {
            static::logModelEvent($model, 'deleted');
        });
    }

    /**
     * Log model event.
     */
    protected static function logModelEvent($model, string $event): void
    {
        $auditService = App::make(AuditService::class);

        $action = strtolower(class_basename($model)).'.'.$event;

        match ($event) {
            'created' => match (class_basename($model)) {
                'Form' => $auditService->logFormCreated($model),
                'SmtpProfile' => $auditService->logSmtpProfileCreated($model),
                'EmailTemplate' => $auditService->logEmailTemplateCreated($model),
                'User' => $auditService->logAgentCreated($model),
                default => $auditService->log($action, $model),
            },
            'updated' => match (class_basename($model)) {
                'Form' => $auditService->logFormUpdated($model, $model->getChanges()),
                'SmtpProfile' => $auditService->logSmtpProfileUpdated($model, $model->getChanges()),
                'EmailTemplate' => $auditService->logEmailTemplateUpdated($model, $model->getChanges()),
                'User' => $auditService->logAgentUpdated($model, $model->getChanges()),
                'CallCenter' => $model->wasChanged('distribution_method')
                    ? $auditService->logDistributionMethodChanged(
                        $model,
                        $model->getOriginal('distribution_method'),
                        $model->distribution_method
                    )
                    : $auditService->log($action, $model, $model->getChanges()),
                default => $auditService->log($action, $model, $model->getChanges()),
            },
            'deleted' => match (class_basename($model)) {
                'Form' => $auditService->logFormDeleted($model),
                default => $auditService->log($action, $model),
            },
            default => $auditService->log($action, $model),
        };
    }
}
