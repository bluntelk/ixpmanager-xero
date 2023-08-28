# Xero Integration for IXP Manager

## Install Instructions

    composer require bluntelk/ixpmanager-xero


## Setting up Xero Config

Please perform the following config

In your ixpmanager base dir, run (if you haven't already)

    php artisan vendor:publish --tag=config --provider="Webfox\Xero\XeroServiceProvider"
    
## Xero Scopes

This is the config for the package that we are using to handle the integration with Xero. You will need to include the following scopes

    accounting.contacts
    accounting.settings.read
    accounting.transactions.read

The scopes config section may look a little like:

```php
        'scopes'                     => [
            'openid',
            'email',
            'profile',
            'offline_access',
            'accounting.contacts',
            'accounting.settings.read',
            'accounting.transactions.read',
        ],

```

## Integrations

In the config file you can see the client id and client secret config are set from the environment. You can either inject your config into the environment (preferred) or update the config to include the client id and secret provided to you.


## Development
I have used a small composer trick to develop this package

We tell composer to look for our dev dir with our package at the same level

    php composer.phar config repositories.local '{"type": "path", "url": "../ixp-manager-xero"}' --file composer.json

and then we require our package at the tip

    php composer.phar require bluntelk/ixpmanager-xero:dev-master

now you can develop on a freshly checkout out IXP Manager!