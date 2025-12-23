<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Limites de Distribution
    |--------------------------------------------------------------------------
    |
    | Configuration des limites pour la distribution des leads aux agents.
    |
    */
    'distribution' => [
        /*
        | Limite maximale de leads par agent par jour.
        | null = pas de limite
        */
        'max_leads_per_agent_per_day' => env('MAX_LEADS_PER_AGENT_PER_DAY', 50),

        /*
        | Limite maximale de leads en attente par agent.
        | null = pas de limite
        */
        'max_pending_leads_per_agent' => env('MAX_PENDING_LEADS_PER_AGENT', null),

        /*
        | Score minimum requis pour prioriser un lead.
        | Les leads avec un score >= à cette valeur seront distribués en priorité.
        */
        'min_score_for_priority' => env('MIN_SCORE_FOR_PRIORITY', 80),

        /*
        | Distribution uniquement pendant les heures ouvrables.
        | true = distribution uniquement du lundi au vendredi, 9h-18h
        | false = distribution 24/7
        */
        'business_hours_only' => env('DISTRIBUTION_BUSINESS_HOURS_ONLY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Règles de Validation
    |--------------------------------------------------------------------------
    |
    | Règles de validation avant distribution d'un lead.
    |
    */
    'validation' => [
        /*
        | Exiger un numéro de téléphone pour distribuer un lead.
        */
        'require_phone_for_distribution' => env('REQUIRE_PHONE_FOR_DISTRIBUTION', true),

        /*
        | Complétude minimale des données requise (en pourcentage).
        | null = pas de minimum requis
        */
        'min_data_completeness' => env('MIN_DATA_COMPLETENESS', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transitions de Statut
    |--------------------------------------------------------------------------
    |
    | Règles pour les transitions de statut des leads.
    |
    */
    'status_transitions' => [
        /*
        | Transitions autorisées entre statuts.
        | Format: 'statut_actuel' => ['statut_destination1', 'statut_destination2']
        | null = toutes les transitions sont autorisées
        */
        'allowed_transitions' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Réassignation Automatique
    |--------------------------------------------------------------------------
    |
    | Configuration pour la réassignation automatique des leads.
    |
    */
    'reassignment' => [
        /*
        | Réassigner automatiquement les leads non traités lors de la désactivation d'un agent.
        */
        'auto_reassign_on_deactivation' => env('AUTO_REASSIGN_ON_DEACTIVATION', true),

        /*
        | Statuts considérés comme "non traités" pour la réassignation.
        */
        'untreated_statuses' => [
            'pending_call',
            'email_confirmed',
            'callback_pending',
        ],
    ],
];
