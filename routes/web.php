<?php

use bluntelk\IxpManagerXero\Controllers\XeroController;

Route::group( [ 'middleware' => [ 'web' ] ], function() {
    Route::get( '/admin/xero', [ XeroController::class, 'index' ] )->name( 'xero.auth.success' );
    Route::get( '/admin/xero/perform-sync', [ XeroController::class, 'performSync' ] )->name( 'xero.sync' );
    Route::get( '/admin/xero/repeating-invoices', [ XeroController::class, 'showRepeatingInvoices' ] )->name( 'xero.repeating.invoices' );
} );
