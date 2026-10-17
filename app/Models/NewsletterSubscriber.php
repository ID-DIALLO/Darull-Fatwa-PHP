<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

/** Abonnés à la newsletter (double confirmation par e-mail, §6). */
class NewsletterSubscriber extends Model
{
    protected static string $table = 'newsletter_subscribers';

    protected static array $fillable = ['email', 'locale', 'status', 'token_hash', 'confirmed_at'];

    /** @return array<string, mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        return Database::fetch('SELECT * FROM newsletter_subscribers WHERE email = ? LIMIT 1', [mb_strtolower(trim($email))]);
    }

    /** @return array<string, mixed>|null */
    public static function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return Database::fetch('SELECT * FROM newsletter_subscribers WHERE token_hash = ? LIMIT 1', [hash('sha256', $token)]);
    }
}
