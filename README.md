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
composer drupal:core:update;
```

This command will perform the following steps:

1. **Backup Composer Files**: Backs up your current `composer.json` and `composer.lock` files.
2. **Determine Current Version**: Detects your current Drupal core version.
3. **Check for Updates**: Finds the next stable Drupal core version.
4. **User Confirmation**: Prompts you to confirm the update.
5. **Update Process**: Updates the `composer.json` with the new version and runs composer update `--minimal-changes`.
6. **Finalize Update**: Replaces wildcard versions with specific caret versions and updates the lock file.

You can also use the `--yes` option to automatically confirm the upgrade without prompting:

```bash
composer drupal:core:update --yes
```

## Contributing

We welcome contributions to enhance the functionality and features of this plugin. Please fork the repository and submit pull requests for any improvements or bug fixes.
