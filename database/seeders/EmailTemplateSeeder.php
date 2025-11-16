<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📝 Création des templates d\'email de démonstration...');

        $templates = [
            [
                'name' => 'Validation Email Standard',
                'subject' => 'Confirmez votre email - {{name}}',
                'body_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmation d\'email</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0;">Confirmation d\'email</h1>
    </div>
    <div style="background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px;">
        <p>Bonjour <strong>{{name}}</strong>,</p>
        <p>Merci de vous être inscrit ! Pour finaliser votre inscription, veuillez confirmer votre adresse email en cliquant sur le bouton ci-dessous :</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{confirmation_link}}" style="background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Confirmer mon email</a>
        </div>
        <p>Ou copiez et collez ce lien dans votre navigateur :</p>
        <p style="word-break: break-all; color: #667eea;">{{confirmation_link}}</p>
        <p>Ce lien expirera dans 24 heures.</p>
        <p>Si vous n\'avez pas créé de compte, vous pouvez ignorer cet email.</p>
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        <p style="font-size: 12px; color: #999; text-align: center;">© 2024 Lead Manager. Tous droits réservés.</p>
    </div>
</body>
</html>',
                'body_text' => "Bonjour {{name}},\n\nMerci de vous être inscrit ! Pour finaliser votre inscription, veuillez confirmer votre adresse email en cliquant sur le lien suivant :\n\n{{confirmation_link}}\n\nCe lien expirera dans 24 heures.\n\nSi vous n'avez pas créé de compte, vous pouvez ignorer cet email.\n\n© 2024 Lead Manager. Tous droits réservés.",
                'variables' => ['name', 'email', 'confirmation_link'],
            ],
            [
                'name' => 'Validation Email Professionnel',
                'subject' => 'Bienvenue {{name}} - Confirmez votre inscription',
                'body_html' => '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue</title>
</head>
<body style="font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background: #2c3e50; padding: 40px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="color: white; margin: 0; font-size: 28px;">Bienvenue {{name}} !</h1>
    </div>
    <div style="background: white; padding: 40px; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 10px 10px;">
        <p style="font-size: 16px;">Bonjour <strong>{{name}}</strong>,</p>
        <p>Nous sommes ravis de vous accueillir dans notre communauté !</p>
        <p>Pour activer votre compte et commencer à utiliser nos services, veuillez confirmer votre adresse email :</p>
        <div style="text-align: center; margin: 40px 0;">
            <a href="{{confirmation_link}}" style="background: #3498db; color: white; padding: 18px 40px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold; font-size: 16px;">Activer mon compte</a>
        </div>
        <p style="color: #7f8c8d; font-size: 14px;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :</p>
        <p style="word-break: break-all; color: #3498db; font-size: 14px; background: #ecf0f1; padding: 10px; border-radius: 4px;">{{confirmation_link}}</p>
        <p style="color: #e74c3c; font-size: 12px; margin-top: 30px;"><strong>Important :</strong> Ce lien est valide pendant 24 heures uniquement.</p>
        <hr style="border: none; border-top: 2px solid #ecf0f1; margin: 40px 0;">
        <p style="font-size: 12px; color: #95a5a6; text-align: center; margin: 0;">Cet email a été envoyé à {{email}}. Si vous n\'avez pas créé de compte, veuillez ignorer cet email.</p>
    </div>
</body>
</html>',
                'body_text' => "Bonjour {{name}},\n\nNous sommes ravis de vous accueillir dans notre communauté !\n\nPour activer votre compte et commencer à utiliser nos services, veuillez confirmer votre adresse email en cliquant sur le lien suivant :\n\n{{confirmation_link}}\n\nImportant : Ce lien est valide pendant 24 heures uniquement.\n\nCet email a été envoyé à {{email}}. Si vous n\'avez pas créé de compte, veuillez ignorer cet email.",
                'variables' => ['name', 'email', 'confirmation_link'],
            ],
            [
                'name' => 'Validation Email Simple',
                'subject' => 'Confirmez votre email',
                'body_html' => '<div style="font-family: Arial, sans-serif; padding: 20px;">
    <h2>Confirmation d\'email</h2>
    <p>Bonjour {{name}},</p>
    <p>Veuillez confirmer votre email en cliquant sur le lien suivant :</p>
    <p><a href="{{confirmation_link}}">{{confirmation_link}}</a></p>
    <p>Merci !</p>
</div>',
                'body_text' => "Bonjour {{name}},\n\nVeuillez confirmer votre email en cliquant sur le lien suivant :\n\n{{confirmation_link}}\n\nMerci !",
                'variables' => ['name', 'confirmation_link'],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::create($template);
            $this->command->info("  ✅ Template créé: {$template['name']}");
        }

        $this->command->info('✅ Templates d\'email créés avec succès!');
    }
}
