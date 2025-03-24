<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Console\Application;

trait ApplicationAwareTrait {

    protected ?Application $application = null;

    public function setApplication(Application $application) {
        $this->application = $application;
    }

    public function getApplication(): Application|null {
        return $this->application;
    }
}
