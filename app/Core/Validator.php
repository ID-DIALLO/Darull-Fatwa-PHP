<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Validation des entrées côté serveur, avec messages traduits (lang/{locale}/validation.php).
 *
 *   $v = Validator::make($request->all(), [
 *       'email'    => 'required_without:phone|nullable|email|max:190|unique:users,email',
 *       'password' => 'required|min:8|confirmed',
 *       'audio'    => 'required|file|mimes:mp3,webm,m4a|max_size:10240',
 *   ]);
 *   if ($v->fails()) { ... $v->errors() ... }
 */
class Validator
{
    /** Correspondance extension → types MIME réels acceptés (vérifiés avec finfo). */
    private const MIME_MAP = [
        'mp3'  => ['audio/mpeg', 'audio/mp3', 'audio/mpeg3', 'audio/x-mpeg-3'],
        'webm' => ['audio/webm', 'video/webm'],
        'ogg'  => ['audio/ogg', 'application/ogg', 'video/ogg'],
        'oga'  => ['audio/ogg', 'application/ogg'],
        'm4a'  => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'video/mp4', 'audio/aac'],
        'mp4'  => ['audio/mp4', 'video/mp4'],
        'aac'  => ['audio/aac', 'audio/x-aac', 'audio/x-hx-aac-adts'],
        'wav'  => ['audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'pdf'  => ['application/pdf'],
    ];

    /** Règles appliquées même si la valeur est vide */
    private const IMPLICIT = ['required', 'required_with', 'required_without', 'accepted'];

    /** @var (callable(string, string, mixed, mixed): bool)|null Vérifie l'existence d'une valeur en base */
    private static $presenceVerifier = null;

    /** @var array<string, list<string>> */
    private array $errors = [];

    private bool $validated = false;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages   Messages personnalisés : 'champ.regle' => 'texte'
     * @param array<string, string> $attributes Noms lisibles des champs
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
        private readonly array $attributes = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|list<string>> $rules
     * @param array<string, string> $messages
     * @param array<string, string> $attributes
     */
    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): self
    {
        return new self($data, $rules, $messages, $attributes);
    }

    /**
     * Remplace la vérification en base des règles unique/exists (utile pour les tests).
     *
     * @param (callable(string $table, string $column, mixed $value, mixed $ignoreId): bool)|null $verifier
     */
    public static function setPresenceVerifier(?callable $verifier): void
    {
        self::$presenceVerifier = $verifier;
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function passes(): bool
    {
        if (!$this->validated) {
            $this->run();
        }

        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        $this->passes();

        return $this->errors;
    }

    public function first(string $field): ?string
    {
        return $this->errors()[$field][0] ?? null;
    }

    /** Données validées (uniquement les champs présents dans les règles). @return array<string, mixed> */
    public function validated(): array
    {
        return array_intersect_key($this->data, $this->rules);
    }

    private function run(): void
    {
        $this->validated = true;
        foreach ($this->rules as $field => $definition) {
            $rules = is_array($definition) ? $definition : explode('|', $definition);
            $value = $this->value($field);
            $ruleNames = array_map(static fn (string $r): string => explode(':', $r, 2)[0], $rules);
            $empty = $this->isEmpty($value);

            foreach ($rules as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, '');
                $params = $param === '' ? [] : ($name === 'regex' ? [$param] : str_getcsv($param, ',', '"', ''));

                if ($name === 'nullable' || $name === 'bail' || $name === 'sometimes') {
                    continue;
                }
                if ($empty && !in_array($name, self::IMPLICIT, true)) {
                    continue;
                }
                if (!$this->check($name, $field, $value, $params, $ruleNames)) {
                    $this->addError($field, $name, $value, $params, $ruleNames);
                    // Une seule erreur par champ : la première règle en échec
                    break;
                }
            }
        }
    }

    /**
     * @param list<string> $params
     * @param list<string> $ruleNames
     */
    private function check(string $rule, string $field, mixed $value, array $params, array $ruleNames): bool
    {
        return match ($rule) {
            'required'         => !$this->isEmpty($value),
            'required_with'    => $this->allEmpty($params) || !$this->isEmpty($value),
            'required_without' => !$this->allEmpty($params) || !$this->isEmpty($value),
            'accepted'         => in_array($value, ['1', 1, true, 'on', 'yes', 'true'], true),
            'string'           => is_string($value),
            'boolean'          => in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off'], true),
            'numeric'          => is_numeric($value),
            'integer'          => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'email'            => is_string($value) && mb_strlen($value) <= 190 && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'phone'            => is_string($value) && (bool) preg_match('/^\+[1-9]\d{7,14}$/', $value) && \App\Services\PhoneNumber::isAllowed($value),
            'url'              => is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false && (bool) preg_match('#^https?://#i', $value),
            'date'             => is_string($value) && strtotime($value) !== false,
            'regex'            => is_string($value) && (bool) preg_match($params[0] ?? '/^$/', $value),
            'in'               => is_scalar($value) && in_array((string) $value, $params, true),
            'not_in'           => !is_scalar($value) || !in_array((string) $value, $params, true),
            'min'              => $this->size($value, $ruleNames) >= (float) ($params[0] ?? 0),
            'max'              => $this->size($value, $ruleNames) <= (float) ($params[0] ?? 0),
            'between'          => $this->size($value, $ruleNames) >= (float) ($params[0] ?? 0) && $this->size($value, $ruleNames) <= (float) ($params[1] ?? 0),
            'same'             => $value === $this->value($params[0] ?? ''),
            'confirmed'        => $value === $this->value($field . '_confirmation'),
            'file'             => $this->isValidFile($value),
            'mimes'            => $this->isValidFile($value) && $this->hasAllowedMime($value, $params),
            'max_size'         => $this->isValidFile($value) && ((int) $value['size']) <= ((int) ($params[0] ?? 0)) * 1024,
            'unique'           => !$this->existsInDatabase($params[0] ?? '', $params[1] ?? $field, $value, $params[2] ?? null),
            'exists'           => $this->existsInDatabase($params[0] ?? '', $params[1] ?? $field, $value, null),
            default            => throw new \InvalidArgumentException('Règle de validation inconnue : ' . $rule),
        };
    }

    /** Valeur d'un champ, avec notation pointée pour les tableaux (ex. « name.fr » → $data['name']['fr']). */
    private function value(string $field): mixed
    {
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }
        $value = $this->data;
        foreach (explode('.', $field) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === [] || (is_string($value) && trim($value) === '')) {
            return true;
        }

        return is_array($value) && isset($value['error']) && $value['error'] === UPLOAD_ERR_NO_FILE;
    }

    /** @param list<string> $fields */
    private function allEmpty(array $fields): bool
    {
        foreach ($fields as $other) {
            if (!$this->isEmpty($this->value($other))) {
                return false;
            }
        }

        return true;
    }

    /** @param list<string> $ruleNames */
    private function size(mixed $value, array $ruleNames): float
    {
        if (is_array($value) && isset($value['tmp_name'], $value['size'])) {
            return ((int) $value['size']) / 1024; // fichiers : taille en Ko
        }
        if (is_array($value)) {
            return count($value);
        }
        if ((in_array('numeric', $ruleNames, true) || in_array('integer', $ruleNames, true)) && is_numeric($value)) {
            return (float) $value;
        }

        return (float) mb_strlen((string) $value);
    }

    private function isValidFile(mixed $value): bool
    {
        if (!is_array($value) || !isset($value['tmp_name'], $value['error'], $value['size'])) {
            return false;
        }
        if ($value['error'] !== UPLOAD_ERR_OK || !is_file((string) $value['tmp_name'])) {
            return false;
        }

        // En production, le fichier doit réellement provenir d'un envoi HTTP.
        return PHP_SAPI === 'cli' || is_uploaded_file((string) $value['tmp_name']);
    }

    /**
     * Type réel (finfo) ET extension du nom d'origine en liste blanche.
     *
     * @param array<string, mixed> $file
     * @param list<string> $extensions
     */
    private function hasAllowedMime(array $file, array $extensions): bool
    {
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== '' && !in_array($extension, $extensions, true)) {
            return false;
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        foreach ($extensions as $allowed) {
            if (in_array($mime, self::MIME_MAP[$allowed] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    private function existsInDatabase(string $table, string $column, mixed $value, mixed $ignoreId): bool
    {
        if (self::$presenceVerifier !== null) {
            return (self::$presenceVerifier)($table, $column, $value, $ignoreId);
        }
        // Noms de table/colonne validés : jamais de valeur utilisateur dans le SQL.
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $table) || !preg_match('/^[a-z_][a-z0-9_]*$/', $column)) {
            throw new \InvalidArgumentException('Table ou colonne invalide pour la validation.');
        }
        $sql = "SELECT 1 FROM `{$table}` WHERE `{$column}` = ?";
        $bindings = [$value];
        if ($ignoreId !== null && $ignoreId !== '') {
            $sql .= ' AND `id` <> ?';
            $bindings[] = $ignoreId;
        }

        return Database::fetch($sql . ' LIMIT 1', $bindings) !== null;
    }

    /**
     * @param list<string> $params
     * @param list<string> $ruleNames
     */
    private function addError(string $field, string $rule, mixed $value, array $params, array $ruleNames): void
    {
        $attribute = $this->attributes[$field]
            ?? (Lang::has('validation.attributes.' . $field) ? Lang::get('validation.attributes.' . $field) : str_replace('_', ' ', $field));

        $key = $rule;
        if (in_array($rule, ['min', 'max', 'between'], true)) {
            $key .= '.' . match (true) {
                is_array($value) && isset($value['tmp_name']) => 'file',
                is_array($value)                               => 'array',
                (in_array('numeric', $ruleNames, true) || in_array('integer', $ruleNames, true)) && is_numeric($value) => 'numeric',
                default                                        => 'string',
            };
        }

        $replace = [
            'attribute' => $attribute,
            'min'       => $params[0] ?? '',
            'max'       => $rule === 'between' ? ($params[1] ?? '') : ($params[0] ?? ''),
            'size'      => $params[0] ?? '',
            'values'    => implode(', ', $params),
            'other'     => $params[0] ?? '',
        ];

        $custom = $this->messages[$field . '.' . $rule] ?? null;
        $message = $custom !== null
            ? strtr($custom, array_combine(array_map(static fn ($k) => ':' . $k, array_keys($replace)), array_map('strval', $replace)))
            : Lang::get('validation.' . $key, $replace);

        $this->errors[$field][] = $message;
    }
}
