<?php
/** @var Foil\Template\Template $t */
/** @var $t ->active */

use XeroAPI\XeroPHP\Models\Accounting\RepeatingInvoice;

$this->layout( 'layouts/ixpv4' );
?>

<?php $this->section( 'page-header-preamble' ) ?>
    Xero Repeating Invoices
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>
<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<table class="table">
    <thead>
    <tr>
        <th>Contact Name</th>
        <th>Sub Total</th>
        <th>Tax</th>
        <th>Total</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach($t->invoices as /** @var RepeatingInvoice $invoice*/ $invoice): ?>
    <tr>
        <td><?= $invoice->getContact()?->getName() ?></td>
        <td class="text-right mono"><?= number_format($invoice->getSubTotal(), 2) ?></td>
        <td class="text-right mono"><?= number_format($invoice->getTotalTax(), 2) ?></td>
        <td class="text-right mono"><?= number_format($invoice->getTotal(), 2) ?></td>
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
