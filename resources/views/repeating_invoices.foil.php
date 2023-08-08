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
<?php $this->append() ?>

<?php $this->section( 'content' ) ?>
<table class="table">
    <thead>
    <tr>
        <th>Xero Customer</th>
        <th>IXP Customer</th>
        <th>Sub Total</th>
        <th>Tax</th>
        <th>Total</th>
        <th>Setup</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach( $t->invoices as /** @var RepeatingInvoice $invoice */
                   $invoice ): ?>
        <?php
        $key = $invoice->getContact()?->getContactNumber() ?? '';
        $isSetup = (bool)( $t->customers[ $key ] ?? false );
        if( $isSetup ) {
            $setupMembers[ $key ] = $t->customers[ $key ];
        }
        ?>
        <tr <?php if( $isSetup ): ?>class="bg-success" <?php endif ?>>
            <td><?= $invoice->getContact()?->getName() ?></td>
            <td><?= $t->customers[ $invoice->getContact()?->getContactNumber() ]->name ?? '' ?></td>
            <td class="text-right mono"><?= number_format( $invoice->getSubTotal(), 2 ) ?></td>
            <td class="text-right mono"><?= number_format( $invoice->getTotalTax(), 2 ) ?></td>
            <td class="text-right mono"><?= number_format( $invoice->getTotal(), 2 ) ?></td>
            <td><?= $isSetup ? 'Yes' : 'No' ?></td>
        </tr>
    <?php endforeach; ?>

    <?php foreach( $t->customers as /** @var Customer $customer */
                   $key => $customer ): ?>
        <?php
        if( isset( $setupMembers[ $key ] ) ) {
            continue;
        }
        ?>
        <tr>
            <td></td>
            <td><?= $customer->name ?? '' ?></td>
            <td class="text-right mono"></td>
            <td class="text-right mono"></td>
            <td class="text-right mono"></td>
            <td>No</td>
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
