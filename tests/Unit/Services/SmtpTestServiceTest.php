<?php

declare(strict_types=1);

use App\Services\SmtpTestService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

describe('SmtpTestService', function () {
    beforeEach(function () {
        $this->service = new SmtpTestService();
        Mail::fake();
    });

    describe('testConnection', function () {
        test('returns success for valid credentials', function () {
            $credentials = [
                'host' => 'smtp.example.com',
                'port' => 587,
                'encryption' => 'tls',
                'username' => 'user@example.com',
                'password' => 'password123',
            ];

            $result = $this->service->testConnection($credentials);

            expect($result['success'])->toBeTrue()
                ->and($result['message'])->toBeString();
        });

        test('returns error when host is missing', function () {
            $credentials = [
                'username' => 'user@example.com',
                'password' => 'password123',
            ];

            $result = $this->service->testConnection($credentials);

            expect($result['success'])->toBeFalse()
                ->and($result['message'])->toContain('requis');
        });

        test('returns error when username is missing', function () {
            $credentials = [
                'host' => 'smtp.example.com',
                'password' => 'password123',
            ];

            $result = $this->service->testConnection($credentials);

            expect($result['success'])->toBeFalse();
        });

        test('returns error when password is missing', function () {
            $credentials = [
                'host' => 'smtp.example.com',
                'username' => 'user@example.com',
            ];

            $result = $this->service->testConnection($credentials);

            expect($result['success'])->toBeFalse();
        });

        test('handles none encryption', function () {
            $credentials = [
                'host' => 'smtp.example.com',
                'port' => 25,
                'encryption' => 'none',
                'username' => 'user@example.com',
                'password' => 'password123',
            ];

            $result = $this->service->testConnection($credentials);

            // Should not throw error
            expect($result)->toBeArray();
        });

        test('uses default port when not provided', function () {
            $credentials = [
                'host' => 'smtp.example.com',
                'username' => 'user@example.com',
                'password' => 'password123',
            ];

            $result = $this->service->testConnection($credentials);

            expect($result)->toBeArray();
        });
    });
});








