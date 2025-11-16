<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\SmtpProfile;
use Illuminate\Database\Seeder;

class FormSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📋 Création des formulaires de démonstration...');

        // Récupérer les profils SMTP et templates créés
        $smtpProfile = SmtpProfile::where('name', 'Gmail SMTP')->first();
        $emailTemplate = EmailTemplate::where('name', 'Validation Email Standard')->first();

        $forms = [
            [
                'name' => 'Formulaire de Contact',
                'description' => 'Formulaire de contact standard pour les demandes d\'information',
                'fields' => [
                    [
                        'name' => 'name',
                        'type' => 'text',
                        'label' => 'Nom complet',
                        'placeholder' => 'Entrez votre nom complet',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Adresse email',
                        'placeholder' => 'votre@email.com',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'tel',
                        'label' => 'Téléphone',
                        'placeholder' => '+33 6 12 34 56 78',
                        'required' => false,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'message',
                        'type' => 'textarea',
                        'label' => 'Message',
                        'placeholder' => 'Votre message...',
                        'required' => true,
                        'validation_rules' => ['min' => 10, 'max' => 1000],
                        'options' => [],
                    ],
                ],
                'smtp_profile_id' => $smtpProfile?->id,
                'email_template_id' => $emailTemplate?->id,
                'is_active' => true,
            ],
            [
                'name' => 'Formulaire d\'Inscription Newsletter',
                'description' => 'Formulaire simple pour s\'inscrire à la newsletter',
                'fields' => [
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Adresse email',
                        'placeholder' => 'votre@email.com',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'newsletter_consent',
                        'type' => 'checkbox',
                        'label' => 'J\'accepte de recevoir la newsletter',
                        'placeholder' => '',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                ],
                'smtp_profile_id' => $smtpProfile?->id,
                'email_template_id' => $emailTemplate?->id,
                'is_active' => true,
            ],
            [
                'name' => 'Formulaire de Devis',
                'description' => 'Formulaire détaillé pour demander un devis',
                'fields' => [
                    [
                        'name' => 'company_name',
                        'type' => 'text',
                        'label' => 'Nom de l\'entreprise',
                        'placeholder' => 'Nom de votre entreprise',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'contact_name',
                        'type' => 'text',
                        'label' => 'Nom du contact',
                        'placeholder' => 'Votre nom',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Email professionnel',
                        'placeholder' => 'contact@entreprise.com',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'tel',
                        'label' => 'Téléphone',
                        'placeholder' => '+33 1 23 45 67 89',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'service_type',
                        'type' => 'select',
                        'label' => 'Type de service',
                        'placeholder' => '',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => ['Consultation', 'Formation', 'Support', 'Développement', 'Autre'],
                    ],
                    [
                        'name' => 'budget',
                        'type' => 'select',
                        'label' => 'Budget estimé',
                        'placeholder' => '',
                        'required' => false,
                        'validation_rules' => [],
                        'options' => ['Moins de 1000€', '1000€ - 5000€', '5000€ - 10000€', 'Plus de 10000€'],
                    ],
                    [
                        'name' => 'project_description',
                        'type' => 'textarea',
                        'label' => 'Description du projet',
                        'placeholder' => 'Décrivez votre projet en détail...',
                        'required' => true,
                        'validation_rules' => ['min' => 20],
                        'options' => [],
                    ],
                    [
                        'name' => 'deadline',
                        'type' => 'date',
                        'label' => 'Date limite souhaitée',
                        'placeholder' => '',
                        'required' => false,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                ],
                'smtp_profile_id' => $smtpProfile?->id,
                'email_template_id' => $emailTemplate?->id,
                'is_active' => true,
            ],
            [
                'name' => 'Formulaire de Candidature',
                'description' => 'Formulaire pour postuler à une offre d\'emploi',
                'fields' => [
                    [
                        'name' => 'first_name',
                        'type' => 'text',
                        'label' => 'Prénom',
                        'placeholder' => 'Votre prénom',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'last_name',
                        'type' => 'text',
                        'label' => 'Nom',
                        'placeholder' => 'Votre nom',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'email',
                        'type' => 'email',
                        'label' => 'Email',
                        'placeholder' => 'votre@email.com',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'phone',
                        'type' => 'tel',
                        'label' => 'Téléphone',
                        'placeholder' => '+33 6 12 34 56 78',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                    [
                        'name' => 'position',
                        'type' => 'select',
                        'label' => 'Poste souhaité',
                        'placeholder' => '',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => ['Développeur', 'Designer', 'Chef de projet', 'Commercial', 'Autre'],
                    ],
                    [
                        'name' => 'experience_years',
                        'type' => 'number',
                        'label' => 'Années d\'expérience',
                        'placeholder' => '5',
                        'required' => true,
                        'validation_rules' => ['min' => 0, 'max' => 50],
                        'options' => [],
                    ],
                    [
                        'name' => 'cover_letter',
                        'type' => 'textarea',
                        'label' => 'Lettre de motivation',
                        'placeholder' => 'Rédigez votre lettre de motivation...',
                        'required' => true,
                        'validation_rules' => ['min' => 50],
                        'options' => [],
                    ],
                    [
                        'name' => 'cv',
                        'type' => 'file',
                        'label' => 'CV (PDF)',
                        'placeholder' => '',
                        'required' => true,
                        'validation_rules' => [],
                        'options' => [],
                    ],
                ],
                'smtp_profile_id' => $smtpProfile?->id,
                'email_template_id' => $emailTemplate?->id,
                'is_active' => true,
            ],
        ];

        foreach ($forms as $form) {
            Form::create($form);
            $this->command->info("  ✅ Formulaire créé: {$form['name']}");
        }

        $this->command->info('✅ Formulaires créés avec succès!');
    }
}
