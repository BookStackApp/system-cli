# BookStack System CLI

A simple command line interface for managing instances of BookStack. Provides the following commands:

- **Init** - Setup a fresh BookStack installation within a folder.
- **Backup** - Creates a backup of an existing BookStack installation to a single ZIP file.
- **Restore** - Restore a backup ZIP into an instance of BookStack.
- **Update** - Update an existing BookStack installation to the latest version.

This CLI is intended to be platform abstract, intended for plain installs that follow our scripts/manual instructions.
This is intended to work independently from BookStack itself, so it can be used even if a BookStack instance is not available or broken, although it could be distributed with and called upon by the core BookStack codebase.

### Development

This project uses composer to manage PHP dependencies. They can be installed as follows:

```bash
composer install
```

This project is intended to be bundled up into a single [phar file](https://www.php.net/manual/en/intro.phar.php) for portability and separation with BookStack itself.
This can be done by running the compile file:

```bash
php compile.php
```

### Contributing

I welcome issues and PRs but keep in mind that I'd like to keep the feature-set narrow to limit support/maintenance burden.
Therefore I likely won't leave issues open long, or merge PRs, for requests to add new features or for changes that increase the scope of what this script already supports.