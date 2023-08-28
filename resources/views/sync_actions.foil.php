<?php
/** @var Foil\Template\Template $t */
/** @var $t ->active */

$this->layout( 'layouts/ixpv4' );
?>

<?php $this->section( 'page-header-preamble' ) ?>
    Xero Sync Actions
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>
<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<?php if( $t->error ): ?>
    <h1>Unable to perform sync</h1>
    <p><?= $t->error ?></p>
    <a href="<?= route( 'ixpxero.sync' ) ?>" class="btn btn-primary btn-large mt-4">
        Try Again
    </a>
    <pre><?= $t->errorExtra ?></pre>
<?php else: ?>
    <h1>Sync Actions</h1>
    <?php if( $t->performing ): ?>
        <div class="alert alert-info">The sync has been performed, the results are below</div>
    <?php endif; ?>
    <p>The following actions will be performed if you click the sync button below.</p>
    <p>
        <a class="btn btn-primary" href="<?= route( 'ixpxero.sync', [ 'perform' => 'yes' ] ) ?>">Perform Manual Sync</a>
    </p>
    <table class="table table-bordered table-condensed table-hover table-striped">
        <thead>
        <tr>
            <th>Customer</th>
            <th>Action</th>
            <th>Performed?</th>
            <th>Worked?</th>
            <th>Errors</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach( $t->actions as $action ): ?>
            <tr>
                <td><?= $action->customer->name ?></td>
                <td><?= $action->action ?></td>
                <td><?= $action->performed ? 'Yes' : 'No' ?></td>
                <td><?= $action->performed ? ($action->failed ? 'No' : 'Yes') : '' ?></td>
                <td><?= implode('<br>', $action->errors) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif ?>
<?php $this->append() ?>


<?php $this->section( 'scripts' ) ?>
    <script>
        $(document).ready(function () {


        });
    </script>
<?php $this->append() ?>