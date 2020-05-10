<?php
/** @var Foil\Template\Template $t */
/** @var $t->active */

$this->layout( 'layouts/ixpv4' );
?>

<?php $this->section( 'page-header-preamble' ) ?>
Xero Integration
<?php $this->append() ?>

<?php $this->section( 'page-header-postamble' ) ?>

<?php $this->append() ?>

<?php $this->section('content') ?>
    Here I am
<?php $this->append() ?>


<?php $this->section( 'scripts' ) ?>
    <script>
        $(document).ready( function() {


        });
    </script>
<?php $this->append() ?>