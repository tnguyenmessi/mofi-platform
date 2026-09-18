<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('recordTransaction', $this->route('portfolio'));

        return true;
    }

    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid'], 'instrument_id' => ['required', 'integer', 'min:1'],
            'side' => ['required', Rule::in(['BUY', 'SELL'])], 'order_type' => ['required', Rule::in(['MARKET', 'LIMIT'])],
            'quantity' => ['required', 'string', 'regex:/\A[0-9]+(?:\.[0-9]{1,8})?\z/'],
            'limit_price' => $this->input('order_type') === 'LIMIT' ? ['required', 'string', 'regex:/\A[0-9]+(?:\.[0-9]{1,8})?\z/'] : ['prohibited'],
        ];
    }
}
