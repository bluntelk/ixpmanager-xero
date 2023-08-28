<?php
/** @var Foil\Template\Template $t */

/** @var $t ->active */

$this->layout( 'layouts/ixpv4' );

$setupMembers = [];
?>

<?php $this->section( 'page-header-preamble' ) ?>
Xero Repeating Invoices
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>
<div class=" btn-group btn-group-sm" role="group">
    <a class="btn btn-white" href="<?= route( 'xero.auth.success' ) ?>" title="Xero Admin">
        <span class="fa fa-arrow-right"></span> Xero Admin
    </a>
    <a class="btn btn-white" href="<?= route( 'ixpxero.line-item.create' ) ?>" title="Add New Mapping">
        <span class="fa fa-plus"></span>
    </a>
</div>
<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<p class="text-info">Current configuration for line items</p>
<?= $t->alerts() ?>
<table class="table table-striped">
    <thead>
    <tr>
        <th>Customer</th>
        <th>Local Service</th>
        <th>Matching Xero Invoice Line</th>
        <th>Action</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach( $t->services as $service ): ?>
        <tr>
            <td><?= $service[ 'customer_name' ] ?></td>
            <td><?= $service[ 'local_service' ] ?></td>
            <td><?= $service[ 'xero_service' ] ?></td>
            <td>
                <div class="btn-group btn-group-sm">
                    <a class="btn btn-primary"
                       href="<?= route( 'ixpxero.line-item.edit', [ 'line_item' => $service[ 'id' ] ] ) ?>">
                        <i class="fa fa-pencil"></i> Edit
                    </a>
                    <a class="btn btn-danger btn-2f-list-delete" title="Delete"
                       href="<?= route( 'ixpxero.line-item.destroy', [ 'line_item' => $service[ 'id' ] ] ) ?>">
                        <i class="fa fa-trash"> Delete</i>
                    </a>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>


    </tbody>
</table>
<?php $this->append() ?>


<?php $this->section( 'scripts' ) ?>
<script>
    $(document).ready(function () {
        $( '.btn-2f-list-delete' ).click( function( event ) {
            event.preventDefault();
            let url = this.href;
            let html = `<form id="d2f-form-delete" method="POST" action="${url}">
                            <div>Do you really want to delete this Xero Line Item Mapping?</div>
                            <input type="hidden" name="_method" value="delete" />
                            <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                        </form>`;

            bootbox.dialog({
                title: "Delete Xero Line Item Mapping",
                message: html,
                buttons: {
                    cancel: {
                        label: 'Close',
                        className: 'btn-secondary',
                        callback: function () {
                            $('.bootbox.modal').modal('hide');
                            return false;
                        }
                    },
                    submit: {
                        label: 'Delete',
                        className: 'btn-danger',
                        callback: function () {
                            $('#d2f-form-delete').submit();
                        }
                    },
                }
            });
        });

    });
</script>
<?php $this->append() ?>
