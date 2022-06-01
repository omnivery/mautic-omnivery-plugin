# Omnivery API plugin for Mautic v4

Plugin provides integration with Omnivery so you can send email messages from Mautic via API using your domains.

**Main Features**

- Sending emails via Omnivery API.

### Prerequisites

- Project was tested on Mautic 4.3.1
- During development having composer setup can be handy to run scripts in `composer.json`.

### Installing

Move to plugins directory of your Mautic installation & clone repository.

```bash
cd <mautic-dir>/plugins
git clone <repo-url> MauticOmniveryMailerBundle
cd MauticOmniveryMailerBundle
composer install
```

Create plugin enviorment file to specifiy your global config. Parameters for configruation can be found in your Omnivery Account.

```bash
cp plugin-env.php.example plugin-env.php
# edit plugin-env.php with values from your account.
```

Install/reload the plugin

```bash
cd <mautic-dir>
rm -rf var/cache/dev/* var/cache/prod/*
php bin/console mautic:plugins:install --env=dev  # Use mautic:plugins:reload --env=dev for update
```

## Running the tests

\[todo\]

### Coding style & Syntax Check

Coding style can be checked and fixed with the following commands

```bash
composer lint  # Syntax Check
composer checkcs  # Code style check
composer fixcs  # Code style fix
```

## Deployment

Pretty much the same as installing procedure only make sure you use `--env=prod` switch when installing on production.

## Documentation

- Choose Omnivery Api as the mail service, in Mautic Configuration > Email Settings. Enter default host (domain) and api key. The details you set on this tab will be used if configuration for specific domain cannot be found.

**Always double check that plugin is selecting expected Omnivery domain when you add new Omnivery host.**

## Built With

- [Mautic](https://github.com/mautic/mautic) - Marketing Automation Tool
- [Composer](https://getcomposer.org/) - Dependency Management

## Contributing

- If you have a suggestion for the feature or improvement consider opening an issue on GitHub (just make sure the same issue does not already exists).
- If you want, you can open a pull request and I will make an effort to merge it.
- Finally if this project was helpful to you **consider supporting it with a donation** via [PayPal](https://paypal.me/maticzagmajster). **Thank you!**

## Versioning

This project is using [Semantic Versioning](https://semver.org/).

## Authors

This project was adapated & is maintained by [Matic Zagmajster](http://maticzagmajster.ddns.net/). For more information please see `AUTHORS` file.

## License

This project is licensed under the GPL-3.0 License see the `LICENSE` file for details.

