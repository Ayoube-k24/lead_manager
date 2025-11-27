<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Lead Scoring Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for the lead scoring system.
    | You can adjust the weights of different factors to customize
    | how leads are scored.
    |
    */

    'factors' => [
        'form_source' => [
            'weight' => 10,
            'label' => 'Source du formulaire',
            'description' => 'Score basé sur la source du formulaire (formulaires premium = score plus élevé)',
        ],
        'email_confirmation_time' => [
            'weight' => 15,
            'label' => 'Temps de confirmation email',
            'description' => 'Score basé sur la rapidité de confirmation de l\'email (< 1h = +30, < 24h = +15)',
        ],
        'data_completeness' => [
            'weight' => 20,
            'label' => 'Complétude des données',
            'description' => 'Score basé sur le nombre de champs remplis dans le formulaire',
        ],
        'lead_history' => [
            'weight' => 25,
            'label' => 'Historique du lead',
            'description' => 'Score basé sur les interactions, notes et changements de statut',
        ],
        'current_status' => [
            'weight' => 20,
            'label' => 'Statut actuel',
            'description' => 'Score basé sur le statut actuel du lead (email_confirmed = +15, pending_call = +10)',
        ],
        'behavioral_data' => [
            'weight' => 10,
            'label' => 'Données comportementales',
            'description' => 'Score basé sur l\'heure de soumission et le jour de la semaine',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Score Thresholds
    |--------------------------------------------------------------------------
    |
    | Define the thresholds for different priority levels.
    |
    */

    'thresholds' => [
        'high' => 80,
        'medium' => 60,
        'low' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Recalculate
    |--------------------------------------------------------------------------
    |
    | Automatically recalculate scores when certain events occur.
    |
    */

    'auto_recalculate' => [
        'on_creation' => true,
        'on_email_confirmation' => true,
        'on_status_change' => true,
        'on_note_added' => true,
    ],
];
