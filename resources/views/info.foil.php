<?php
/** @var Foil\Template\Template $t */
/** @var $t ->active */

$this->layout( 'layouts/ixpv4' );
?>

<?php $this->section( 'page-header-preamble' ) ?>
    Xero Integration <small>Current Login Information</small>
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>
    <div class=" btn-group btn-group-sm" role="group">
        <a class="btn btn-danger" href="<?= route( 'ixpxero.nuke' ) ?>" title="Nuke Login Info">
            <span class="fa fa-arrow-right"></span> Nuke Xero Login Info
        </a>
        <a class="btn btn-white" href="<?= route( 'xero.auth.success' ) ?>" title="Xero Admin">
            <span class="fa fa-arrow-right"></span> Xero Admin
        </a>
    </div>
<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<?= $t->alerts() ?>
    <p>
        <strong>Note:</strong> If you are trying to diagnose "invalid_redirect" errors, make sure your redirect url contains `/xero/auth/callback`
    </p>
    <table class="table">
        <tr>
            <th>Info Exists</th>
            <td><?= $t->exists ? 'Yes' : 'No' ?></td>
        </tr>
        <tr>
            <th>User</th>
            <td>
                <dl>
                    <dt>Given Name</dt>
                    <dd><?= $t->user[ 'given_name' ] ?></dd>
                    <dt>Family Name</dt>
                    <dd><?= $t->user[ 'family_name' ] ?></dd>
                    <dt>email</dt>
                    <dd><?= $t->user[ 'email' ] ?></dd>
                    <dt>user_id</dt>
                    <dd><?= $t->user[ 'user_id' ] ?></dd>
                    <dt>username</dt>
                    <dd><?= $t->user[ 'username' ] ?></dd>
                </dl>
            </td>
        </tr>
        <tr>
            <th>Tokens</th>
            <td>
                <dl>
                    <dt>Access Token</dt>
                    <dd><?= $t->hasAccessToken ? "Yes" : "No" ?></dd>
                    <dt>Refresh Token</dt>
                    <dd><?= $t->hasRefreshToken ? "Yes" : "No" ?></dd>
                </dl>
            </td>
        </tr>
        <tr>
            <th>Expiry</th>
            <td>
                <dl>
                    <dt>Timestamp</dt>
                    <dd><?= $t->expires ?></dd>
                    <dt>Date/Time</dt>
                    <dd><?= date( 'r', intval( $t->expires ) ) ?></dd>
                    <dt>Is Expired?</dt>
                    <dd><?= $t->isExpired ? "Yes" : "No" ?></dd>
                </dl>
            </td>
        </tr>
    </table>
<?php $this->append() ?>

<?php $this->section( 'scripts' ) ?>

<?php $this->append() ?>