<?php
/** @var Foil\Template\Template $t */
/** @var $t ->active */

$this->layout( 'layouts/ixpv4' );
?>

<?php $this->section( 'page-header-preamble' ) ?>
    Xero Integration
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>

<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<?php if( $t->error ): ?>
    <h1>Your connection to Xero failed</h1>
    <p><?= $t->error ?></p>
    <a href="<?= route( 'xero.auth.authorize' ) ?>" class="btn btn-primary btn-large mt-4">
        Reconnect to Xero
    </a>
    <pre><?= $t->errorExtra ?></pre>
<?php elseif( $t->incorrectSetup ): ?>
    <h1>Please setup Xero Config</h1>
    <h2>Setting up Xero Config</h2>
    <p>Please perform the following config</p>
    <p>In your ixpmanager base dir, run (if you haven't already)</p>
    <code style="padding-bottom: 20px">
        php artisan vendor:publish --tag=config --provider="Webfox\Xero\XeroServiceProvider"<br/>
    </code>
    <h2>Xero Scopes</h2>
    <p>This is the config for the package that we are using to handle the integration with Xero. You will need to include the following scopes</p>
    <ul>
        <li>accounting.contacts</li>
        <li>accounting.settings.read</li>
    </ul>
    <p>The scopes config section may look a little like:</p>
    <pre class="border">
        'scopes'                     => [
            'openid',
            'email',
            'profile',
            'offline_access',
            'accounting.contacts',
            'accounting.settings.read',
        ],</pre>
    <h2>Integrations</h2>
    <p>In the config file you can see the client id and client secret config are set from the environment. You can
        either inject your config into the environment (preferred) or update the config to include the client id and
        secret provided to you.</p>
    <p>
        <a href="<?= route( 'xero.auth.success' ) ?>" class="btn btn-primary btn-lg active" role="button" aria-pressed="true">I have updated my config, let me continue!</a>
    </p>
<?php elseif( $t->connected ): ?>
    <h1>You are connected to Xero</h1>
    <p><?= $t->organisationName ?> via <?= $t->username ?></p>
    <h2>Administration Actions</h2>
    <div class="btn-group mb-4">
        <a href="<?= route( 'xero.auth.authorize' ) ?>" class="btn btn-primary btn-large mt-4">
            Reconnect to Xero
        </a>
    </div>

    <h2>Sync Actions</h2>
    <div class="btn-group mb-4">
        <a href="<?= route( 'xero.sync' ) ?>" class="btn btn-secondary btn-large mt-4">
            View Sync Actions
        </a>
        <a href="<?= route( 'xero.repeating.invoices' ) ?>" class="btn btn-secondary btn-large mt-4">
            Invoice Thingy
        </a>
    </div>

<?php else: ?>
    <h1>You are not connected to Xero</h1>
    <a href="<?= route( 'xero.auth.authorize' ) ?>" class="btn btn-primary btn-large mt-4">
        Connect to Xero
    </a>
<?php endif ?>
<?php $this->append() ?>


<?php $this->section( 'scripts' ) ?>
    <script>
        $(document).ready(function () {


        });
    </script>
<?php $this->append() ?>