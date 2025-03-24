<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Composer;

class Configuration {
    protected readonly bool $ignorePlatformReqs;
    protected readonly array $drupalCoreVersionLinkedPackages;
    protected readonly string $manifestFileRepositoryPath;
    protected readonly string $manifestPackageName;
    protected readonly bool $includeRootDependencies;
    protected readonly bool $preferLowest;
    protected readonly bool $noPlugins;
    protected readonly bool $noScripts;

    public function __construct(protected readonly Composer $composer) {
        $extra = $this->composer->getPackage()->getExtra();
        $pluginConfiguration = $extra['drupal-upgrade-plugin'] ?? [];
        $this->includeRootDependencies = $pluginConfiguration['include-root-dependencies'] ?? false;
        $this->ignorePlatformReqs = $pluginConfiguration['ignore-platform-reqs'] ?? false;
        $this->drupalCoreVersionLinkedPackages = $pluginConfiguration['packages-linked-to-core-version'] ?? [
            'drupal/core-composer-scaffold',
            'drupal/core-project-message',
            'drupal/core-recommended',
            'drupal/core-dev',
            'drupal/core',
        ];
        $this->manifestFileRepositoryPath = $pluginConfiguration['manifest-file']['path'] ?? './drupal_manifest';
        $this->manifestPackageName = $pluginConfiguration['manifest-file']['name'] ?? 'project/drupal-manifest';
        $this->preferLowest = $pluginConfiguration['prefer-lowest'] ?? true;
    }

    public function getDrupalCoreVersionLinkedPackages() {
        $presentDrupalCorePackages = [];
        foreach ($this->drupalCoreVersionLinkedPackages as $package) {
            if ($this->composer->getRepositoryManager()->getLocalRepository()->findPackage($package, '*')) {
                $presentDrupalCorePackages[] = $package;
            }
        }
        return $presentDrupalCorePackages;
    }

    public function includeRootDependencies(): bool {
        return $this->includeRootDependencies;
    }

    public function ignorePlatformReqs(): bool {
        return $this->ignorePlatformReqs;
    }

    public function getManifestFileRepositoryPath(): string {
        return $this->manifestFileRepositoryPath;
    }

    public function getManifestPackageName(): string {
        return $this->manifestPackageName;
    }

    public function preferLowest(): bool {
        return $this->preferLowest;
    }
}
