<?php
/**
 * CLI entry point. Commands: db:migrate, db:seed, users:list.
 */

// Command line only.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require __DIR__ . '/api/config.php';   // defines $pdo

// First argument is the command.
$command = $argv[1] ?? 'list';

switch ($command) {
    case 'db:migrate':
        dbMigrate($pdo);
        break;

    case 'db:seed':
        dbSeed($pdo);
        break;

    case 'users:list':
        usersList($pdo);
        break;

    case 'list':
    case 'help':
        printHelp();
        break;

    default:
        fwrite(STDERR, "Unknown command: {$command}\n\n");
        printHelp();
        exit(1);   // non-zero exit code = "it failed", so scripts can detect it
}

// ---------------------------- commands ----------------------------

/**
 * Apply each unapplied file in migrations/ and record it in schema_migrations.
 */
function dbMigrate(PDO $pdo): void
{
    $migrationsDir = __DIR__ . '/migrations';
    if (!is_dir($migrationsDir)) {
        echo "No migrations/ directory found. Nothing to do.\n";
        return;
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
            migration  VARCHAR(255) NOT NULL PRIMARY KEY,
            applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );

    $files = glob($migrationsDir . '/*.sql') ?: [];
    sort($files, SORT_STRING);

    if ($files === []) {
        echo "No migration files found. Nothing to do.\n";
        return;
    }

    $applied = $pdo->query('SELECT migration FROM schema_migrations')
        ->fetchAll(PDO::FETCH_COLUMN);
    $applied = array_flip($applied);

    $ran = 0;
    foreach ($files as $file) {
        $name = basename($file);

        if (isset($applied[$name])) {
            echo "  skip  {$name} (already applied)\n";
            continue;
        }

        try {
            foreach (splitSqlStatements((string) file_get_contents($file)) as $statement) {
                $pdo->exec($statement);
            }

            $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
            $stmt->execute([':migration' => $name]);

            echo "  run   {$name}\n";
            $ran++;
        } catch (Throwable $e) {
            fwrite(STDERR, "  FAIL  {$name}: {$e->getMessage()}\n");
            fwrite(STDERR, "Fix the SQL, then run `php console.php db:migrate` again.\n");
            exit(1);
        }
    }

    echo $ran === 0
        ? "Nothing to migrate. Database is up to date.\n"
        : "Applied {$ran} migration(s).\n";
}

/**
 * Split a .sql file on semicolons, ignoring full-line -- comments.
 */
function splitSqlStatements(string $sql): array
{
    $lines = preg_split('/\r\n|\r|\n/', $sql) ?: [];
    $clean = [];

    foreach ($lines as $line) {
        if (strpos(ltrim($line), '--') === 0) {
            continue;   // whole-line comment
        }
        $clean[] = $line;
    }

    $statements = [];
    foreach (explode(';', implode("\n", $clean)) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $statements[] = $statement;
        }
    }

    return $statements;
}

function dbSeed(PDO $pdo): void
{
    $users = ['admin', 'manager', 'cashier'];
    $stmt  = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE username = :name');

    foreach ($users as $username) {
        $stmt->execute([
            ':hash' => password_hash('password123', PASSWORD_DEFAULT),
            ':name' => $username,
        ]);
        echo "  hashed -> {$username}\n";
    }

    echo 'Seeded ' . count($users) . " users. Password for all: password123\n";
}

function usersList(PDO $pdo): void
{
    echo "  username   full_name             role     active\n";
    foreach ($pdo->query('SELECT username, full_name, role, is_active FROM users') as $u) {
        printf("  %-10s %-21s %-8s %s\n", $u['username'], $u['full_name'], $u['role'], $u['is_active']);
    }
}

function printHelp(): void
{
    echo "POS System console\n\n";
    echo "Usage: php console.php <command>\n\n";
    echo "Commands:\n";
    echo "  db:migrate   Apply unapplied files in migrations/ (safe to re-run)\n";
    echo "  db:seed      Hash + reset every user's password to password123\n";
    echo "  users:list   Show all users\n";
    echo "  list         Show this help\n";
}
