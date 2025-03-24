<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Composer;
use Composer\Package\Link;
use Composer\Semver\Constraint\MatchAllConstraint;

class ComposerManipulator {

  public function __construct(
      protected readonly Composer $composer,
      protected readonly Configuration $configuration,
  ) {}

  /**
   * Convert core packages to matchall constraint in loaded composer.
   *
   * This does not manipulate the composer.json file.
   *
   * @return void
   */
  public function convertCorePackagesToWildcards(): void {
    $this->convertPackagesToWildcards($this->getPresentDrupalCoreRootPackages());
  }

  /**
   * Convert packages to matchall constraint in loaded composer.
   *
   * This does not manipulate the composer.json file.
   *
   * @param array $packages
   *   List of packages to convert to matchall constraint.
   *
   * @return void
   */
  public function convertPackagesToWildcards(array $packages): void {
    $devRequires = $this->composer->getPackage()->getDevRequires();
    $requires = $this->composer->getPackage()->getRequires();
    foreach ($packages as $packageName) {
      $package = $devRequires[$packageName] ?? $requires[$packageName] ?? null;
      if ($package) {
        $replaceWith = new Link($package->getSource(), $package->getTarget(), new MatchAllConstraint(), $package->getDescription(), '*');
        if (isset($devRequires[$packageName])) {
          unset($devRequires[$packageName]);
          $devRequires[$packageName] = $replaceWith;
        }
        else {
          unset($requires[$packageName]);
          $requires[$packageName] = $replaceWith;
        }
      }
    }
    $this->composer->getPackage()->setDevRequires($devRequires);
    $this->composer->getPackage()->setRequires($requires);
  }

    /**
     * Get list of Drupal core packages present in loaded composer.
     *
     * @return array
     */
    public function getPresentDrupalCoreRootPackages(): array {
        $presentDrupalCorePackages = [];
        $devRequires = $this->composer->getPackage()->getDevRequires();
        $requires = $this->composer->getPackage()->getRequires();
        foreach ($devRequires as $packageName => $package) {
            if (in_array($packageName, $this->configuration->getDrupalCoreVersionLinkedPackages())) {
                $presentDrupalCorePackages[] = $packageName;
            }
        }
        foreach ($requires as $packageName => $package) {
            if (in_array($packageName, $this->configuration->getDrupalCoreVersionLinkedPackages())) {
                $presentDrupalCorePackages[] = $packageName;
            }
        }
        return $presentDrupalCorePackages;
    }
}
