<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Console\Application;

interface ApplicationAwareInterface {
    public function setApplication(Application $application);
    public function getApplication(): Application|null;
}
