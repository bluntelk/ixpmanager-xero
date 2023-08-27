<?php

namespace bluntelk\IxpManagerXero\Http\Requests\XeroLineItem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $uniqueRule = Rule::unique( 'xero_line_items', 'local_service' )
            ->where( 'cust_id', $this->input( 'cust_id' ) )
            ->where( 'xero_service', $this->input( 'xero_service' ) );
        if ($id = $this->route('line_item')) {
            $uniqueRule->whereNot('id', $id);
        }

        return [
            'local_service' => [
                'required',
                $uniqueRule,
            ],
        ];
    }

    public function messages()
    {
        return [
            'local_service.unique' => 'You already have a matching local service',
        ];
    }
}
