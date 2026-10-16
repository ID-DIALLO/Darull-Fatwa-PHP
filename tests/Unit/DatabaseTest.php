<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use PHPUnit\Framework\TestCase;

final class DatabaseTest extends TestCase
{
    public function testSplitStatementsRespectsStringsAndComments(): void
    {
        $sql = <<<'SQL'
            -- commentaire ; avec point-virgule
            SET NAMES utf8mb4;
            /* bloc ; commentaire */
            INSERT INTO t (a) VALUES ('L''Unicité ; de Dieu'), ("guillemets ; doubles");
            INSERT INTO t (a) VALUES ('antislash \' ; échappé');
            # autre commentaire ;
            CREATE TABLE `x;y` (id INT)
            SQL;

        $statements = Database::splitStatements($sql);

        self::assertCount(4, $statements);
        self::assertSame('SET NAMES utf8mb4', $statements[0]);
        self::assertStringContainsString("'L''Unicité ; de Dieu'", $statements[1]);
        self::assertStringContainsString("\\' ; échappé'", $statements[2]);
        self::assertSame('CREATE TABLE `x;y` (id INT)', $statements[3]);
    }

    public function testRealSchemaAndSeedsSplitIntoStatements(): void
    {
        $base = dirname(__DIR__, 2) . '/database';
        $schema = Database::splitStatements((string) file_get_contents($base . '/schema.sql'));
        $creates = array_filter($schema, static fn (string $s): bool => str_starts_with($s, 'CREATE TABLE'));

        self::assertCount(47, $creates);
        foreach (glob($base . '/seeds/*.sql') ?: [] as $seed) {
            self::assertNotEmpty(Database::splitStatements((string) file_get_contents($seed)), basename($seed));
        }
    }

    public function testIdentifierWhitelist(): void
    {
        self::assertSame('`users`', Database::identifier('users'));

        $this->expectException(\InvalidArgumentException::class);
        Database::identifier('users; DROP TABLE users');
    }
}
