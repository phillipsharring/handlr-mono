<?php

declare(strict_types=1);

use Handlr\Database\Migrations\MigrationRunner;

/**
 * Regression guard for the migration class-name derivation.
 *
 * The runner derives the class name from the filename and instantiates it. If
 * the derivation drops word boundaries (the old `str_replace('_', '', …)` bug
 * turned `create_users_table` into `Createuserstable`), every generated
 * migration fails to load with "Migration class … not found" — forcing a
 * manual rename of each file. This must stay in lockstep with the names
 * produced by MigrationMaker::toStudlyCase().
 */

/** Invoke the private classNameFromFile without touching the DB-bound constructor. */
function deriveMigrationClass(string $file): string
{
    $runner = (new ReflectionClass(MigrationRunner::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(MigrationRunner::class, 'classNameFromFile');

    return $method->invoke($runner, $file);
}

it('derives StudlyCase class names preserving word boundaries', function () {
    expect(deriveMigrationClass('20250826000500_create_users_table.php'))
        ->toBe('Migration_20250826000500_CreateUsersTable');
});

it('handles multi-word descriptions', function () {
    expect(deriveMigrationClass('20250826002500_create_permission_role_table.php'))
        ->toBe('Migration_20250826002500_CreatePermissionRoleTable');
});

it('matches the shipped app-skeleton migration class names', function () {
    $cases = [
        '20250826000000_create_sessions_table.php'             => 'Migration_20250826000000_CreateSessionsTable',
        '20250826007000_create_ab_tests_table.php'             => 'Migration_20250826007000_CreateAbTestsTable',
        '20250826008500_create_email_verification_tokens_table.php'
            => 'Migration_20250826008500_CreateEmailVerificationTokensTable',
    ];

    foreach ($cases as $file => $expected) {
        expect(deriveMigrationClass($file))->toBe($expected);
    }
});
