<?php

namespace App\Services;

use App\Models\Instrument;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RecordTransaction
{
    public function __construct(private PortfolioSummary $summary) {}

    /**
     * @param  array<string, mixed>  $input  Validated StoreTransactionRequest data.
     * @return array{transaction: Transaction, replayed: bool, summary: array<string, mixed>}
     */
    public function handle(User $user, Portfolio $portfolio, array $input): array
    {
        Gate::forUser($user)->authorize('recordTransaction', $portfolio);
        $payload = $this->normalize($input);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        $key = strtolower($input['request_key']);

        return DB::transaction(function () use ($user, $portfolio, $payload, $hash, $key): array {
            $locked = Portfolio::whereKey($portfolio->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $existing = $locked->transactions()->where('request_key', $key)->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new ConflictHttpException('Mã yêu cầu đã được dùng cho một giao dịch khác.');
                }

                return ['transaction' => $existing, 'replayed' => true, 'summary' => $this->summary->forPortfolio($locked)];
            }
            if ($locked->currency !== 'VND') {
                $this->reject('portfolio', 'Chỉ hỗ trợ danh mục bằng VND.');
            }
            if ($payload['instrument_id'] !== null) {
                $instrument = Instrument::find($payload['instrument_id']);
                if (! $instrument || ! $instrument->tradable || $instrument->asset_class !== 'stock'
                    || $instrument->market !== 'VN' || $instrument->currency !== 'VND') {
                    $this->reject('instrument_id', 'Hãy chọn cổ phiếu Việt Nam được giao dịch bằng VND.');
                }
            }
            $rows = $locked->transactions()->orderBy('trade_date')->orderBy('id')->limit(10001)->get();
            if ($rows->count() >= 10000) {
                $this->reject('portfolio', 'Danh mục đã đạt giới hạn giao dịch của bản demo.');
            }
            $cash = $quantity = BigDecimal::zero();
            foreach ($rows as $row) {
                if ($row->trade_date->toDateString() > $payload['trade_date']) {
                    $this->reject('trade_date', 'Không thể ghi giao dịch trước lịch sử hiện có.');
                }
                $cash = $cash->plus($row->cash_delta);
                if ($row->instrument_id === $payload['instrument_id']) {
                    $quantity = match ($row->kind) {
                        'BUY' => $quantity->plus($row->quantity),
                        'SELL' => $quantity->minus($row->quantity),
                        default => $quantity,
                    };
                }
            }
            if ($payload['kind'] === 'SELL' && $quantity->isLessThan($payload['quantity'])) {
                $this->reject('quantity', 'Không đủ cổ phiếu để bán.');
            }
            if ($cash->plus($payload['cash_delta'])->isNegative()) {
                $this->reject('gross_amount', 'Không đủ tiền mặt cho giao dịch, bao gồm phí và thuế.');
            }
            $transaction = $locked->transactions()->create(array_merge($payload, [
                'user_id' => $user->id, 'request_key' => $key, 'request_hash' => $hash,
            ]));

            DB::afterCommit(fn () => PortfolioSummary::forget($locked));

            return ['transaction' => $transaction, 'replayed' => false, 'summary' => $this->summary->forPortfolio($locked)];
        }, 3);
    }

    /** @return array<string, mixed> */
    private function normalize(array $input): array
    {
        $kind = $input['kind'];
        $trade = in_array($kind, ['BUY', 'SELL'], true);
        $cash = in_array($kind, ['DEPOSIT', 'WITHDRAW'], true);
        $fee = BigDecimal::of($input['fee'] ?? '0')->toScale(0);
        $tax = BigDecimal::of($input['tax'] ?? '0')->toScale(0);
        if ($cash && (! $fee->isZero() || ! $tax->isZero())) {
            $this->reject('fee', 'Nạp và rút tiền không áp dụng phí hoặc thuế.');
        }
        $quantity = $trade ? BigDecimal::of($input['quantity'])->toScale(8) : null;
        $price = $trade ? BigDecimal::of($input['unit_price'])->toScale(8) : null;
        if ($trade && (! $quantity->isPositive() || $quantity->isGreaterThan('1000000000'))) {
            $this->reject('quantity', 'Số lượng phải là số nguyên từ 1 đến 1.000.000.000 cổ phiếu.');
        }
        if ($trade && (! $price->isPositive() || $price->isGreaterThan('1000000000000'))) {
            $this->reject('unit_price', 'Giá phải lớn hơn 0 và không vượt quá 1.000.000.000.000.');
        }
        $gross = $trade ? $quantity->multipliedBy($price)->toScale(0, RoundingMode::HalfUp) : BigDecimal::of($input['gross_amount'])->toScale(0);
        if (! $gross->isPositive() || $gross->isGreaterThanOrEqualTo('100000000000000000000')) {
            $this->reject('gross_amount', 'Số tiền phải lớn hơn 0 và nằm trong giới hạn giao dịch demo.');
        }
        if (in_array($kind, ['SELL', 'DIVIDEND'], true) && $gross->isLessThan($fee->plus($tax))) {
            $this->reject('fee', 'Phí và thuế không được vượt quá số tiền thu về.');
        }
        $delta = match ($kind) {
            'DEPOSIT' => $gross,
            'WITHDRAW' => $gross->negated(),
            'BUY' => $gross->plus($fee)->plus($tax)->negated(),
            'SELL', 'DIVIDEND' => $gross->minus($fee)->minus($tax),
        };
        if ($delta->abs()->isGreaterThanOrEqualTo('100000000000000000000')) {
            $this->reject('gross_amount', 'Tổng giao dịch vượt quá giới hạn của bản demo.');
        }

        return [
            'kind' => $kind, 'instrument_id' => $cash ? null : (int) $input['instrument_id'],
            'trade_date' => config('demo.simulation_date'), 'quantity' => $quantity === null ? null : (string) $quantity,
            'unit_price' => $price === null ? null : (string) $price, 'gross_amount' => (string) $gross,
            'fee' => (string) $fee, 'tax' => (string) $tax, 'cash_delta' => (string) $delta,
        ];
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
