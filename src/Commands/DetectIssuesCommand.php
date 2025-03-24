<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger\Commands;

use Composer\Composer;
use DigitalPolygon\Composer\Drupal\VersionChanger\Plugin;
use DigitalPolygon\Composer\Drupal\VersionChanger\VersionManager;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DetectIssuesCommand extends DrupalBaseCommand {

    protected string $targetDrupalCoreVersion;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('drupal:core:detect-upgrade-issues');
        $this->setDescription('Attempt to detect any issues that might prevent a clean upgrade to the target Drupal core version.');
        $this->addArgument('version', InputArgument::OPTIONAL, 'The specific version of Drupal core to update to. If not specified, other options will be considered.');
        $this->addOption('latest-minor', null, InputOption::VALUE_NONE, 'Update to the latest stable minor version within the current major version of Drupal core. This option ensures that you stay within the current major version while applying the latest minor updates.');
        $this->addOption('latest-major', null, InputOption::VALUE_NONE, 'Update to the latest stable major version of Drupal core. This option will upgrade your site to the latest available major version.');
        $this->addOption('next-major', null, InputOption::VALUE_NONE, 'Update to the latest stable of the next major version of Drupal core. This option prepares your site for the next major release.');
    }

    protected function setTargetDrupalCoreVersion(InputInterface $input) {
        /** @var VersionManager $versionManager */
        $versionManager = Plugin::getContainer()->get('versionManager');
        $this->targetDrupalCoreVersion = $versionManager->getTargetDrupalCoreVersionFromInput($input);
    }

    protected function validate(InputInterface $input) {
        $result = $this->validateInput($input);
        if ($result !== 0) {
            return $result;
        }
        $this->setTargetDrupalCoreVersion($input);
        if (!$this->targetDrupalCoreVersion) {
            $this->getIO()->writeError('No target version of Drupal core could be identified.');
            return 4;
        }
        return 0;
    }

    protected function validateInput(InputInterface $input): int {
        $options = $input->getOptions();
        $arguments = $input->getArguments();
        $possibleOptions = ['--latest-minor', '--latest-major', '--next-major'];
        $selectedOptions = array_filter($possibleOptions, fn($option) => $options[ltrim($option, '-')]);
        if (count($selectedOptions) === 0 && !$arguments['version']) {
            $this->getIO()->writeError('You must specify a specific version argument, or one of the following version target options: ' . implode(', ', $possibleOptions));
            return 1;
        }
        if (count($selectedOptions) >= 1 && $arguments['version']) {
            $this->getIO()->writeError('You cannot specify a version argument and a version target option at the same time.');
            return 2;
        }
        if (count($selectedOptions) > 1) {
            $this->getIO()->writeError('You can only specify one of the following version options: ' . implode(', ', $possibleOptions));
            return 3;
        }

        return 0;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int {
        // Go through each manifest package and check for compatibility with the target Drupal core version.
        /** @var Composer $composer */
        $composer = Plugin::getContainer()->get('composer');
        $result = $this->validate($input);
        $manifestPackages = Plugin::getContainer()->get('composerFileManager')->getManifestPackages();
        $this->getIO()->write($manifestPackages);
        $this->getIO()->write("Target Drupal core version: {$this->targetDrupalCoreVersion}");
        return 0;
    }

}
