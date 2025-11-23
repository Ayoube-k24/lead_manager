<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SmtpTestService
{
    /**
     * Test SMTP connection with provided credentials.
     *
     * @param  array<string, mixed>  $credentials
     * @return array{success: bool, message: string}
     */
    public function testConnection(array $credentials): array
    {
        try {
            $host = $credentials['host'] ?? '';
            $port = $credentials['port'] ?? 587;
            $encryption = $credentials['encryption'] ?? 'tls';
            $username = $credentials['username'] ?? '';
            $password = $credentials['password'] ?? '';

            if (empty($host) || empty($username) || empty($password)) {
                return [
                    'success' => false,
                    'message' => __('Les champs host, username et password sont requis'),
                ];
            }

            // Configure mailer with SMTP profile
            $config = [
                'transport' => 'smtp',
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption === 'none' ? null : $encryption,
                'username' => $username,
                'password' => $password,
                'timeout' => 10,
            ];

            // Create a temporary mailer configuration
            Config::set('mail.mailers.test_smtp', $config);

            // Get the mailer
            $mailer = Mail::mailer('test_smtp');

            // Test connection by attempting to send a test email
            // The connection will be validated during the SMTP handshake
            // We catch specific errors to determine if connection/auth failed
            $mailer->raw('SMTP Connection Test', function ($message) use ($username) {
                $message->to('test@example.com')
                    ->subject('SMTP Connection Test')
                    ->from($username);
            });

            return [
                'success' => true,
                'message' => __('Connexion SMTP réussie ! Les identifiants sont valides.'),
            ];
        } catch (TransportExceptionInterface $e) {
            $errorMessage = $this->parseErrorMessage($e->getMessage());

            return [
                'success' => false,
                'message' => __('Échec de la connexion SMTP : :error', ['error' => $errorMessage]),
            ];
        } catch (\Exception $e) {
            $errorMessage = $this->parseErrorMessage($e->getMessage());

            return [
                'success' => false,
                'message' => __('Échec de la connexion SMTP : :error', ['error' => $errorMessage]),
            ];
        }
    }

    /**
     * Parse and translate common SMTP error messages.
     */
    protected function parseErrorMessage(string $message): string
    {
        $message = strtolower($message);

        if (str_contains($message, 'authentication failed') || str_contains($message, 'invalid login') || str_contains($message, '535')) {
            return __('Authentification échouée. Vérifiez votre nom d\'utilisateur et mot de passe.');
        }

        if (str_contains($message, 'connection refused') || str_contains($message, 'could not connect') || str_contains($message, 'connection timed out')) {
            return __('Impossible de se connecter au serveur. Vérifiez l\'adresse et le port.');
        }

        if (str_contains($message, 'timeout')) {
            return __('Délai d\'attente dépassé. Le serveur ne répond pas.');
        }

        if (str_contains($message, 'ssl') || str_contains($message, 'tls') || str_contains($message, 'certificate')) {
            return __('Erreur de chiffrement. Vérifiez le type de chiffrement (TLS/SSL).');
        }

        return $message;
    }
}
