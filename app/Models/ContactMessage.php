<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Messages du formulaire de contact (§4.11). */
class ContactMessage extends Model
{
    protected static string $table = 'contact_messages';

    protected static array $fillable = ['name', 'email', 'phone', 'subject', 'message', 'locale', 'status', 'ip_address', 'replied_by', 'replied_at'];

    public const SUBJECTS = ['general', 'partnership', 'technical', 'become_scholar', 'other'];
}
