<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Composer;
use Composer\Factory;
use Composer\Json\JsonFile;
use Composer\Package\Package;
use Composer\Package\Version\VersionSelector;
use Composer\Repository\RepositorySet;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Composer\Util\Filesystem;
use Composer\Repository\PathRepository;
use Composer\IO\IOInterface;

class ComposerFileManager {

  protected JsonFile $composerFile;
  protected JsonFile $composerLockFile;
  protected JsonFile $manifestFile;
  protected JsonFile $backupManifestFile;
  protected ?JsonFile $wildcardManifestFile = null;
  protected JsonFile $backupComposerFile;
  protected JsonFile $backupComposerLockFile;
  protected bool $backedUp = false;

  public function __construct(
    protected readonly Composer $composer,
    protected readonly Filesystem $filesystem,
    protected readonly IOInterface $io,
    protected readonly ComposerManipulator $composerManipulator,
  ) {
    $composerFilePath = Factory::getComposerFile();
    $this->composerFile = new JsonFile($composerFilePath);
    $this->composerLockFile = new JsonFile(Factory::getLockFile($composerFilePath));
    $this->manifestFile = new JsonFile('./drupal_manifest/composer.json');
  }

  public function backupFiles(): void {
    if (!$this->backedUp) {
      $backupComposerDir = tempnam(sys_get_temp_dir(), 'backup_composer_dir_');
      $backupComposerFilePath = $backupComposerDir . '/composer.json';
      $this->filesystem->remove($backupComposerDir);
      $this->filesystem->ensureDirectoryExists($backupComposerDir);
      $this->backupComposerFile = new JsonFile($backupComposerFilePath);
      $this->backupComposerFile->write($this->composerFile->read());

      $backupComposerLockDir = tempnam(sys_get_temp_dir(), 'backup_composer_lock_dir_');
      $backupComposerLockFilePath = $backupComposerLockDir . '/composer.lock';
      $this->filesystem->remove($backupComposerLockDir);
      $this->filesystem->ensureDirectoryExists($backupComposerLockDir);
      $this->backupComposerLockFile = new JsonFile($backupComposerLockFilePath);
      $this->backupComposerLockFile->write($this->composerLockFile->read());

      $backupManifestDir = tempnam(sys_get_temp_dir(), 'backup_manifest_dir_');
      $backupManifestPath = $backupManifestDir . '/composer.json';
      $this->filesystem->remove($backupManifestDir);
      $this->filesystem->ensureDirectoryExists($backupManifestDir);
      $this->backupComposerLockFile = new JsonFile($backupManifestPath);
      $this->backupComposerLockFile->write($this->manifestFile->read());

      $this->backedUp = true;
      $this->io->write('<info>Backed up composer.json, composer.lock, and drupal_manifest/composer.json files.</info>');
    }
  }

  public function getWildcardManifestRepository(): PathRepository {
    if (!$this->wildcardManifestFile) {
      $wildcardManifestDir = tempnam(sys_get_temp_dir(), 'wildcard_manifest_');
      $wildcardManifestFile = $wildcardManifestDir . '/composer.json';
      $this->filesystem->remove($wildcardManifestDir);
      $this->filesystem->ensureDirectoryExists($wildcardManifestDir);
      $this->wildcardManifestFile = new JsonFile($wildcardManifestFile);
      $wildcardManifest = $this->manifestFile->read();
      foreach ($wildcardManifest['require'] as $package => $version) {
        $wildcardManifest['require'][$package] = '*';
      }
      $this->wildcardManifestFile->write($wildcardManifest);
    }

    return new PathRepository([
      'type' => 'path',
      'url' => dirname($this->wildcardManifestFile->getPath()),
    ], $this->io, $this->composer->getConfig());
  }

  public function restoreFiles(): void {
    if ($this->backedUp) {
      $this->composerFile->write($this->backupComposerFile->read());
      $this->composerLockFile->write($this->backupComposerLockFile->read());
      $this->manifestFile->write($this->backupManifestFile->read());
      $this->io->write('<info>Restored composer.json, composer.lock, and drupal_manifest/composer.json files. Run composer install to ensure packages are properly restored.</info>');
    }
  }

  public function getManifestFile(): JsonFile {
    return $this->manifestFile;
  }

  public function updatePackageRequirementsForRootAndManifest(): void {
    $composerRoot = $this->composerFile->read();
    $manifestFile = $this->manifestFile->read();
    $drupalCorePackages = $this->composerManipulator->getPresentDrupalCoreRootPackages();
    $manifestFileRequiredPackages = array_keys($manifestFile['require']);
    $currentConstrainedVersions = array_merge($composerRoot['require'], $composerRoot['require-dev'], $manifestFile['require']);
    $currentInstalledVersions = [];
    $updatedConstrainedVersions = [];
    $allPackages = array_merge($drupalCorePackages, $manifestFileRequiredPackages);
    foreach ($allPackages as $packageName) {
      $package = $this->composer->getRepositoryManager()->getLocalRepository()->findPackage($packageName, '*');
      if ($package) {
        $currentInstalledVersions[$packageName] = $package;
      }
    }
    foreach ($currentInstalledVersions as $packageName => $package) {
      $newVersion = $this->getPreferredConstrainedVersion($package, $currentConstrainedVersions);
      $updatedConstrainedVersions[$packageName] = $newVersion;
    }
    foreach (['require', 'require-dev'] as $requireType) {
      if (isset($composerRoot[$requireType])) {
        foreach ($composerRoot[$requireType] as $packageName => $version) {
          if (isset($updatedConstrainedVersions[$packageName])) {
            $composerRoot[$requireType][$packageName] = $updatedConstrainedVersions[$packageName];
          }
        }
      }
      if (isset($manifestFile[$requireType])) {
        foreach ($manifestFile[$requireType] as $packageName => $version) {
          if (isset($updatedConstrainedVersions[$packageName])) {
            $manifestFile[$requireType][$packageName] = $updatedConstrainedVersions[$packageName];
          }
        }
      }
    }
    $this->composerFile->write($composerRoot);
    $this->manifestFile->write($manifestFile);
  }

  /**
   * Use the same constraint if possible. Otherwise, use a new constraint.
   *
   * @param \Composer\Package\Package $package
   * @param array $currentConstrainedVersions
   *
   * @return string
   */
  protected function getPreferredConstrainedVersion(Package $package, array $currentConstrainedVersions): string
  {
    $packageName = $package->getName();
    $version = $package->getVersion();
    $currentConstraint = $currentConstrainedVersions[$packageName] ?? null;
    if ($currentConstraint) {
      if (Semver::satisfies($version, $currentConstraint)) {
        return $currentConstraint;
      }
    }
    $versionSelector = new VersionSelector(new RepositorySet());
    return $versionSelector->findRecommendedRequireVersion($package);
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
//  protected function generateCaretVersion(string $version): string
//  {
//    if (substr($version, 0, 4) === 'dev-') {
//      return $version;
//    }
//    $version_parser = new VersionParser();
//    $normalized = $version_parser->normalize($version);
//    $parts = explode('.', $normalized);
//    // Ensure there are at least two parts (major and minor versions).
//    if (count($parts) >= 2) {
//      return '^' . $parts[0] . '.' . $parts[1];
//    }
//    // Default to returning the original version prefixed with '^'.
//    return '^' . $version;
//  }

}
