<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Symfony\Component\Console\Input\InputInterface;

interface InputAwareInterface {
    public function setInput(InputInterface $input);
    public function getInput(): InputInterface|null;
}
