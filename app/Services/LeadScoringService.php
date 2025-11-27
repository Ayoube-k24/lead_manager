<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Facades\Config;

class LeadScoringService
{
    /**
     * Calculate the score for a lead.
     *
     * @return array{score: int, factors: array<string, array<string, mixed>>}
     */
    public function calculateScore(Lead $lead): array
    {
        $factors = $this->getScoreFactors();
        $score = 0;
        $scoreDetails = [];

        // Source du formulaire (10%)
        $formScore = $this->calculateFormScore($lead);
        $score += $formScore * ($factors['form_source']['weight'] / 100);
        $scoreDetails['form_source'] = [
            'value' => $formScore,
            'weight' => $factors['form_source']['weight'],
            'contribution' => round($formScore * ($factors['form_source']['weight'] / 100), 2),
        ];

        // Temps de confirmation email (15%)
        $emailConfirmationScore = $this->calculateEmailConfirmationScore($lead);
        $score += $emailConfirmationScore * ($factors['email_confirmation_time']['weight'] / 100);
        $scoreDetails['email_confirmation_time'] = [
            'value' => $emailConfirmationScore,
            'weight' => $factors['email_confirmation_time']['weight'],
            'contribution' => round($emailConfirmationScore * ($factors['email_confirmation_time']['weight'] / 100), 2),
        ];

        // Complétude des données (20%)
        $dataCompletenessScore = $this->calculateDataCompletenessScore($lead);
        $score += $dataCompletenessScore * ($factors['data_completeness']['weight'] / 100);
        $scoreDetails['data_completeness'] = [
            'value' => $dataCompletenessScore,
            'weight' => $factors['data_completeness']['weight'],
            'contribution' => round($dataCompletenessScore * ($factors['data_completeness']['weight'] / 100), 2),
        ];

        // Historique du lead (25%)
        $historyScore = $this->calculateHistoryScore($lead);
        $score += $historyScore * ($factors['lead_history']['weight'] / 100);
        $scoreDetails['lead_history'] = [
            'value' => $historyScore,
            'weight' => $factors['lead_history']['weight'],
            'contribution' => round($historyScore * ($factors['lead_history']['weight'] / 100), 2),
        ];

        // Statut actuel (20%)
        $statusScore = $this->calculateStatusScore($lead);
        $score += $statusScore * ($factors['current_status']['weight'] / 100);
        $scoreDetails['current_status'] = [
            'value' => $statusScore,
            'weight' => $factors['current_status']['weight'],
            'contribution' => round($statusScore * ($factors['current_status']['weight'] / 100), 2),
        ];

        // Données comportementales (10%)
        $behavioralScore = $this->calculateBehavioralScore($lead);
        $score += $behavioralScore * ($factors['behavioral_data']['weight'] / 100);
        $scoreDetails['behavioral_data'] = [
            'value' => $behavioralScore,
            'weight' => $factors['behavioral_data']['weight'],
            'contribution' => round($behavioralScore * ($factors['behavioral_data']['weight'] / 100), 2),
        ];

        // Ensure score is between 0 and 100
        $score = max(0, min(100, round($score)));

        return [
            'score' => $score,
            'factors' => $scoreDetails,
        ];
    }

    /**
     * Update the score for a lead.
     */
    public function updateScore(Lead $lead): Lead
    {
        // Load all necessary relationships before calculation
        $lead->loadMissing([
            'form',
            'notes',
            'reminders',
            'tags',
        ]);

        $result = $this->calculateScore($lead);

        $lead->update([
            'score' => $result['score'],
            'score_updated_at' => now(),
            'score_factors' => $result['factors'],
        ]);

        return $lead->fresh();
    }

    /**
     * Calculate form source score (0-100).
     */
    protected function calculateFormScore(Lead $lead): int
    {
        if (! $lead->form) {
            return 50; // Default score if no form
        }

        // Premium forms get higher score
        // You can customize this based on form properties
        if ($lead->form->is_active) {
            return 80;
        }

        return 50;
    }

    /**
     * Calculate email confirmation time score (0-100).
     */
    protected function calculateEmailConfirmationScore(Lead $lead): int
    {
        if (! $lead->email_confirmed_at || ! $lead->created_at) {
            return 0; // No confirmation = 0
        }

        $confirmationTime = $lead->created_at->diffInHours($lead->email_confirmed_at);

        if ($confirmationTime < 1) {
            return 100; // Confirmed within 1 hour
        } elseif ($confirmationTime < 24) {
            return 75; // Confirmed within 24 hours
        } elseif ($confirmationTime < 48) {
            return 50; // Confirmed within 48 hours
        } else {
            return 25; // Confirmed after 48 hours
        }
    }

    /**
     * Calculate data completeness score (0-100).
     */
    protected function calculateDataCompletenessScore(Lead $lead): int
    {
        $data = $lead->data ?? [];
        $requiredFields = ['name', 'email', 'phone'];
        $optionalFields = ['company', 'message', 'address'];

        $filledRequired = 0;
        $filledOptional = 0;

        foreach ($requiredFields as $field) {
            if (! empty($data[$field])) {
                $filledRequired++;
            }
        }

        foreach ($optionalFields as $field) {
            if (! empty($data[$field])) {
                $filledOptional++;
            }
        }

        // Required fields are worth 70%, optional 30%
        $requiredScore = ($filledRequired / count($requiredFields)) * 70;
        $optionalScore = ($filledOptional / count($optionalFields)) * 30;

        return min(100, round($requiredScore + $optionalScore));
    }

    /**
     * Calculate lead history score (0-100).
     */
    protected function calculateHistoryScore(Lead $lead): int
    {
        $score = 50; // Base score

        // Add points for notes
        $notesCount = $lead->notes()->count();
        $score += min(20, $notesCount * 2); // Max 20 points for notes

        // Add points for reminders
        $remindersCount = $lead->reminders()->where('is_completed', true)->count();
        $score += min(15, $remindersCount * 3); // Max 15 points for completed reminders

        // Add points for status changes (engagement)
        $statusChanges = $lead->getStatusHistory()->count();
        $score += min(15, $statusChanges * 2); // Max 15 points for status changes

        return min(100, $score);
    }

    /**
     * Calculate current status score (0-100).
     */
    protected function calculateStatusScore(Lead $lead): int
    {
        $status = $lead->getStatusEnum();

        return match ($status) {
            \App\LeadStatus::EmailConfirmed => 80,
            \App\LeadStatus::PendingCall => 70,
            \App\LeadStatus::Qualified => 90,
            \App\LeadStatus::Converted => 100,
            \App\LeadStatus::Confirmed => 85,
            \App\LeadStatus::CallbackPending => 60,
            \App\LeadStatus::Rejected => 10,
            \App\LeadStatus::Unqualified => 5,
            default => 50,
        };
    }

    /**
     * Calculate behavioral data score (0-100).
     */
    protected function calculateBehavioralScore(Lead $lead): int
    {
        $score = 50; // Base score

        $createdAt = $lead->created_at;
        $hour = $createdAt->hour;
        $dayOfWeek = $createdAt->dayOfWeek;

        // Business hours (9-17) get higher score
        if ($hour >= 9 && $hour <= 17) {
            $score += 20;
        }

        // Weekdays get higher score than weekends
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            $score += 20;
        } else {
            $score -= 10; // Weekend submissions might be less serious
        }

        return max(0, min(100, $score));
    }

    /**
     * Get score factors configuration.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getScoreFactors(): array
    {
        return Config::get('lead-scoring.factors', [
            'form_source' => [
                'weight' => 10,
                'label' => __('Source du formulaire'),
            ],
            'email_confirmation_time' => [
                'weight' => 15,
                'label' => __('Temps de confirmation email'),
            ],
            'data_completeness' => [
                'weight' => 20,
                'label' => __('Complétude des données'),
            ],
            'lead_history' => [
                'weight' => 25,
                'label' => __('Historique du lead'),
            ],
            'current_status' => [
                'weight' => 20,
                'label' => __('Statut actuel'),
            ],
            'behavioral_data' => [
                'weight' => 10,
                'label' => __('Données comportementales'),
            ],
        ]);
    }
}
