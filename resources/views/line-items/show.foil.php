<?php
/** @var Foil\Template\Template $t */

/** @var $t ->active */

use IXP\Models\Customer;
use XeroAPI\XeroPHP\Models\Accounting\RepeatingInvoice;

$this->layout( 'layouts/ixpv4' );

$setupMembers = [];
?>

<?php $this->section( 'page-header-preamble' ) ?>
Show Line Item Mapping
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>
<div class=" btn-group btn-group-sm" role="group">
    <a class="btn btn-white" href="<?= route( 'xero.auth.success' ) ?>" title="Xero Admin">
        <span class="fa fa-arrow-right"></span> Xero Admin
    </a>
</div>
<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<?= $t->alerts() ?>

<?php $this->append() ?>


<?php $this->section( 'scripts' ) ?>
<script>
    $(document).ready(function () {


    });
</script>
<?php $this->append() ?>
