<?php

namespace Database\Seeders;

use App\Models\EmailSubject;
use Illuminate\Database\Seeder;

class EmailSubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            [
                'subject' => 'Devis mutuelle santé',
                'default_template_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Devis Mutuelle Santé</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            <p>Nous vous remercions pour votre intérêt concernant notre offre de mutuelle santé.</p>
            <p>Nous vous préparons actuellement un devis personnalisé adapté à vos besoins.</p>
            <p>Nous vous contacterons très prochainement pour vous présenter notre proposition.</p>
            <p>Cordialement,<br>L\'équipe</p>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>',
                'is_active' => true,
                'order' => 1,
            ],
            [
                'subject' => 'Proposition offre devis assurance',
                'default_template_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #2196F3; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Proposition d\'Offre - Devis Assurance</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            <p>Suite à notre échange, nous avons le plaisir de vous présenter notre proposition d\'assurance.</p>
            <p>Notre offre a été spécialement conçue pour répondre à vos besoins et à votre budget.</p>
            <p>N\'hésitez pas à nous contacter si vous avez des questions ou souhaitez des précisions.</p>
            <p>Dans l\'attente de votre retour,<br>L\'équipe</p>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>',
                'is_active' => true,
                'order' => 2,
            ],
            [
                'subject' => 'Validation d\'information pour devis mutuelle santé',
                'default_template_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #FF9800; color: white; padding: 20px; text-align: center; }
        .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Validation d\'Information</h1>
        </div>
        <div class="content">
            <p>Bonjour,</p>
            <p>Pour finaliser votre devis de mutuelle santé, nous avons besoin de valider certaines informations avec vous.</p>
            <p>Pourriez-vous nous confirmer les éléments suivants :</p>
            <ul>
                <li>Vos informations personnelles</li>
                <li>Vos besoins en matière de couverture</li>
                <li>Votre budget</li>
            </ul>
            <p>Nous restons à votre disposition pour toute question.</p>
            <p>Cordialement,<br>L\'équipe</p>
        </div>
        <div class="footer">
            <p>Cet email a été envoyé automatiquement. Merci de ne pas y répondre.</p>
        </div>
    </div>
</body>
</html>',
                'is_active' => true,
                'order' => 3,
            ],
        ];

        foreach ($subjects as $subject) {
            EmailSubject::updateOrCreate(
                ['subject' => $subject['subject']],
                $subject
            );
        }
    }
}
