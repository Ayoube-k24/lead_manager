<?php

namespace Database\Seeders;

use App\Models\SmtpProfile;
use Illuminate\Database\Seeder;

class SmtpProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📧 Création des profils SMTP de démonstration...');

        $profiles = [
            [
                'name' => 'Gmail SMTP',
                'host' => 'smtp.gmail.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'noreply@example.com',
                'password' => 'your-app-password',
                'from_address' => 'noreply@example.com',
                'from_name' => 'Lead Manager',
                'is_active' => true,
            ],
            [
                'name' => 'Outlook SMTP',
                'host' => 'smtp.office365.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'noreply@outlook.com',
                'password' => 'your-password',
                'from_address' => 'noreply@outlook.com',
                'from_name' => 'Lead Manager',
                'is_active' => true,
            ],
            [
                'name' => 'SendGrid SMTP',
                'host' => 'smtp.sendgrid.net',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'apikey',
                'password' => 'your-sendgrid-api-key',
                'from_address' => 'noreply@sendgrid.com',
                'from_name' => 'Lead Manager',
                'is_active' => true,
            ],
            [
                'name' => 'Mailgun SMTP',
                'host' => 'smtp.mailgun.org',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'postmaster@your-domain.com',
                'password' => 'your-mailgun-password',
                'from_address' => 'noreply@your-domain.com',
                'from_name' => 'Lead Manager',
                'is_active' => false,
            ],
        ];

        foreach ($profiles as $profile) {
            SmtpProfile::create($profile);
            $this->command->info("  ✅ Profil SMTP créé: {$profile['name']}");
        }

        $this->command->info('✅ Profils SMTP créés avec succès!');
    }
}
