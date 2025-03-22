1. Determine target Drupal core version.
2. Backup composer.json, composer.lock, and drupal_manifest/composer.json files
3. Create temporary copy of drupal_manifest/composer.json, require packages to '*', prepend it as a repository in loaded Composer
4. Update any packages required in loaded composer to wildcard (e.g. drupal/core-dev:*)
5. Run `composer update project/drupal-manifest <drupal/core packages:target version> -w --minimal-changes --prefer-lowest`
6. Restore composer.json, composer.lock, and drupal_manifest/composer.json backup files if update not succesful and kill process with error message.
7. Update version constraints for all packages in drupal_manifest/composer.json:
   1. Restore original constraint if it is compatible with new installed version.
   2. If new version is compatible with a caret constraint, use it with ^major.minor.
   3. If no other matching constraint works, directly specify string.
8. Run `composer update --lock`
