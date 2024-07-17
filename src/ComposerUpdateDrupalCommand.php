<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * This class defines a Composer command to update the Drupal core version.
 *
 * @package DigitalPolygon\Composer\Drupal\VersionChanger
 */
final class ComposerUpdateDrupalCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('drupal:core:version-change');
        $this->setDescription('Upgrade Drupal core to the next available stable version.');
        $this->addArgument('version', InputArgument::OPTIONAL, 'The specific version of Drupal core to update to. If not specified, other options will be considered.');
        $this->addOption('latest-minor', null, InputOption::VALUE_NONE, 'Update to the latest stable minor version within the current major version of Drupal core. This option ensures that you stay within the current major version while applying the latest minor updates.');
        $this->addOption('latest-major', null, InputOption::VALUE_NONE, 'Update to the latest stable major version of Drupal core. This option will upgrade your site to the latest available major version.');
        $this->addOption('next-major', null, InputOption::VALUE_NONE, 'Update to the latest stable of the next major version of Drupal core. This option prepares your site for the next major release.');
        $this->addOption('yes', null, InputOption::VALUE_NONE, 'Automatically confirm the upgrade without prompting for user confirmation. This is useful for scripting and automation purposes.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updater = new DrupalVersionChanger($this->requireComposer(), $this->getApplication(), $input, $output, $this->getIO());
        return $updater->execute();
    }
}
