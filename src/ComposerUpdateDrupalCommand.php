<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Command\BaseCommand;
use Composer\IO\ConsoleIO;
use Composer\Semver\VersionParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * This class defines a Composer command to update the Drupal core version.
 *
 * @package DigitalPolygon\Composer\Drupal\VersionChanger
 */
final class ComposerUpdateDrupalCommand extends BaseCommand
{

  protected string $targetDrupalCoreVersion;

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('drupal:core:version-change');
        $this->setDescription('Upgrade Drupal core packages to a new version. Other packages are upgraded if necessary.');
        $this->addArgument('version', InputArgument::OPTIONAL, 'The specific version of Drupal core to update to. If not specified, other options will be considered.');
        $this->addOption('latest-minor', null, InputOption::VALUE_NONE, 'Update to the latest stable minor version within the current major version of Drupal core. This option ensures that you stay within the current major version while applying the latest minor updates.');
        $this->addOption('latest-major', null, InputOption::VALUE_NONE, 'Update to the latest stable major version of Drupal core. This option will upgrade your site to the latest available major version.');
        $this->addOption('next-major', null, InputOption::VALUE_NONE, 'Update to the latest stable of the next major version of Drupal core. This option prepares your site for the next major release.');
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

    protected function setTargetDrupalCoreVersion(InputInterface $input) {
      $options = $input->getOptions();
      $arguments = $input->getArguments();
      /** @var \DigitalPolygon\Composer\Drupal\VersionChanger\VersionManager $versionManager */
      $versionManager = Plugin::getContainer()->get('versionManager');
      if ($arguments['version']) {
        $this->targetDrupalCoreVersion = $arguments['version'];
      }
      if ($options['latest-minor']) {
        $this->targetDrupalCoreVersion = $versionManager->getLatestMinor();
      }
      if ($options['latest-major']) {
        $this->targetDrupalCoreVersion = $versionManager->getLatestMajor();
      }
      if ($options['next-major']) {
        $this->targetDrupalCoreVersion = $versionManager->getNextMajor();
      }
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

    protected function runComposerPackageUpdates(OutputInterface $output): int {
      /** @var Configuration $configuration */
      $configuration = Plugin::getContainer()->get('configuration');
      $packages = array_merge([$configuration->getManifestPackageName()], array_map(
        fn($package) => $package . ':' . $this->targetDrupalCoreVersion,
        $configuration->getDrupalCoreVersionLinkedPackages(),
      ));
      $parameters = [
        'packages' => $packages,
        '--minimal-changes' => true,
      ];
      if ($configuration->includeRootDependencies()) {
        $parameters['-W'] = true;
      }
      else {
        $parameters['-w'] = true;
      }
      if ($configuration->preferLowest()) {
        $parameters['--prefer-lowest'] = true;
      }
      return $this->runComposerUpdate($parameters, $output);
    }

    protected function runComposerUpdate($parameters, OutputInterface $output): int {
      if ($this->getIO()->isInteractive() && !array_key_exists('--no-interaction', $parameters)) {
        $parameters['--no-interaction'] = true;
      }
      $update_command = $this->getApplication()->find('update');
      // Run composer update and capture the exit code.
      $input = new ArrayInput($parameters);
      $exit_code = $update_command->run($input, $output);
      // Check for errors.
      if ($exit_code !== 0) {
        $this->getIO()->writeError("Failed to run 'composer update', Could not update dependencies.");
        return $exit_code;
      } else {
        $this->getIO()->write('<info>Composer update completed successfully.</info>');
      }
      return 0;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
      $validateResult = $this->validate($input);
      if ($validateResult !== 0) {
        $this->getIO()->writeError('<error>Failed to validate Drupal core upgrade prerequisites.</error>');
        return $validateResult;
      }
      $container = Plugin::getContainer();
      /** @var \Composer\Composer $composer */
      $composer = $container->get('composer');
      /** @var \DigitalPolygon\Composer\Drupal\VersionChanger\ComposerFileManager $composerFileManager */
      $composerFileManager = $container->get('composerFileManager');
      /** @var \DigitalPolygon\Composer\Drupal\VersionChanger\ComposerManipulator $composerManipulator */
      $composerManipulator = $container->get('composerManipulator');
      $composerFileManager->backupFiles();
      try {
        $manifestWildcardRepo = $composerFileManager->getWildcardManifestRepository();
        $composer->getRepositoryManager()->prependRepository($manifestWildcardRepo);
        $composerManipulator->convertCorePackagesToWildcards();
        $result = $this->runComposerPackageUpdates($output);
        if ($result !== 0) {
          $this->getIO()->writeError('<error>Failed to update Drupal core and manifest packages.</error>');
          $composerFileManager->restoreFiles();
          return $result;
        }
        $composerFileManager->updatePackageRequirementsForRootAndManifest();
        $this->getApplication()->resetComposer();
        $result = $this->runComposerUpdate(['--lock' => true], $output);
        if ($result !== 0) {
          $this->getIO()->writeError('<error>Failed to update composer.lock file.</error>');
          $composerFileManager->restoreFiles();
          return $result;
        }
        return Command::SUCCESS;
      }
      catch (\Exception $e) {
        $composerFileManager->restoreFiles();
        $this->getIO()->writeError("<error>{$e->getMessage()}</error>");
        return Command::FAILURE;
      }
    }
}
