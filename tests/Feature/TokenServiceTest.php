<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Database;
use App\Services\LoginThrottle;
use App\Services\QueueService;
use App\Services\TokenService;
use Tests\Support\DatabaseTestCase;

final class TokenServiceTest extends DatabaseTestCase
{
    public function testTokensAreHashedSingleUseAndExpire(): void
    {
        $id = $this->createUser();
        $token = TokenService::create($id, TokenService::EMAIL_VERIFY, 3600);

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame(hash('sha256', $token), Database::value('SELECT token_hash FROM user_tokens WHERE user_id = ?', [$id]));
        self::assertNull(TokenService::find($token, TokenService::PASSWORD_RESET), 'Le type doit correspondre');
        self::assertSame($id, TokenService::consume($token, TokenService::EMAIL_VERIFY));
        self::assertNull(TokenService::consume($token, TokenService::EMAIL_VERIFY), 'Usage unique');

        $expired = TokenService::create($id, TokenService::PASSWORD_RESET, -10);
        self::assertNull(TokenService::find($expired, TokenService::PASSWORD_RESET));
        self::assertSame(2, TokenService::purge());
    }

    public function testCreatingATokenReplacesThePreviousOneOfTheSameType(): void
    {
        $id = $this->createUser();
        $first = TokenService::create($id, TokenService::PASSWORD_RESET, 3600);
        $second = TokenService::create($id, TokenService::PASSWORD_RESET, 3600);

        self::assertNull(TokenService::find($first, TokenService::PASSWORD_RESET));
        self::assertNotNull(TokenService::find($second, TokenService::PASSWORD_RESET));
        self::assertSame(1, (int) Database::value('SELECT COUNT(*) FROM user_tokens WHERE user_id = ?', [$id]));
    }

    public function testLoginThrottleCountsFailuresSinceLastSuccess(): void
    {
        for ($i = 0; $i < 4; $i++) {
            LoginThrottle::record('awa@example.ml', '1.2.3.4', false);
        }
        self::assertSame(0, LoginThrottle::retryAfter('awa@example.ml', '1.2.3.4'));
        LoginThrottle::record('awa@example.ml', '1.2.3.4', false);
        self::assertGreaterThan(0, LoginThrottle::retryAfter('AWA@example.ml', '9.9.9.9'), 'Bloqué par identifiant');
        self::assertGreaterThan(0, LoginThrottle::retryAfter('autre@example.ml', '1.2.3.4'), 'Bloqué par IP');
        self::assertSame(0, LoginThrottle::retryAfter('autre@example.ml', '5.5.5.5'));

        LoginThrottle::record('awa@example.ml', '1.2.3.4', true);
        self::assertSame(0, LoginThrottle::retryAfter('awa@example.ml', '1.2.3.4'), 'Un succès remet le compteur à zéro');
    }

    public function testQueueProcessesEmailsAndRetriesOnFailure(): void
    {
        QueueService::push('email', 'awa@example.ml', 'password_changed', 'fr', ['name' => 'Awa']);
        QueueService::push('whatsapp', '+22376123456', 'question_answered', 'fr', []); // non pris en charge en V1

        [$sent, $failed] = QueueService::process();
        self::assertSame(1, $sent);
        self::assertSame(0, $failed, 'Le message WhatsApp est reprogrammé, pas encore en échec définitif');
        self::assertSame('sent', Database::value('SELECT status FROM message_queue WHERE channel = ?', ['email']));
        self::assertSame('pending', Database::value('SELECT status FROM message_queue WHERE channel = ?', ['whatsapp']));
        self::assertSame(1, (int) Database::value('SELECT attempts FROM message_queue WHERE channel = ?', ['whatsapp']));
        self::assertSame('Votre mot de passe a été modifié — Darul Fatwa', \App\Services\MailService::$sent[0]['subject']);
    }
}
