<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('recordTransaction', $this->route('portfolio'));

        return true;
    }

    public function rules(): array
    {
        $trade = in_array($this->input('kind'), ['BUY', 'SELL'], true);
        $cash = in_array($this->input('kind'), ['DEPOSIT', 'WITHDRAW'], true);
        $whole = ['bail', 'required', 'string', 'max:20', 'regex:/\A[0-9]+\z/'];

        return [
            'request_key' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['DEPOSIT', 'WITHDRAW', 'BUY', 'SELL', 'DIVIDEND'])],
            'instrument_id' => $cash ? ['prohibited'] : ['required', 'integer', 'min:1'],
            'quantity' => $trade ? $whole : ['prohibited'],
            'unit_price' => $trade ? ['bail', 'required', 'string', 'max:22', 'regex:/\A[0-9]+(?:\.[0-9]{1,8})?\z/'] : ['prohibited'],
            'gross_amount' => $trade ? ['prohibited'] : $whole,
            'fee' => ['sometimes', 'bail', 'required', 'string', 'max:20', 'regex:/\A[0-9]+\z/'],
            'tax' => ['sometimes', 'bail', 'required', 'string', 'max:20', 'regex:/\A[0-9]+\z/'],
            'user_id' => ['prohibited'], 'portfolio_id' => ['prohibited'], 'cash_delta' => ['prohibited'],
            'trade_date' => ['prohibited'], 'request_hash' => ['prohibited'], 'created_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.prohibited' => 'This field is assigned by the server or is not applicable to this transaction.',
            '*.string' => 'Send financial values as decimal strings.',
            '*.regex' => 'Use non-negative decimal notation with the supported precision.',
            'request_key.uuid' => 'The request key must be a UUID.',
            'kind.in' => 'Select DEPOSIT, WITHDRAW, BUY, SELL or DIVIDEND.',
        ];
    }
}
