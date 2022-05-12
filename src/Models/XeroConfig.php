<?php

namespace bluntelk\IxpManagerXero\Models;

use Eloquent, stdClass;

use Illuminate\Database\Eloquent\{
    Builder,
    Model,
    Relations\BelongsTo
};

/**
 *
 * @property string|null $key
 * @property string|null $config
 * @mixin Eloquent
 */
class XeroConfig extends Model {
    protected $table = 'xero_config';

}