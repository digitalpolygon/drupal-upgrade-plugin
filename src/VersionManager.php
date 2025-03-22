<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Package\Package;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use Composer\Util\PackageSorter;
use Composer\Composer;

class VersionManager {
  public function __construct(
    protected readonly Composer $composer,
    protected readonly Package $corePackage,
  ) {}

  public function getLatestMajor(): string|null {
    $current_version = $this->corePackage->getVersion();
    // Define the constraint to limit the search to major versions.
    $constraint = new Constraint('>', $current_version);
    // Find available core package versions that meet the range constraint.
    $available_versions = $this->getAvailableCoreVersions($constraint);
    // Since the versions are sorted, the latest version is the first one in the list.
    return !empty($available_versions) ? current($available_versions) : null;
  }

  public function getLatestMinor() {
    $current_version = $this->corePackage->getVersion();
    // Define the constraint to limit the search to minor versions within the current major version.
    $current_major_version = $this->extractMajorVersion($current_version);
    $next_major_version = ($current_major_version + 1);
    $lower_bound_constraint = new Constraint('>', $current_version);
    $upper_bound_constraint = new Constraint('<', "{$next_major_version}.0.0");
    // Combine lower and upper bound constraints to create a range constraint.
    $range_constraint = new MultiConstraint([$lower_bound_constraint, $upper_bound_constraint], true);
    // Find available core packages versions that meet the range constraint.
    $available_versions = $this->getAvailableCoreVersions($range_constraint);
    // Since the versions are sorted, the latest version is the first one in the list.
    return !empty($available_versions) ? current($available_versions) : null;
  }

  public function getNextMajor() {
    $current_version = $this->corePackage->getVersion();
    // Define the constraint to limit the search to the next major versions.
    $current_major_version = $this->extractMajorVersion($current_version);
    $lower_bound_major_version = ($current_major_version + 1);
    $upper_bound_major_version = ($lower_bound_major_version + 1);
    $lower_bound_constraint = new Constraint('>=', "{$lower_bound_major_version}.0.0");
    $upper_bound_constraint = new Constraint('<', "{$upper_bound_major_version}.0.0");
    // Combine lower and upper bound constraints to create a range constraint.
    $range_constraint = new MultiConstraint([$lower_bound_constraint, $upper_bound_constraint], true);
    // Find available core packages versions that meet the range constraint.
    $available_versions = $this->getAvailableCoreVersions($range_constraint);
    // Since the versions are sorted, the latest version is the first one in the list.
    return !empty($available_versions) ? current($available_versions) : null;
  }

  public function isValidDrupalCoreVersion(string $version): bool {
    $constraint = new Constraint('=', $version);
    $available_versions = $this->getAvailableCoreVersions($constraint);
    return !empty($available_versions);
  }

  protected function getAvailableCoreVersions(ConstraintInterface $constraint): array
  {
    $package_name = $this->corePackage->getName();
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
   * Extracts the major version number from a version string.
   *
   * @param string $version
   *   The version string to extract the major version from (e.g., "8.0.0").
   *
   * @return int
   *   The major version number extracted from the given version string.
   */
  protected function extractMajorVersion(string $version): int
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

}
