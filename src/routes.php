<?php

use bluntelk\IxpManagerXero\Controllers\XeroController;

Route::group( [ 'middleware' => [ 'web' ] ], function() {
    Route::get( '/admin/xero', [ XeroController::class, 'index' ] )->name( 'xero.auth.success' );
} );
