<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Validator;
use PDO;
use Ramsey\Uuid\Uuid;

/**
 * Installation et migrations de la base (utilisé par bin/install.php, bin/migrate.php, bin/create-admin.php).
 * Compatible MySQL 8 et MariaDB 10.6+, y compris en hébergement mutualisé où la base
 * est créée depuis le panneau d'hébergement (CREATE DATABASE alors ignoré).
 */
final class Installer
{
    public function __construct(private readonly string $basePath)
    {
    }

    /** @return list<array{label: string, ok: bool, required: bool}> */
    public static function requirements(): array
    {
        $checks = [['label' => 'PHP >= 8.2 (actuel : ' . PHP_VERSION . ')', 'ok' => PHP_VERSION_ID >= 80200, 'required' => true]];
        foreach (['pdo_mysql', 'mbstring', 'intl', 'fileinfo', 'openssl', 'json'] as $ext) {
            $checks[] = ['label' => 'Extension ' . $ext, 'ok' => extension_loaded($ext), 'required' => true];
        }
        foreach (['curl', 'zip', 'gd'] as $ext) {
            $checks[] = ['label' => 'Extension ' . $ext . ' (recommandée)', 'ok' => extension_loaded($ext), 'required' => false];
        }

        return $checks;
    }

    /** Crée .env à partir de .env.example si besoin. Retourne true si le fichier vient d'être créé. */
    public function ensureEnvFile(): bool
    {
        $env = $this->basePath . '/.env';
        if (is_file($env)) {
            return false;
        }
        if (!copy($this->basePath . '/.env.example', $env)) {
            throw new \RuntimeException('Impossible de créer le fichier .env');
        }
        @chmod($env, 0600);

        return true;
    }

    /**
     * Modifie des valeurs du fichier .env (ex. DB_HOST, APP_KEY).
     *
     * @param array<string, string> $values
     */
    public function writeEnv(array $values): void
    {
        $file = $this->basePath . '/.env';
        $content = (string) file_get_contents($file);
        foreach ($values as $key => $value) {
            $escaped = preg_match('/[\s#"\'$]/', $value) ? '"' . addcslashes($value, '"\\$') . '"' : $value;
            $line = $key . '=' . $escaped;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
            $content = preg_match($pattern, $content)
                ? (string) preg_replace_callback($pattern, static fn () => $line, $content)
                : rtrim($content) . "\n" . $line . "\n";
            $_ENV[$key] = $value;
        }
        file_put_contents($file, $content, LOCK_EX);
    }

    /** Génère APP_KEY (32 octets aléatoires, base64) s'il est vide. Retourne true si une clé a été créée. */
    public function ensureAppKey(): bool
    {
        $content = (string) file_get_contents($this->basePath . '/.env');
        if (preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/=]{40,}/m', $content)) {
            return false;
        }
        $this->writeEnv(['APP_KEY' => 'base64:' . base64_encode(random_bytes(32))]);

        return true;
    }

    /**
     * Crée la base si les droits le permettent. Retourne false (sans erreur) en hébergement mutualisé.
     *
     * @param array<string, mixed> $config
     * @throws \PDOException si le serveur MySQL est injoignable ou refuse l'identifiant
     */
    public function createDatabase(array $config): bool
    {
        $name = (string) $config['database'];
        $server = Database::connect($config, false);
        try {
            $server->exec('CREATE DATABASE IF NOT EXISTS ' . Database::identifier($name) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

            return true;
        } catch (\PDOException) {
            return false;
        }
    }

    public function serverVersion(PDO $pdo): string
    {
        return (string) $pdo->query('SELECT VERSION()')->fetchColumn();
    }

    /** @return list<string> */
    public function existingTables(PDO $pdo): array
    {
        return $pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Supprime toutes les tables de la base (réinstallation complète, option --fresh). */
    public function dropAllTables(PDO $pdo): void
    {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->existingTables($pdo) as $table) {
            $pdo->exec('DROP TABLE IF EXISTS ' . Database::identifier($table));
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * Exécute un fichier SQL. Les instructions CREATE DATABASE / USE sont ignorées :
     * on utilise toujours la base configurée dans .env.
     *
     * @return int Nombre d'instructions exécutées
     */
    public function runSqlFile(PDO $pdo, string $file): int
    {
        $sql = (string) file_get_contents($file);
        $count = 0;
        foreach (Database::splitStatements($sql) as $statement) {
            if (preg_match('/^\s*(CREATE\s+DATABASE|USE)\b/i', $statement)) {
                continue;
            }
            $pdo->exec($statement);
            $count++;
        }

        return $count;
    }

    /** Schéma complet puis données initiales (seeds dans l'ordre 01_…, 02_…). */
    public function installSchema(PDO $pdo): void
    {
        $this->runSqlFile($pdo, $this->basePath . '/database/schema.sql');
        foreach ($this->seedFiles() as $seed) {
            $this->runSqlFile($pdo, $seed);
        }
        // Le schéma installé est à jour : toutes les migrations existantes sont considérées comme appliquées.
        $this->ensureMigrationsTable($pdo);
        foreach ($this->migrationFiles() as $file) {
            $this->recordMigration($pdo, basename($file), 0);
        }
    }

    /** @return list<string> */
    public function seedFiles(): array
    {
        $files = glob($this->basePath . '/database/seeds/*.sql') ?: [];
        sort($files);

        return $files;
    }

    /** @return list<string> */
    public function migrationFiles(): array
    {
        $files = glob($this->basePath . '/database/migrations/*.sql') ?: [];
        sort($files);

        return $files;
    }

    /** Table technique de suivi des migrations (voir docs/DECISIONS.md). */
    public function ensureMigrationsTable(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
               migration  VARCHAR(190) NOT NULL,
               batch      INT UNSIGNED NOT NULL DEFAULT 0,
               applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
               PRIMARY KEY (migration)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }

    /** @return list<string> Migrations déjà appliquées */
    public function appliedMigrations(PDO $pdo): array
    {
        $this->ensureMigrationsTable($pdo);

        return $pdo->query('SELECT migration FROM schema_migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @return list<string> Fichiers de migration non encore appliqués */
    public function pendingMigrations(PDO $pdo): array
    {
        $applied = array_flip($this->appliedMigrations($pdo));

        return array_values(array_filter($this->migrationFiles(), static fn (string $f): bool => !isset($applied[basename($f)])));
    }

    /** Applique les migrations en attente. @return list<string> noms appliqués */
    public function migrate(PDO $pdo, ?callable $onApplied = null): array
    {
        $pending = $this->pendingMigrations($pdo);
        $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM schema_migrations')->fetchColumn();
        $done = [];
        foreach ($pending as $file) {
            $this->runSqlFile($pdo, $file);
            $this->recordMigration($pdo, basename($file), $batch);
            $done[] = basename($file);
            if ($onApplied !== null) {
                $onApplied(basename($file));
            }
        }

        return $done;
    }

    private function recordMigration(PDO $pdo, string $name, int $batch): void
    {
        $statement = $pdo->prepare('INSERT IGNORE INTO schema_migrations (migration, batch) VALUES (?, ?)');
        $statement->execute([$name, $batch]);
    }

    /**
     * Vérifie les informations d'un compte administrateur.
     *
     * @param array<string, mixed> $data full_name, email, phone, password, password_confirmation
     * @return array<string, list<string>> erreurs (vide si tout est correct)
     */
    public function validateAdmin(array $data): array
    {
        $validator = Validator::make($data, [
            'full_name' => 'required|string|min:3|max:120',
            'email'     => 'required_without:phone|nullable|email|max:190|unique:users,email',
            'phone'     => 'nullable|phone|unique:users,phone',
            'password'  => ['required', 'string', 'min:10', 'max:200', 'regex:/^(?=.*[A-Za-z])(?=.*\d).+$/'],
        ]);

        return $validator->errors();
    }

    /**
     * Crée un compte (super-administrateur par défaut) actif et vérifié. Retourne son id.
     *
     * @param array<string, mixed> $data
     */
    public function createAdmin(array $data, string $role = 'superadmin'): int
    {
        $roleId = Database::value('SELECT id FROM roles WHERE slug = ?', [$role]);
        if ($roleId === null) {
            throw new \RuntimeException('Rôle inconnu : ' . $role);
        }
        $email = ($data['email'] ?? '') !== '' ? mb_strtolower(trim((string) $data['email'])) : null;
        $phone = ($data['phone'] ?? '') !== '' ? (string) $data['phone'] : null;

        Database::execute(
            'INSERT INTO users (uuid, role_id, full_name, email, phone, password_hash, locale, status, email_verified_at, phone_verified_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                Uuid::uuid4()->toString(),
                (int) $roleId,
                trim((string) $data['full_name']),
                $email,
                $phone,
                Auth::hashPassword((string) $data['password']),
                in_array($data['locale'] ?? 'fr', ['fr', 'ar', 'en'], true) ? $data['locale'] ?? 'fr' : 'fr',
                'active',
                $email !== null ? gmdate('Y-m-d H:i:s') : null,
                $phone !== null ? gmdate('Y-m-d H:i:s') : null,
            ],
        );
        $id = Database::lastInsertId();

        Database::execute(
            'INSERT INTO activity_logs (user_id, action, subject_type, subject_id, details) VALUES (?, ?, ?, ?, ?)',
            [$id, 'user.admin_created', 'user', $id, json_encode(['role' => $role, 'via' => 'cli'], JSON_THROW_ON_ERROR)],
        );

        return $id;
    }
}
