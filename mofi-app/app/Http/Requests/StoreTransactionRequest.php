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
        $whole = ['bail', 'required', 'string', 'max:20', 'regex:/\A[0-9]+\z/'];

        return [
            'request_key' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['DEPOSIT', 'WITHDRAW'])],
            'instrument_id' => ['prohibited'],
            'quantity' => ['prohibited'],
            'unit_price' => ['prohibited'],
            'gross_amount' => $whole,
            'fee' => ['prohibited'],
            'tax' => ['prohibited'],
            'user_id' => ['prohibited'], 'portfolio_id' => ['prohibited'], 'cash_delta' => ['prohibited'],
            'trade_date' => ['prohibited'], 'request_hash' => ['prohibited'], 'created_at' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.prohibited' => 'Trường này do hệ thống thiết lập hoặc không áp dụng cho giao dịch.',
            '*.string' => 'Giá trị tài chính cần được gửi dưới dạng chuỗi số.',
            '*.regex' => 'Nhập số không âm với số chữ số thập phân được hỗ trợ.',
            'request_key.uuid' => 'Mã yêu cầu không hợp lệ.',
            'kind.in' => 'Chọn nạp tiền hoặc rút tiền. Mua bán cổ phiếu thực hiện tại Thị trường.',
        ];
    }
}
