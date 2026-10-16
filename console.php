<?php
/**
 * console.php — tiny CLI entry point for the POS system.
 *
 * This is our "artisan": run commands from the repo root, e.g.
 *     php console.php list
 *     php console.php users:list
 *     php console.php db:seed
 *
 * It REFUSES to run over the web (see the guard below), so commands like
 * db:seed can never be triggered by visiting a URL.
 */

// --- guard: command line only (PHP_SAPI is 'cli' in a terminal, 'cli-server'
//     or 'apache2handler' when a web server runs this file) ---
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

require __DIR__ . '/api/config.php';   // defines $pdo (header() calls are no-ops in CLI)

// --- dispatch: $argv[0] is this script, $argv[1] is the first thing you typed ---
$command = $argv[1] ?? 'list';

switch ($command) {
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
    echo "  db:seed      Hash + reset every user's password to password123\n";
    echo "  users:list   Show all users\n";
    echo "  list         Show this help\n";
}
