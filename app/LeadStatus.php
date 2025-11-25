<?php

namespace App;

enum LeadStatus: string
{
    // ============================================
    // Statuts initiaux - Cycle de validation
    // ============================================
    /** Lead créé, en attente de validation de l'email (double opt-in) */
    case PendingEmail = 'pending_email';

    /** Email validé par le prospect, prêt pour attribution à un agent */
    case EmailConfirmed = 'email_confirmed';

    /** Lead attribué à un agent, en attente d'appel téléphonique */
    case PendingCall = 'pending_call';

    // ============================================
    // Statuts après appel - Résultats de contact
    // ============================================
    /** Prospect intéressé par l'offre après contact */
    case Confirmed = 'confirmed';

    /** Prospect a refusé l'offre */
    case Rejected = 'rejected';

    /** Rappel programmé à une date ultérieure */
    case CallbackPending = 'callback_pending';

    // ============================================
    // Statuts techniques - Résultats d'appel
    // ============================================
    /** Pas de réponse lors de l'appel */
    case NoAnswer = 'no_answer';

    /** Ligne occupée lors de l'appel */
    case Busy = 'busy';

    /** Numéro de téléphone invalide ou incorrect */
    case WrongNumber = 'wrong_number';

    // ============================================
    // Statuts commerciaux - Qualification
    // ============================================
    /** Prospect a exprimé son désintérêt */
    case NotInterested = 'not_interested';

    /** Prospect qualifié (critères métier validés) */
    case Qualified = 'qualified';

    /** Conversion réussie - Lead transformé en client */
    case Converted = 'converted';

    /** Relance nécessaire pour finaliser */
    case FollowUp = 'follow_up';

    /** Rendez-vous commercial confirmé */
    case AppointmentScheduled = 'appointment_scheduled';

    /** Devis envoyé au prospect */
    case QuoteSent = 'quote_sent';

    /** Prospect en liste d'exclusion (opt-out) */
    case DoNotCall = 'do_not_call';

    /**
     * Get the label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PendingEmail => __('Validation email en cours'),
            self::EmailConfirmed => __('Prospect validé'),
            self::PendingCall => __('En file d\'appel'),
            self::Confirmed => __('Prospect intéressé'),
            self::Rejected => __('Refusé'),
            self::CallbackPending => __('Rappel programmé'),
            self::NoAnswer => __('Absent - Pas de réponse'),
            self::Busy => __('Ligne occupée'),
            self::WrongNumber => __('Numéro invalide'),
            self::NotInterested => __('Refusé - Pas intéressé'),
            self::Qualified => __('Prospect qualifié'),
            self::Converted => __('Client acquis'),
            self::FollowUp => __('Relance requise'),
            self::AppointmentScheduled => __('Rendez-vous confirmé'),
            self::QuoteSent => __('Devis envoyé'),
            self::DoNotCall => __('Liste d\'exclusion'),
        };
    }

    /**
     * Get the color class for the status badge.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::PendingEmail => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400',
            self::EmailConfirmed => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400',
            self::PendingCall => 'bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400',
            self::Confirmed => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            self::Rejected => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            self::CallbackPending => 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400',
            self::NoAnswer => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
            self::Busy => 'bg-amber-100 text-amber-800 dark:bg-amber-900/20 dark:text-amber-400',
            self::WrongNumber => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            self::NotInterested => 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400',
            self::Qualified => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400',
            self::Converted => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
            self::FollowUp => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/20 dark:text-indigo-400',
            self::AppointmentScheduled => 'bg-teal-100 text-teal-800 dark:bg-teal-900/20 dark:text-teal-400',
            self::QuoteSent => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/20 dark:text-cyan-400',
            self::DoNotCall => 'bg-slate-100 text-slate-800 dark:bg-slate-900/20 dark:text-slate-400',
        };
    }

    /**
     * Get statuses that require action (active leads).
     */
    public static function activeStatuses(): array
    {
        return [
            self::PendingEmail,
            self::EmailConfirmed,
            self::PendingCall,
            self::CallbackPending,
            self::FollowUp,
            self::AppointmentScheduled,
            self::QuoteSent,
        ];
    }

    /**
     * Get statuses that are final (closed leads).
     */
    public static function finalStatuses(): array
    {
        return [
            self::Confirmed,
            self::Rejected,
            self::Converted,
            self::NotInterested,
            self::WrongNumber,
            self::DoNotCall,
        ];
    }

    /**
     * Get statuses that can be set after a call.
     */
    public static function postCallStatuses(): array
    {
        return [
            self::Confirmed,
            self::Rejected,
            self::CallbackPending,
            self::NoAnswer,
            self::Busy,
            self::WrongNumber,
            self::NotInterested,
            self::Qualified,
            self::Converted,
            self::FollowUp,
            self::AppointmentScheduled,
            self::QuoteSent,
            self::DoNotCall,
        ];
    }

    /**
     * Check if this status is active (requires action).
     */
    public function isActive(): bool
    {
        return in_array($this, self::activeStatuses(), true);
    }

    /**
     * Check if this status is final (closed).
     */
    public function isFinal(): bool
    {
        return in_array($this, self::finalStatuses(), true);
    }

    /**
     * Check if this status can be set after a call.
     */
    public function canBeSetAfterCall(): bool
    {
        return in_array($this, self::postCallStatuses(), true);
    }

    /**
     * Get all statuses as array for select options.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
