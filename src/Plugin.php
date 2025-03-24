<?php

namespace DigitalPolygon\Composer\Drupal\VersionChanger;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Util\Filesystem;
use DigitalPolygon\Composer\Drupal\VersionChanger\CommandProvider as UpgradeDrupalCommandProvider;
use League\Container\Container;

/**
 * Composer plugin for handling drupal upgrades.
 *
 * @internal
 */
class Plugin implements PluginInterface, Capable
{
    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    protected static Container $container;

    public static function getContainer(): Container {
      return self::$container;
    }

    public static function configureContainer(Composer $composer, IOInterface $io) {
      $container = new Container();
      $container->add('filesystem', Filesystem::class);
      $container->addShared('composer', $composer);
      $container->addShared('io', $io);
      $container->addShared('versionManager', VersionManager::class)
        ->addArgument('composer')
        ->addArgument('installedCorePackage');
      $container->addShared(
        'installedCorePackage',
        $composer
          ->getRepositoryManager()
          ->getLocalRepository()
          ->findPackage('drupal/core', '*')
      );
      $container->addShared('composerFileManager', ComposerFileManager::class)
        ->addArgument('composer')
        ->addArgument('filesystem')
        ->addArgument('io')
        ->addArgument('composerManipulator');
      $container->addShared('composerManipulator', ComposerManipulator::class)
        ->addArgument('composer');
      $container->addShared('configuration', Configuration::class)
        ->addArgument('composer');
      static::$container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
        static::configureContainer($composer, $io);
    }

    /**
     * {@inheritdoc}
     */
    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getCapabilities()
    {
        return [CommandProvider::class => UpgradeDrupalCommandProvider::class];
    }

}
