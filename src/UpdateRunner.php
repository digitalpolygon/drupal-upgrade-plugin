<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Composer;
use Composer\IO\IOInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class UpdateRunner implements ApplicationAwareInterface, OutputAwareInterface {

    use ApplicationAwareTrait;
    use OutputAwareTrait;

    public function __construct(
        protected readonly IOInterface $io,
        protected readonly Composer $composer,
        protected readonly Configuration $configuration,
    ) {}

    public function updateCore(string $targetDrupalCoreVersion): int {
        /** @var Configuration $configuration */
        $packages = array_merge([$this->configuration->getManifestPackageName()], array_map(
            fn($package) => $package . ':' . $targetDrupalCoreVersion,
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
        if ($configuration->ignorePlatformReqs()) {
            $parameters['--ignore-platform-reqs'] = true;
        }
        return $this->runComposerUpdate($parameters);
    }

    public function updateLock(): int {
        $this->getApplication()->resetComposer();
        $parameters = ['--lock' => true];
        if ($this->configuration->ignorePlatformReqs()) {
            $parameters['--ignore-platform-reqs'] = true;
        }
        return $this->runComposerUpdate($parameters);
    }

    protected function runComposerUpdate($parameters): int {
        if (!$this->getApplication()) {
            $this->io->writeError('Cannot run update command before access to the Composer application is available.');
            return 1;
        }
        if (!$this->getOutput()) {
            $this->setOutput(new NullOutput());
            $this->io->writeError('<warning>Update command will run but may not output to the console.</warning>');
        }
        if ($this->io->isInteractive() && !array_key_exists('--no-interaction', $parameters)) {
            $parameters['--no-interaction'] = true;
        }
        $update_command = $this->getApplication()->find('update');
        // Run composer update and capture the exit code.
        $input = new ArrayInput($parameters);
        $exit_code = $update_command->run($input, $this->getOutput());
        // Check for errors.
        if ($exit_code !== 0) {
            $this->io->writeError("Failed to run 'composer update', Could not update dependencies.");
            return $exit_code;
        } else {
            $this->io->write('<info>Composer update completed successfully.</info>');
        }
        return 0;
    }
}
