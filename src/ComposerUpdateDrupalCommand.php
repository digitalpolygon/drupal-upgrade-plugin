<?php

namespace DigitalPolygon\Composer\Drupal\Upgrader;

use Composer\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

final class ComposerUpdateDrupalCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this->setName('drupal:core:upgrade');
        $this->setDescription('Upgrade Drupal core to the next available stable version.');
        $this->addOption('yes', null, InputOption::VALUE_NONE, 'Automatically confirm the upgrade.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updater = new DrupalUpgrader($this->requireComposer(), $this->getApplication(), $input, $output, $this->getIO());
        return $updater->execute();
    }
}
