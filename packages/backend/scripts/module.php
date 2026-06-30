<?php

/**
 * Handlr module installer.
 *
 * A Handlr module is a single unit published to BOTH registries at a lockstep
 * version: composer (`phillipsharring/handlr-module-<name>`) and npm
 * (`@phillipsharring/handlr-module-<name>`). A plain `composer require` only
 * pulls the backend half; this command installs both halves at the matching
 * version so the frontend runtime always lines up with the backend it ships
 * against.
 *
 * ## Usage
 *
 * ```bash
 * # From an app's backend/ directory (or via the app skeleton's composer script):
 * php scripts/module.php landing
 * composer run module:install -- landing
 * ```
 *
 * The npm half is pinned to the version composer actually resolved (read back
 * from composer.lock), not just the constraint  - so an app on an older module
 * version still gets the matching frontend.
 *
 * This command does NOT register the module's service provider or run its
 * migrations; it only installs the two package halves and prints the remaining
 * manual steps.
 */

declare(strict_types=1);

require_once __DIR__ . '/../src/Support/path-helpers.php';

$autoloadPath = findFileInParents(__DIR__, 'vendor/autoload.php');
if ($autoloadPath === null) {
    fwrite(STDERR, "Could not find vendor/autoload.php\n");
    exit(1);
}
require_once $autoloadPath;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/**
 * Installs both halves (composer + npm) of a dual-published Handlr module.
 */
class ModuleInstallCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('module:install')
            ->setDescription('Install both halves (composer + npm) of a Handlr module at the matching version.')
            ->addArgument('name', InputArgument::REQUIRED, 'Module name, e.g. "landing" or "ab" (without the handlr-module- prefix)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = (string) $input->getArgument('name');
        if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $name)) {
            $io->error(sprintf(
                'Invalid module name "%s". Use lowercase letters, digits and hyphens, e.g. "landing".',
                $name
            ));
            return Command::INVALID;
        }

        $composerPkg = "phillipsharring/handlr-module-$name";
        $npmPkg = "@phillipsharring/handlr-module-$name";

        // `composer run` executes scripts from the directory holding composer.json,
        // so the backend root is the current working directory. The frontend half
        // is its sibling in the app skeleton layout (backend/ + frontend/).
        $backendRoot = getcwd();
        if ($backendRoot === false) {
            $io->error('Could not determine the current working directory.');
            return Command::FAILURE;
        }
        $frontendDir = dirname($backendRoot) . '/frontend';

        $io->title("Installing module: $name");

        // --- Backend half (composer) ---
        $io->section('Backend (composer)');
        $composerBin = getenv('COMPOSER_BINARY') ?: 'composer';
        if ($this->stream($io, [$composerBin, 'require', $composerPkg], $backendRoot) !== 0) {
            $io->error("composer require $composerPkg failed.");
            return Command::FAILURE;
        }

        // The constraint in composer.json is just `^x.y`; read the version that was
        // actually resolved so the frontend half matches it exactly.
        $version = $this->resolvedVersion($backendRoot, $composerPkg);
        if ($version === null) {
            $io->error("Installed $composerPkg but could not read its version from composer.lock.");
            return Command::FAILURE;
        }
        $io->text("Resolved $composerPkg to <info>$version</info>.");

        // --- Frontend half (npm) ---
        $io->section('Frontend (npm)');
        if (!is_dir($frontendDir)) {
            $io->warning("No frontend/ directory at $frontendDir  - skipping the npm half.");
            $io->text("Install it manually where your frontend lives: npm install $npmPkg@$version");
        } else {
            if ($this->stream($io, ['npm', 'install', "$npmPkg@$version"], $frontendDir) !== 0) {
                $io->error("npm install $npmPkg@$version failed.");
                return Command::FAILURE;
            }
        }

        // --- Next steps (not automated by design) ---
        $io->success("Installed $name ($version)  - backend and frontend halves.");
        $io->section('Next steps');
        $io->listing([
            sprintf(
                'Register the module service provider in app/config.php under "providers", e.g. Handlr\\Module\\%s\\%sServiceProvider::class',
                $this->studly($name),
                $this->studly($name)
            ),
            'Run its migrations: composer run migrate',
        ]);

        return Command::SUCCESS;
    }

    /**
     * Run a process in $cwd, streaming its output live to the console.
     *
     * @param list<string> $command
     */
    private function stream(SymfonyStyle $io, array $command, string $cwd): int
    {
        $process = new Process($command, $cwd);
        $process->setTimeout(null);

        return $process->run(static function (string $type, string $buffer) use ($io): void {
            $io->write($buffer);
        });
    }

    /**
     * Read the resolved version of a composer package from $backendRoot/composer.lock.
     * Strips a leading "v" so it can be handed straight to npm.
     */
    private function resolvedVersion(string $backendRoot, string $composerPkg): ?string
    {
        $lockPath = $backendRoot . '/composer.lock';
        if (!is_file($lockPath)) {
            return null;
        }

        $lock = json_decode((string) file_get_contents($lockPath), true);
        if (!is_array($lock)) {
            return null;
        }

        foreach (['packages', 'packages-dev'] as $section) {
            foreach ($lock[$section] ?? [] as $package) {
                if (($package['name'] ?? null) === $composerPkg) {
                    return ltrim((string) $package['version'], 'v');
                }
            }
        }

        return null;
    }

    /**
     * "email-capture" => "EmailCapture" for the suggested provider namespace.
     */
    private function studly(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }
}

$app = new Application();
$app->addCommand(new ModuleInstallCommand());
$app->setDefaultCommand('module:install', true);

try {
    $app->run();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(Command::FAILURE);
}
