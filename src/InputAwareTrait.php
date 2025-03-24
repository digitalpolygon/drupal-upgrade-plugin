<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Symfony\Component\Console\Input\InputInterface;

trait InputAwareTrait {

    protected ?InputInterface $input = null;

    public function setInput(InputInterface $input) {
        $this->input = $input;
    }

    public function getInput(): InputInterface|null {
        return $this->input;
    }
}
