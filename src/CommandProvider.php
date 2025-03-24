<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use DigitalPolygon\Composer\Drupal\VersionChanger\Commands\ComposerUpdateDrupalCommand;
use DigitalPolygon\Composer\Drupal\VersionChanger\Commands\DetectIssuesCommand;

/**
 * List of all commands provided by this package.
 *
 * @internal
 */
class CommandProvider implements CommandProviderCapability
{
  /**
   * {@inheritdoc}
   */
    public function getCommands()
    {
        return [
            new ComposerUpdateDrupalCommand(),
            new DetectIssuesCommand(),
        ];
    }
}
