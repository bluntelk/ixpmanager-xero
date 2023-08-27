<?php

use bluntelk\IxpManagerXero\Http\Controllers\XeroController;
use bluntelk\IxpManagerXero\Http\Controllers\XeroLineItemController;

Route::group( [ 'middleware' => [ 'web' ] ], function() {
    Route::get( '/admin/xero', [ XeroController::class, 'index' ] )->name( 'xero.auth.success' );
    Route::get( '/admin/xero/info', [ XeroController::class, 'xeroDetails' ] )->name( 'ixpxero.info' );
    Route::get( '/admin/xero/nuke-login', [ XeroController::class, 'nukeLogin' ] )->name( 'ixpxero.nuke' );
    Route::get( '/admin/xero/perform-sync', [ XeroController::class, 'performSync' ] )->name( 'ixpxero.sync' );
    Route::get( '/admin/xero/repeating-invoices', [ XeroController::class, 'showRepeatingInvoices' ] )->name( 'xero.repeating.invoices' );
    Route::resource( '/admin/xero/line-item', XeroLineItemController::class )->names('ixpxero.line-item');
} );
