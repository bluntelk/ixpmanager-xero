<?php
/** @var Foil\Template\Template $t */

/** @var $t ->active */

use IXP\Models\Customer;
use XeroAPI\XeroPHP\Models\Accounting\RepeatingInvoice;

$this->layout( 'layouts/ixpv4' );

$setupMembers = [];
?>

<?php $this->section( 'page-header-preamble' ) ?>
Xero Repeating Invoices
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>
<a class="btn btn-white btn-sm" href="<?= route( 'xero.auth.success' ) ?>" title="Xero Admin">
    <span class="fa fa-arrow-right"></span> Xero Admin
</a>
<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<?= $t->alerts() ?>
<p class="text-info">Showing all FULL Customers</p>
<table class="table table-striped">
    <thead>
    <tr>
        <th>Customer</th>
        <th>Customer Type</th>
        <th>Date Joined</th>
        <th>Services</th>
        <th>Needs Billing</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach( $t->bills as $bill ): ?>
        <tr>
            <td>
                <a href="<?= route('xero.repeating.invoices.customer', ['customer_id' => $bill['customer']->id]) ?>">
                    <?= $bill['customer']->name ?></a>
            </td>
            <td><?= $bill['customer']->type() ?></td>
            <td><?= $bill['customer']->datejoin->format('Y-m-d') ?></td>
            <td>
                <table class="table">
                <?php foreach( $bill['services'] as $service ): ?>
                <tr>
                    <td><?= $service->vlan_name ?></td>
                    <td class="text-right mono"><?= $service->speed / 1000 ?>gbps</td>
                    <td><?= $service->location_name ?></td>
                </tr>
                <?php endforeach; ?>
                </table>
            </td>
            <td>
                <table class="table">
                <?php foreach( $bill['servicesNeedingBilling'] as $service ): ?>
                <tr>
                    <td><?= $service->vlan_name ?></td>
                    <td><?= $service->speed / 1000 ?>gbps</td>
                    <td><?= $service->location_name ?></td>
                </tr>
                <?php endforeach; ?>
                </table>
            </td>
        </tr>
    <?php endforeach; ?>


    </tbody>
</table>
<?php $this->append() ?>


<?php $this->section( 'scripts' ) ?>
<script>
    $(document).ready(function () {


    });
</script>
<?php $this->append() ?>
