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

The scopes config section may look a little like:

```php
        'scopes'                     => [
            'openid',
            'email',
            'profile',
            'offline_access',
            'accounting.contacts',
            'accounting.settings.read',
        ],

```

## Integrations

In the config file you can see the client id and client secret config are set from the environment. You can either inject your config into the environment (preferred) or update the config to include the client id and secret provided to you.