<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Symfony\Component\Console\Output\OutputInterface;

interface OutputAwareInterface {
    public function setOutput(OutputInterface $input);
    public function getOutput(): OutputInterface|null;
}
