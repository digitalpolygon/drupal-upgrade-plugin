<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;


use Symfony\Component\Console\Output\OutputInterface;

trait OutputAwareTrait {

    protected ?OutputInterface $input = null;

    public function setOutput(OutputInterface $input) {
        $this->input = $input;
    }

    public function getOutput(): OutputInterface|null {
        return $this->input;
    }
}
