<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Composer;
use Composer\Console\Application;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use Composer\Util\Filesystem;
use Composer\Util\PackageSorter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This class updates Drupal core to the next available stable version using
 * Composer.
 *
 * This class performs the following operations:
 * 1. Determines the current version of Drupal core.
 * 2. Determines the next available stable version of Drupal core based on options:
 *    - A specific version (--version).
 *    - The latest available stable minor version (--latest-minor).
 *    - The latest available stable major version (--latest-major).
 *    - The next latest available stable major version (--next-major).
 * 3. Updates the composer.json file to set the next stable version for
 *    drupal/core-recommended, drupal/core-composer-scaffold, and drupal/core-dev.
 * 4. Sets the version constraints for all other required and required-dev packages to '*'.
 * 5. Runs 'composer update --minimal-changes' to update the packages with minimal changes.
 * 6. Replaces wildcard versions in composer.json with caret versions from composer.lock.
 * 7. Updates composer.lock hashes.
 */
final class DrupalVersionChanger
{
    /**
     * The Composer service instance.
     *
     * @var \Composer\Composer
     */
    private Composer $composer;

    /**
     * The Composer Console Application instance.
     *
     * @var \Composer\Console\Application
     */
    private Application $application;

    /**
     * Input service for Composer Application.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    private InputInterface $input;

    /**
     * Output service for Composer Application.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private OutputInterface $output;

    /**
     * I/O service for Composer.
     *
     * @var \Composer\IO\IOInterface
     */
    protected IOInterface $io;

    /**
     * Represents the composer.json file.
     *
     * @var \Composer\Json\JsonFile
     */
    private JsonFile $composerJson;

    /**
     * Represents the composer.lock file.
     *
     * @var \Composer\Json\JsonFile
     */
    private JsonFile $composerLock;

    /**
     * Stores the original content of composer.json before modifications.
     *
     * @var string
     */
    private string $composerJsonBackup;

    /**
     * Stores the original content of composer.lock before modifications.
     *
     * @var string
     */
    private string $composerLockBackup;

    /**
     * DrupalUpdater constructor.
     *
     * @param Composer $composer
     *   The Composer service instance.
     * @param Application $application
     *   The Composer Console Application instance.
     * @param InputInterface $input
     *   The Input service for Composer Application.
     * @param OutputInterface $output
     *   The Output service for Composer Application.
     * @param IOInterface $io
     *   The I/O service for Composer.
     */
    public function __construct(Composer $composer, Application $application, InputInterface $input, OutputInterface $output, IOInterface $io)
    {
        $this->composer = $composer;
        $this->application = $application;
        $this->input = $input;
        $this->output = $output;
        $this->io = $io;
    }

    /**
     * Executes the Drupal core update process.
     *
     * @return int
     *   Returns 0 if successful, otherwise an error code.
     */
    public function execute(): int
    {
        // Validate requested target version..
        if (!$this->requestedTargetVersionIsValid()) {
            return 1;
        }
        // Attempt to read composer files.
        if (!$this->readComposerFiles()) {
            return 1;
        }
        // Retrieve current and next requested versions of Drupal core.
        $current_version = $this->getCurrentVersion();
        if ($current_version === null) {
            $this->io->writeError("Unable to determine the current Drupal core version. Ensure this is a valid Drupal project.");
            return 0;
        }
        $target_version = $this->getRequestedTargetVersion($current_version);
        // Validate if update is necessary.
        if ($target_version === null || ($current_version === $target_version)) {
            $this->io->write("Your Drupal core ($current_version) is already the requested latest version.");
            return 0;
        }
        // Confirm upgrade with user.
        if (!$this->input->getOption('yes') && !$this->confirmUpgrade($current_version, $target_version)) {
            $this->io->write("Operation cancelled by user.");
            return 0;
        }
        // Perform the upgrade.
        return $this->upgradeDrupalCore($current_version, $target_version);
    }

    /**
     * Validates if the requested target version is valid.
     *
     * @return bool
     *   Returns true if the requested target version is valid, otherwise false.
     */
    private function requestedTargetVersionIsValid(): bool
    {
        // Get the user input.
        /** @var string $version */
        $version = $this->input->getArgument('version');
        /** @var string $latest_minor */
        $latest_minor = $this->input->getOption('latest-minor');
        /** @var string $latest_major */
        $latest_major = $this->input->getOption('latest-major');
        /** @var string $next_major */
        $next_major = $this->input->getOption('next-major');
        // It is an error if no flag or version is specified.
        if (!$version && !$latest_minor && !$latest_major && !$next_major) {
            $this->io->writeError('Error: You must specify either a version or the --latest-minor, --latest-major, or --next-major option.');
            return false;
        }
        // It is an error if [version] is specified at the same time as either --latest-minor, --latest-major, or --next-major.
        if ($version && ($latest_minor || $latest_major || $next_major)) {
            $this->io->writeError('Error: You cannot specify a version and the --latest-minor, --latest-major, or --next-major option at the same time.');
            return false;
        }
        return true;
    }

    /**
     * Upgrades Drupal core from the current version to the specified version.
     *
     * @param string $current_version
     *   The current version of Drupal core.
     * @param string $target_version
     *   The specified version of Drupal core to upgrade to.
     *
     * @return int
     *   Returns 0 on success, 1 on failure.
     */
    private function upgradeDrupalCore(string $current_version, string $target_version): int
    {
        // Backup composer files before modification.
        $this->backupComposerFiles();
        // Try to upgrade.
        try {
            // Start Drupal core update process.
            $this->io->write("<info>Updating Drupal core from version $current_version to version $target_version.</info>");
            // Step 1: Update composer.json with specified version and wildcards.
            $this->updateComposerJsonWithWildcards($target_version);
            // Step 2: Run 'composer update --minimal-changes' to update with minimal changes.
            $this->runComposerUpdate(['--minimal-changes' => true, '--no-interaction' => true]);
            // Step 3: Replace wildcard versions in composer.json with caret versions.
            $this->replaceWildcardVersionsInComposerJson();
            $this->runComposerUpdate(['--lock' => true, '--no-interaction' => true]);
            // Completion message.
            $this->io->write("<info>Drupal core has been successfully updated from version $current_version to version $target_version.</info>");
            return 0;
        } catch (\Exception $e) {
            // Error handling: revert changes and return error code.
            $this->io->writeError("<error>Exception caught: {$e->getMessage()}</error>");
            $this->revertComposerFiles();
            return 1;
        }
    }

    /**
     * Reads the composer.json and composer.lock files.
     *
     * @return bool
     *   Returns TRUE if files are readable, otherwise FALSE.
     */
    private function readComposerFiles(): bool
    {
        // Determine paths for composer.json and composer.lock.
        $composer_json_path = Factory::getComposerFile();
        $composer_lock_path = Factory::getLockFile($composer_json_path);
        // Check readability of files.
        if (!Filesystem::isReadable($composer_json_path)) {
            $this->io->writeError('<error>' . $composer_json_path . ' is not readable.</error>');
            return false;
        }
        if (!Filesystem::isReadable($composer_lock_path)) {
            $this->io->writeError('<error>' . $composer_lock_path  . ' is not readable.</error>');
            return false;
        }
        // Initialize JsonFile objects.
        $this->composerJson = new JsonFile($composer_json_path);
        $this->composerLock = new JsonFile($composer_lock_path);
        return true;
    }

    /**
     * Backup composer.json and composer.lock files before any modifications.
     */
    private function backupComposerFiles(): void
    {
        $this->io->write("Backing up composer.json and composer.lock files...");
        // Backup composer.json.
        /** @var string $json_raw_content */
        $json_raw_content = file_get_contents($this->composerJson->getPath());
        $this->composerJsonBackup = $json_raw_content;
        // Backup composer.lock.
        /** @var string $lock_raw_content */
        $lock_raw_content = file_get_contents($this->composerLock->getPath());
        $this->composerLockBackup = $lock_raw_content;
    }

    /**
     * Retrieves the current version of Drupal core.
     *
     * @return string|null
     *   Returns the current version of Drupal core.
     */
    private function getCurrentVersion(): ?string
    {
        $installedRepo = $this->composer->getRepositoryManager()->getLocalRepository();
        $package = $installedRepo->findPackage('drupal/core-recommended', '*');
        if ($package == null) {
            return null;
        }
        return $package->getPrettyVersion();
    }

    /**
     * Determines the requested target version of Drupal core.
     *
     * @param string $current_version
     *   The current version of Drupal core.
     *
     * @return string|null
     *   Returns the requested target version, or null if no update is needed.
     */
    private function getRequestedTargetVersion(string $current_version): ?string
    {
        // Check if the user request specific target version.
        /** @var string $version */
        $version = $this->input->getArgument('version');
        if ($version) {
            return $version;
        }
        // Check if the user request for the latest minor version.
        /** @var string $latest_minor */
        $latest_minor = $this->input->getOption('latest-minor');
        if ($latest_minor) {
            return $this->getLatestMinorVersion($current_version);
        }
        // Check if the user request for the latest major version.
        /** @var string $latest_major */
        $latest_major = $this->input->getOption('latest-major');
        if ($latest_major) {
            return $this->getLatestMajorVersion($current_version);
        }
        // Check if the user request for the next major version.
        /** @var string $next_major */
        $next_major = $this->input->getOption('next-major');
        if ($next_major) {
            return $this->getNextMajorVersion($current_version);
        }
        return null;
    }

    /**
     * Retrieves the latest minor stable version of Drupal core for the same major version as the current version.
     *
     * @param string $current_version
     *   The current version of Drupal core.
     *
     * @return string|null
     *   Returns the latest minor stable version of Drupal core, or null if none is found.
     */
    private function getLatestMinorVersion(string $current_version): ?string
    {
        // Define the constraint to limit the search to minor versions within the current major version.
        $current_major_version = $this->extractMajorVersion($current_version);
        $next_major_version = ($current_major_version + 1);
        $lower_bound_constraint = new Constraint('>', $current_version);
        $upper_bound_constraint = new Constraint('<', "{$next_major_version}.0.0");
        // Combine lower and upper bound constraints to create a range constraint.
        $range_constraint = new MultiConstraint([$lower_bound_constraint, $upper_bound_constraint], true);
        // Find available core packages versions that meet the range constraint.
        $available_versions = $this->getAvailableVersions('drupal/core-recommended', $range_constraint);
        // Since the versions are sorted, the latest version is the first one in the list.
        return !empty($available_versions) ? current($available_versions) : null;
    }

    /**
     * Retrieves the latest major stable version of Drupal core.
     *
     * @param string $current_version
     *   The current version of Drupal core.
     *
     * @return string|null
     *   Returns the latest major stable version of Drupal core, or NULL if none is
     *   found.
     */
    private function getLatestMajorVersion(string $current_version): ?string
    {
        // Define the constraint to limit the search to major versions.
        $constraint = new Constraint('>', $current_version);
        // Find available core packages versions that meet the range constraint.
        $available_versions = $this->getAvailableVersions('drupal/core-recommended', $constraint);
        // Since the versions are sorted, the latest version is the first one in the list.
        return !empty($available_versions) ? current($available_versions) : null;
    }

    /**
     * Retrieves the next latest major stable version of Drupal core.
     *
     * @param string $current_version
     *   The current version of Drupal core.
     *
     * @return string|null
     *   Returns the next latest major stable version of Drupal core, or NULL if none is
     *   found.
     */
    private function getNextMajorVersion(string $current_version): ?string
    {
        // Define the constraint to limit the search to the next major versions.
        $current_major_version = $this->extractMajorVersion($current_version);
        $lower_bound_major_version = ($current_major_version + 1);
        $upper_bound_major_version = ($lower_bound_major_version + 1);
        $lower_bound_constraint = new Constraint('>=', "{$lower_bound_major_version}.0.0");
        $upper_bound_constraint = new Constraint('<', "{$upper_bound_major_version}.0.0");
        // Combine lower and upper bound constraints to create a range constraint.
        $range_constraint = new MultiConstraint([$lower_bound_constraint, $upper_bound_constraint], true);
        // Find available core packages versions that meet the range constraint.
        $available_versions = $this->getAvailableVersions('drupal/core-recommended', $range_constraint);
        // Since the versions are sorted, the latest version is the first one in the list.
        return !empty($available_versions) ? current($available_versions) : null;
    }

    /**
     * Retrieves available versions of a package from Composer repositories.
     *
     * @param string $package_name
     *   The name of the package.
     * @param \Composer\Semver\Constraint\ConstraintInterface $constraint
     *   The version constraint to match against.
     *
     * @return array<string, string>
     *   Returns an array of available versions.
     */
    private function getAvailableVersions(string $package_name, ConstraintInterface $constraint): array
    {
        $repositoryManager = $this->composer->getRepositoryManager();
        $packages = $repositoryManager->findPackages($package_name, $constraint);
        // Sort the packages.
        $sorted_packages = PackageSorter::sortPackages($packages);
        // Filter packages with stable versions only.
        $stable_packages = array_filter($sorted_packages, function ($package) {
            return $package->getStability() === 'stable';
        });
        // Extract the versions.
        $versions = [];
        foreach ($stable_packages as $package) {
            $version = $package->getPrettyVersion();
            $versions[$version] = $version;
        }
        return $versions;
    }

    /**
     * Prompts the user for confirmation before proceeding with the upgrade.
     *
     * @param string $current_version
     *   The current version of Drupal.
     * @param string $next_version
     *   The next stable version of Drupal.
     *
     * @return bool
     *   TRUE if the user confirms the upgrade, FALSE otherwise.
     */
    private function confirmUpgrade(string $current_version, string $next_version): bool
    {
        $this->io->write("Your current Drupal Core version is \"$current_version\" and the requested next version to upgrade is \"$next_version\".");
        return $this->io->askConfirmation("Do you want to proceed with the upgrade? (yes/no)", false);
    }

    /**
     * Update composer.json with wildcard versions for core packages and '*'
     * for other packages.
     *
     * @param string $next_stable_version
     *   The next stable version of Drupal.
     */
    private function updateComposerJsonWithWildcards(string $next_stable_version): void
    {
        // Read composer.json content.
        /** @var array<string, string> $composer_json */
        $composer_json = $this->composerJson->read();
        // Define core packages to update with specific version.
        $core_packages = ['drupal/core-recommended', 'drupal/core-composer-scaffold', 'drupal/core-dev'];
        // Update version for core packages.
        foreach ($core_packages as $package) {
            $composer_json['require'][$package] = $next_stable_version;
        }
        // Set wildcard version constraint for all other required packages.
        foreach ($composer_json['require'] as $package => $version) {
            if (!in_array($package, $core_packages)) {
                $composer_json['require'][$package] = '*';
            }
        }
        // Set wildcard version constraint for all other required-dev packages.
        if (isset($composer_json['require-dev'])) {
            foreach ($composer_json['require-dev'] as $package => $version) {
                if (!in_array($package, $core_packages)) {
                    $composer_json['require-dev'][$package] = '*';
                }
            }
        }
        // Save updated composer.json content.
        $this->composerJson->write($composer_json);
    }

    /**
     * Replaces wildcard versions in composer.json with caret versions from
     * composer.lock.
     */
    private function replaceWildcardVersionsInComposerJson(): void
    {
        // Read composer.json file.
        /** @var array<string, array<string, string>> $json_data */
        $json_data = $this->composerJson->read();
        // Extract locked versions from composer.lock.
        $locked_versions = $this->extractLockedVersions();
        // Update wildcard versions for all other required packages in composer.json.
        foreach ($json_data['require'] as $package => $version) {
            if ($this->hasWildcardVersion($version) && isset($locked_versions[$package])) {
                $exact_wersion = $locked_versions[$package];
                $caret_version = $this->generateCaretVersion($exact_wersion);
                $json_data['require'][$package] = $caret_version;
            }
        }
        // Update wildcard versions for all other required-dev packages in composer.json.
        if (isset($json_data['require-dev'])) {
            foreach ($json_data['require-dev'] as $package => $version) {
                if ($this->hasWildcardVersion($version) && isset($locked_versions[$package])) {
                    $exact_wersion = $locked_versions[$package];
                    $caret_version = $this->generateCaretVersion($exact_wersion);
                    $json_data['require-dev'][$package] = $caret_version;
                }
            }
        }
        // Write back the updated composer.json.
        $this->composerJson->write($json_data);
    }

    /**
     * Extracts locked versions from composer.lock file.
     *
     * @return array<string, string>
     *   An associative array where keys are package names and values are
     *   versions.
     */
    private function extractLockedVersions(): array
    {
        // Reads data from composer.lock file.
        /** @var array<string, array<string, string>> $lock_data */
        $lock_data = $this->composerLock->read();
        $locked_versions = [];
        /** @var array<string, string> $package */
        foreach ($lock_data['packages'] as $package) {
            $locked_versions[$package['name']] = $package['version'];
        }
        return $locked_versions;
    }

    /**
     * Checks if a version string contains a wildcard (*).
     *
     * @param string $version
     *   The version string to check.
     *
     * @return bool
     *   TRUE if the version contains a wildcard, otherwise FALSE.
     */
    private function hasWildcardVersion(string $version): bool
    {
        $version_parser = new VersionParser();
        $constraint = $version_parser->parseConstraints($version);
        // Create a constraint to match against '*'.
        $wildcard_constraint = new Constraint('=', '*');
        return $constraint->matches($wildcard_constraint);
    }

    /**
     * Generates a caret version (^) based on the given version.
     *
     * @param string $version
     *   The version string.
     *
     * @return string
     *   The caret version.
     */
    private function generateCaretVersion(string $version): string
    {
        $version_parser = new VersionParser();
        $normalized = $version_parser->normalize($version);
        $parts = explode('.', $normalized);
        // Ensure there are at least two parts (major and minor versions).
        if (count($parts) >= 2) {
            return '^' . $parts[0] . '.' . $parts[1];
        }
        // Default to returning the original version prefixed with '^'.
        return '^' . $version;
    }

    /**
     * Extracts the major version number from a version string.
     *
     * @param string $version
     *   The version string to extract the major version from (e.g., "8.0.0").
     *
     * @return int
     *   The major version number extracted from the given version string.
     */
    private function extractMajorVersion(string $version): int
    {
        $versionParser = new VersionParser();
        // Normalizes a version string to be able to perform comparisons on it.
        $normalized_version = $versionParser->normalize($version);
        // Get version parts.
        $version_parts = explode('.', $normalized_version);
        // Extract the major version.
        $major_version = current($version_parts);
        // Convert it into a int.
        return intval($major_version);
    }

    /**
     * Runs 'composer update' command with specific flags.
     *
     * @param array<string, bool> $options
     *   The flags to pass to 'composer update' command.
     */
    private function runComposerUpdate(array $options): void
    {
        $update_command = $this->application->find('update');
        $this->application->resetComposer();
        // Run composer update and capture the exit code.
        $input = new ArrayInput($options);
        $exit_code = $update_command->run($input, $this->output);
        // Check for errors.
        if ($exit_code !== 0) {
            throw new \RuntimeException("Failed to run 'composer update', Could not update dependencies.");
        } else {
            $this->io->write('<info>Composer update completed successfully.</info>');
        }
    }

    /**
     * Reverts composer.json and composer.lock files to their original state.
     */
    private function revertComposerFiles(): void
    {
        $this->io->write("Reverting composer.json and composer.lock files...");
        file_put_contents($this->composerJson->getPath(), $this->composerJsonBackup);
        file_put_contents($this->composerLock->getPath(), $this->composerLockBackup);
        $this->io->write("Composer files reverted successfully.");
        $this->io->write("To restore the vendor folder to its previous state, please run <info>composer install</info>.");
    }
}
