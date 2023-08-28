<?php
/** @var Foil\Template\Template $t */

/** @var $t ->active */

$this->layout( 'layouts/ixpv4' );

$setupMembers = [];
?>

<?php $this->section( 'page-header-preamble' ) ?>
<?php if( $t->item ): ?>
    Edit Xero Line Item Mapping
<?php else: ?>
    New Xero Line Item Mapping
<?php endif ?>
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>
<div class=" btn-group btn-group-sm" role="group">
    <a class="btn btn-white" href="<?= route( 'xero.auth.success' ) ?>" title="Xero Admin">
        <span class="fa fa-arrow-right"></span> Xero Admin
    </a>
</div>
<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<div class="row">
    <div class="col-lg-12">
        <?= Former::open()->method( $t->item ? 'PUT' : 'POST' )
            ->id( "form" )
            ->action( $t->item ? route( 'ixpxero.line-item.update', [ 'line_item' => $t->item->id ] ) : route( 'ixpxero.line-item.store' ) )
            ->rules( [ 'local_service' => 'required' ] )
            ->customInputWidthClass( 'col-sm-6' )
            ->customLabelWidthClass( 'col-sm-3' )
            ->actionButtonsCustomClass( "grey-box" )
        ?>

        <?= Former::select( 'cust_id' )
            ->value($t->item->cust_id ?? null)
            ->label( 'Customer' )
            ->fromQuery( $t->customers, 'name' )
            ->addClass( 'chzn-select' )
            ->blockHelp( "If this is a customer specific line item, please choose the customer it belongs to here." );
        ?>

        <?= Former::text( 'local_service' )
            ->value($t->item->local_service ?? null)
            ->label( 'Local Service' )
            ->placeholder( "vlan-id-10gpbs" )
            ->blockHelp( "This is our locally generated mapping key, it is the VLAN ID (spaces as dashes) lower cased with the speed. <br>" .
                "e.g. `My Awesome VLAN` with a 10 gbps service becomes `my-awesome-vlan-10gbps`" );
        ?>

        <?= Former::select( 'xero_service' )
            ->value($t->item->xero_service ?? null)
            ->fromQuery( $t->xero_services )
            ->addClass( 'chzn-select' )
            ->label( 'Xero Line item' )
            ->blockHelp( "The name you have chosen for the Line Item in Xero" );
        ?>

        <?= Former::actions(
            Former::primary_submit( $t->item ? 'Save Changes' : 'Create' )->class( "mb-2 mb-sm-0" ),
            Former::secondary_link( 'Cancel' )->href( route( 'ixpxero.line-item.index' ) )->class( "mb-2 mb-sm-0" ),
        ); ?>

        <?= Former::close() ?>

        <datalist id="local_services">
            <?php foreach ($t->local_services as $service): ?>
            <option value="<?=$service['local_service']?>"></option>
            <?php endforeach ?>
        </datalist>
    </div>
</div>
<?php $this->append() ?>


<?php $this->section( 'scripts' ) ?>
<script>
    $(document).ready(function () {
        $('#local_service').attr('list', 'local_services')
        $('#xero_service').attr('list', 'xero_line_items')
    });
</script>
<?php $this->append() ?>
