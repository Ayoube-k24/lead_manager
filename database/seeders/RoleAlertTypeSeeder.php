<?php

namespace Database\Seeders;

use App\Models\RoleAlertType;
use Illuminate\Database\Seeder;

class RoleAlertTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📝 Création des types d\'alertes par rôle...');

        // Types d'alertes pour OWNER (call_center_owner)
        $ownerAlertTypes = [
            [
                'role_slug' => 'call_center_owner',
                'alert_type' => 'status_threshold',
                'name' => 'Pas de réponse',
                'description' => 'Alerte lorsque le nombre de leads avec le statut "Pas de réponse" atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => ['status_slug' => 'no_answer'],
                'order' => 1,
            ],
            [
                'role_slug' => 'call_center_owner',
                'alert_type' => 'status_threshold',
                'name' => 'Ligne occupée',
                'description' => 'Alerte lorsque le nombre de leads avec le statut "Ligne occupée" atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => ['status_slug' => 'busy'],
                'order' => 2,
            ],
            [
                'role_slug' => 'call_center_owner',
                'alert_type' => 'status_threshold',
                'name' => 'Numéro invalide',
                'description' => 'Alerte lorsque le nombre de leads avec le statut "Numéro invalide" atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => ['status_slug' => 'wrong_number'],
                'order' => 3,
            ],
            [
                'role_slug' => 'call_center_owner',
                'alert_type' => 'status_threshold',
                'name' => 'Prospect Pas intéressé (Refusé)',
                'description' => 'Alerte lorsque le nombre de leads avec le statut "Prospect Pas intéressé" atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => ['status_slug' => 'not_interested'],
                'order' => 4,
            ],
            [
                'role_slug' => 'call_center_owner',
                'alert_type' => 'status_threshold',
                'name' => 'Prospect intéressé (Rappel programmé)',
                'description' => 'Alerte lorsque le nombre de leads avec le statut "Rappel programmé" atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => ['status_slug' => 'callback_pending'],
                'order' => 5,
            ],
            [
                'role_slug' => 'call_center_owner',
                'alert_type' => 'status_threshold',
                'name' => 'Devis envoyé',
                'description' => 'Alerte lorsque le nombre de leads avec le statut "Devis envoyé" atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => ['status_slug' => 'quote_sent'],
                'order' => 6,
            ],
            [
                'role_slug' => 'call_center_owner',
                'alert_type' => 'status_threshold',
                'name' => 'Prospect validé',
                'description' => 'Alerte lorsque le nombre de leads avec le statut "Prospect validé" atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => ['status_slug' => 'converted'],
                'order' => 7,
            ],
        ];

        // Types d'alertes pour SUPER ADMIN
        $superAdminAlertTypes = [
            [
                'role_slug' => 'super_admin',
                'alert_type' => 'lead_stale',
                'name' => 'Lead inactif',
                'description' => 'Détecte les leads qui n\'ont pas été mis à jour depuis X heures',
                'is_enabled' => true,
                'default_conditions' => ['hours' => 24],
                'order' => 1,
            ],
            [
                'role_slug' => 'super_admin',
                'alert_type' => 'agent_performance',
                'name' => 'Performance agent',
                'description' => 'Surveille le taux de conversion d\'un agent spécifique',
                'is_enabled' => true,
                'default_conditions' => [],
                'order' => 2,
            ],
            [
                'role_slug' => 'super_admin',
                'alert_type' => 'conversion_rate',
                'name' => 'Taux de conversion',
                'description' => 'Surveille le taux de conversion global de tous les leads',
                'is_enabled' => true,
                'default_conditions' => [],
                'order' => 3,
            ],
            [
                'role_slug' => 'super_admin',
                'alert_type' => 'high_volume',
                'name' => 'Volume élevé',
                'description' => 'Détecte quand trop de leads arrivent dans un laps de temps',
                'is_enabled' => true,
                'default_conditions' => ['hours' => 1],
                'order' => 4,
            ],
            [
                'role_slug' => 'super_admin',
                'alert_type' => 'low_volume',
                'name' => 'Volume faible',
                'description' => 'Détecte quand trop peu de leads arrivent dans un laps de temps',
                'is_enabled' => true,
                'default_conditions' => ['hours' => 1],
                'order' => 5,
            ],
            [
                'role_slug' => 'super_admin',
                'alert_type' => 'form_performance',
                'name' => 'Performance formulaire',
                'description' => 'Surveille le taux de conversion d\'un formulaire spécifique',
                'is_enabled' => true,
                'default_conditions' => [],
                'order' => 6,
            ],
            [
                'role_slug' => 'super_admin',
                'alert_type' => 'status_threshold',
                'name' => 'Seuil de statut',
                'description' => 'Alerte lorsque le nombre de leads avec un statut spécifique atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => [],
                'order' => 7,
            ],
        ];

        // Types d'alertes pour SUPERVISOR
        $supervisorAlertTypes = [
            [
                'role_slug' => 'supervisor',
                'alert_type' => 'status_threshold',
                'name' => 'Seuil de statut',
                'description' => 'Alerte lorsque le nombre de leads avec un statut spécifique atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => [],
                'order' => 1,
            ],
            [
                'role_slug' => 'supervisor',
                'alert_type' => 'agent_performance',
                'name' => 'Performance agent',
                'description' => 'Surveille le taux de conversion des agents sous votre supervision',
                'is_enabled' => true,
                'default_conditions' => [],
                'order' => 2,
            ],
        ];

        // Types d'alertes pour AGENT
        $agentAlertTypes = [
            [
                'role_slug' => 'agent',
                'alert_type' => 'status_threshold',
                'name' => 'Seuil de statut',
                'description' => 'Alerte lorsque le nombre de vos leads avec un statut spécifique atteint un seuil',
                'is_enabled' => true,
                'default_conditions' => [],
                'order' => 1,
            ],
        ];

        // Insérer tous les types d'alertes
        $allTypes = array_merge(
            $ownerAlertTypes,
            $superAdminAlertTypes,
            $supervisorAlertTypes,
            $agentAlertTypes
        );

        foreach ($allTypes as $typeData) {
            RoleAlertType::updateOrCreate(
                [
                    'role_slug' => $typeData['role_slug'],
                    'alert_type' => $typeData['alert_type'],
                ],
                $typeData
            );
        }

        $this->command->info('✅ Types d\'alertes par rôle créés avec succès!');
    }
}
