# Drupal Core Composer Updater Plugin

## What problem does this plugin actually solve?

It removes the "Composer dependency hell" phase of upgrading Drupal core. With
this plugin, you can run a command that effectively says "This is the version of
Drupal core I want to upgrade to, do whatever you need to get me there."

## Features

- **Automatic Version Detection**: Easily upgrade to latest major, next major, latest minor, or specific version of Drupal core.
- **Seamless Updates**: Updates core packages with minimal changes to all other packages.
- **Rollback Mechanism**: Provides a safe fallback by backing up your composer files before making any changes.

## Installation

To install the Drupal Core Composer Updater Plugin, follow these steps:

1. Require the package:

   ```bash
   composer require digitalpolygon/drupal-upgrade-plugin;
   ```

2. Create the manifest file: `<composer root>/drupal_manifest/composer.json`
   with all of your Drupal packages (except dev, those must be kept in the root
   composer.json).

   Example:
   ```json
    {
      "name": "project/drupal-manifest",
      "type": "metapackage",
      "require": {
        "drupal/core-composer-scaffold": "^10.3",
        "drupal/core-project-message": "^10.3",
        "drupal/core-recommended": "^10.3",
        "drupal/search_api": "^1.30",
        "drush/drush": "^12"
      }
    }
   ```

3. Add the following to your `<composer root>/composer.json` repositories
   section:

   ```json
   {
     "type": "path",
     "url": "./drupal_manifest"
   }
   ```

4. Run `composer update --lock`.

Going forward, add Drupal packages to the manifest file.

## Managing requirements in the manifest

Typically when you add new packages, you use `composer require ...` which will
update `composer.json` in your project directory. If you are requiring packages
that are not related to Drupal, this is fine, but if you are requiring a Drupal
package then it is highly recommended that this goes in the manifest file. There
are two methods for doing this:

### Modify requirements from the command line

```bash
composer require drupal/foo:^1.2 --working-dir=drupal_manifest --no-update
composer update drupal/foo
```

### Manually modify requirements

Edit the `drupal_manifest/composer.json` file directly:

```json
{
  "name": "project/drupal-manifest",
  "type": "metapackage",
  "require": {
    "drupal/core-composer-scaffold": "^10.3",
    "drupal/core-project-message": "^10.3",
    "drupal/core-recommended": "^10.3",
    "drupal/search_api": "^1.30",
    "drush/drush": "^12",
    "drupal/foo": "^1.2"
  }
}
```

Then run:

```bash
composer update drupal/foo
```

## Usage

To update your Drupal core to a new version, run the following command in your project root:

```bash
composer drupal:core:version-change 10.3.1;
```

This command will attempt to update all Drupal core packages to 10.3.1 and also
attempt to update any other package in the manifest to a version that would be
compatible with Drupal 10.3.1 (regardless of the constraints you currently have
specified in the manifest).

### Usage with Flags

You can specify the update behavior using the following flags:

2. `--latest-minor`: Update to the latest stable minor version within the currently installed major version of Drupal core.
3. `--latest-major`: Update to the latest stable major version of Drupal core. This option will upgrade your site to the latest available version of Drupal.
4. `--next-major`: Update to the latest stable of the next major version of Drupal core.

## Configuration

The plugin can be configured via the extra section of `composer.json` file in your project root.
All configuration options are optional. The values specified below are the defaults.

```json
{
    "extra": {
        "drupal-upgrade-plugin": {
            "ignore-platform-reqs": false,
            "include-root-dependencies": false,
            "prefer-lowest": true,
            "manifest-file": {
                "path": "./drupal_manifest",
                "name": "project/drupal-manifest"
            },
            "packages-linked-to-core-version": [
                "drupal/core-composer-scaffold",
                "drupal/core-project-message",
                "drupal/core-recommended",
                "drupal/core-dev",
                "drupal/core"
            ]
        },
    }
}
```

**ignore-platform-reqs**

Set to `true` to ignore platform requirements when updating packages.

**include-root-dependencies**

Set to `true` to allow updates of packages in the root composer.json.

**prefer-lowest**

Set to `true` to prefer the lowest version of packages that satisfy the constraints.

**manifest-file.path**

The path to the manifest file repository.

**manifest-file.name**

The name of the manifest file package.

**packages-linked-to-core-version**

A list of packages that share their versioning with Drupal core.

## Contributing

We welcome contributions to enhance the functionality and features of this plugin. Please fork the repository and submit pull requests for any improvements or bug fixes.
