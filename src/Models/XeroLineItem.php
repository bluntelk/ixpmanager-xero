<?php

namespace bluntelk\IxpManagerXero\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use IXP\Models\Customer;

/**
 *
 * @property string $local_service
 * @property string|null $xero_service
 * @property int|null $cust_id
 * @mixin Eloquent
 */
class XeroLineItem extends Model
{
    protected $table = 'xero_line_items';
    protected $fillable = ['local_service', 'xero_service', 'cust_id'];

    /**
     * Get the customer that owns the Atlas probe
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'cust_id');
    }
}