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
        $packages = array_merge([$this->configuration->getManifestPackageName()], array_map(
            fn($package) => $package . ':' . $targetDrupalCoreVersion,
            $this->configuration->getDrupalCoreVersionLinkedPackages(),
        ));
        $parameters = [
            'packages' => $packages,
            '--minimal-changes' => true,
        ];
        if ($this->configuration->includeRootDependencies()) {
            $parameters['-W'] = true;
        }
        else {
            $parameters['-w'] = true;
        }
        if ($this->configuration->preferLowest()) {
            $parameters['--prefer-lowest'] = true;
        }
        $parameters = array_merge($parameters, $this->commonParameters());
        return $this->runComposerUpdate($parameters);
    }

    public function updateLock(): int {
        $this->getApplication()->resetComposer();
        $parameters = array_merge(['--lock' => true], $this->commonParameters());
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

    protected function commonParameters(): array {
        $parameters = [];
        if ($this->io->isInteractive()) {
            $parameters['--no-interaction'] = true;
        }
        if ($this->configuration->noScripts()) {
            $parameters['--no-scripts'] = true;
        }
        if ($this->configuration->noPlugins()) {
            $parameters['--no-plugins'] = true;
        }
        if ($this->configuration->ignorePlatformReqs()) {
            $parameters['--ignore-platform-reqs'] = true;
        }
        return $parameters;
    }
}
