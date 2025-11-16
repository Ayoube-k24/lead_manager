<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log an activity.
     *
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $properties = null,
        ?User $user = null
    ): ActivityLog {
        $user = $user ?? Auth::user();

        $log = ActivityLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'properties' => $properties ?? [],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return $log;
    }

    /**
     * Log a form creation.
     */
    public function logFormCreated(Model $form): ActivityLog
    {
        return $this->log('form.created', $form, [
            'form_name' => $form->name ?? 'N/A',
        ]);
    }

    /**
     * Log a form update.
     */
    public function logFormUpdated(Model $form, array $changes = []): ActivityLog
    {
        return $this->log('form.updated', $form, [
            'form_name' => $form->name ?? 'N/A',
            'changes' => $changes,
        ]);
    }

    /**
     * Log a form deletion.
     */
    public function logFormDeleted(Model $form): ActivityLog
    {
        return $this->log('form.deleted', $form, [
            'form_name' => $form->name ?? 'N/A',
        ]);
    }

    /**
     * Log a lead status update.
     */
    public function logLeadStatusUpdated(Model $lead, string $oldStatus, string $newStatus, ?string $comment = null): ActivityLog
    {
        return $this->log('lead.status_updated', $lead, [
            'lead_id' => $lead->id,
            'lead_email' => $lead->email ?? 'N/A',
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'comment' => $comment,
        ]);
    }

    /**
     * Log a lead assignment.
     */
    public function logLeadAssigned(Model $lead, User $agent): ActivityLog
    {
        return $this->log('lead.assigned', $lead, [
            'lead_id' => $lead->id,
            'lead_email' => $lead->email ?? 'N/A',
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
        ]);
    }

    /**
     * Log an agent creation.
     */
    public function logAgentCreated(User $agent): ActivityLog
    {
        return $this->log('agent.created', $agent, [
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'agent_email' => $agent->email,
        ]);
    }

    /**
     * Log an agent update.
     */
    public function logAgentUpdated(User $agent, array $changes = []): ActivityLog
    {
        return $this->log('agent.updated', $agent, [
            'agent_id' => $agent->id,
            'agent_name' => $agent->name,
            'changes' => $changes,
        ]);
    }

    /**
     * Log an SMTP profile creation.
     */
    public function logSmtpProfileCreated(Model $profile): ActivityLog
    {
        return $this->log('smtp_profile.created', $profile, [
            'profile_name' => $profile->name ?? 'N/A',
        ]);
    }

    /**
     * Log an SMTP profile update.
     */
    public function logSmtpProfileUpdated(Model $profile, array $changes = []): ActivityLog
    {
        return $this->log('smtp_profile.updated', $profile, [
            'profile_name' => $profile->name ?? 'N/A',
            'changes' => $changes,
        ]);
    }

    /**
     * Log an email template creation.
     */
    public function logEmailTemplateCreated(Model $template): ActivityLog
    {
        return $this->log('email_template.created', $template, [
            'template_name' => $template->name ?? 'N/A',
        ]);
    }

    /**
     * Log an email template update.
     */
    public function logEmailTemplateUpdated(Model $template, array $changes = []): ActivityLog
    {
        return $this->log('email_template.updated', $template, [
            'template_name' => $template->name ?? 'N/A',
            'changes' => $changes,
        ]);
    }

    /**
     * Log a distribution method change.
     */
    public function logDistributionMethodChanged(Model $callCenter, string $oldMethod, string $newMethod): ActivityLog
    {
        return $this->log('distribution_method.changed', $callCenter, [
            'call_center_id' => $callCenter->id,
            'call_center_name' => $callCenter->name ?? 'N/A',
            'old_method' => $oldMethod,
            'new_method' => $newMethod,
        ]);
    }

    /**
     * Log a login.
     */
    public function logLogin(User $user, bool $success = true, ?string $reason = null): ActivityLog
    {
        return $this->log('auth.login', $user, [
            'success' => $success,
            'reason' => $reason,
        ], $user);
    }

    /**
     * Log a logout.
     */
    public function logLogout(User $user): ActivityLog
    {
        return $this->log('auth.logout', $user, [], $user);
    }

    /**
     * Log a failed login attempt.
     */
    public function logFailedLogin(string $email, ?string $reason = null): ActivityLog
    {
        return $this->log('auth.login_failed', null, [
            'email' => $email,
            'reason' => $reason ?? 'Invalid credentials',
        ]);
    }
}
