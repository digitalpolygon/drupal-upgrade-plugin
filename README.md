# Drupal Core Composer Updater Plugin

A Composer plugin designed to streamline the process of updating Drupal core to the latest stable version. This tool ensures your Drupal site remains secure and up-to-date with minimal hassle, leveraging Composer's capabilities to make the update process smooth and efficient.

## Features

- **Automatic Version Detection**: Identifies your current Drupal core version and the next available stable release.
- **Seamless Updates**: Updates core packages with minimal changes to dependencies using `composer update --minimal-changes`.
- **User Confirmation**: Prompts for user confirmation before proceeding with the upgrade, ensuring you're always in control.
- **Rollback Mechanism**: Provides a safe fallback by backing up your composer files before making any changes.
- **Dependency Management**: Handles version constraints and updates for required and required-dev packages efficiently.

## Installation

To install the Drupal Core Composer Updater Plugin, follow these steps:

1. **Add Plugin Repository**: Add the plugin GitHub repository to the repositories section in your project's `composer.json` file.

   ```json
   {
       "repositories": [
           {
               "type": "vcs",
               "url": "git@github.com:digitalpolygon/drupal-upgrade-plugin.git"
           }
       ]
   }
   ```

1. **Require the Plugin**: Add the plugin to your project's `composer.json` file.

   ```bash
   composer require digitalpolygon/drupal-upgrade-plugin;
   ```

## Usage

To update your Drupal core to the latest stable version, run the following command in your project root:

```bash
composer drupal:core:version-change 10.3.1;
```

This command will perform the following steps:

1. **Backup Composer Files**: Backs up your current `composer.json` and `composer.lock` files.
2. **Determine Current Version**: Detects your current Drupal core version.
3. **Check for Updates**: Finds the next stable Drupal core version.
4. **User Confirmation**: Prompts you to confirm the update.
5. **Update Process**: Updates the `composer.json` with the new version and runs composer update `--minimal-changes`.
6. **Finalize Update**: Replaces wildcard versions with specific caret versions and updates the lock file.

### Usage with Flags

You can specify the update behavior using the following flags:

1. `--version=<version>`: The specific version of Drupal core to update to. If not specified, other options will be considered.
2. `--latest-minor`: Update to the latest stable minor version within the current major version of Drupal core. This option ensures that you stay within the current major version while applying the latest minor updates.
3. `--latest-major`: Update to the latest stable major version of Drupal core. This option will upgrade your site to the latest available major version.
4. `--next-major`: Update to the latest stable of the next major version of Drupal core. This option prepares your site for the next major release.

You can also use the `--yes` option to automatically confirm the upgrade without prompting:

```bash
composer drupal:core:version-change 10.3.1 --yes;
```
This option is useful for scripting and automation purposes.

## Contributing

We welcome contributions to enhance the functionality and features of this plugin. Please fork the repository and submit pull requests for any improvements or bug fixes.
