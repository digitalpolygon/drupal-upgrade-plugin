<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger\Commands;

use Composer\Command\BaseCommand;
use DigitalPolygon\Composer\Drupal\VersionChanger\ApplicationAwareInterface;
use DigitalPolygon\Composer\Drupal\VersionChanger\InputAwareInterface;
use DigitalPolygon\Composer\Drupal\VersionChanger\OutputAwareInterface;
use DigitalPolygon\Composer\Drupal\VersionChanger\Plugin;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class DrupalBaseCommand extends BaseCommand {

    /**
     * {@inheritdoc}
     */
    public function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);
        Plugin::getContainer()
            ->inflector(ApplicationAwareInterface::class)
            ->invokeMethod('setApplication', [$this->getApplication()]);
        Plugin::getContainer()
            ->inflector(OutputAwareInterface::class)
            ->invokeMethod('setOutput', [$output]);
        Plugin::getContainer()
            ->inflector(InputAwareInterface::class)
            ->invokeMethod('setInput', [$input]);
    }
}
