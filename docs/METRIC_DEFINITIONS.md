# MOFI - Định nghĩa chỉ số và hợp đồng biểu đồ

> Phạm vi: tài liệu thiết kế dài hạn v0.1. Khi làm demo 2 ngày, dùng [bộ tài liệu demo 1.0](demo/README.md). Các quy tắc khác nhau như nhiều tài khoản, goal earmark, reversal và jobs không áp dụng cho demo.

v0.1 - Dự thảo. Tiền VND; decimal dạng chuỗi ở API. Mỗi kết quả ghi scope, valuation_at, data_revision, price_revision, stream (`mock`/`real`) và status. NULL là thiếu/không áp dụng, không phải số 0. Toàn bộ công thức sử dụng dữ liệu cùng cutoff và revision.

## 1. Từ vựng và phạm vi

C = cash balances đã posted; S = securities value; A = manual assets value; B = remaining cost basis; R = realized P/L lũy kế trong khoảng theo dõi; I = net cash dividend. Portfolio value V = C+S. User total wealth W = tổng V + A. Mốc đầu kỳ là lúc bắt đầu nhập tài sản, không phải lúc user bắt đầu đầu tư trong đời thực.

| Mã | Thành phần trên ảnh | Công thức/phạm vi | Thiếu dữ liệu và cách hiển thị |
| --- | --- | --- | --- |
| M-01 | Tiền mặt | Sum cash_entries.amount của account trong scope; có đầu kỳ | Không có event = 0; không dùng field opening_balance độc lập |
| M-02 | Danh mục đầu tư | S = sum(Q_i * P_i), chưa gồm cash | Trên UI ghi “Giá trị chứng khoán”; thiếu P của một vị thế => S NULL và known_value riêng |
| M-03 | Tổng tài sản | W = sum(C+S) + A ngoài portfolio | Không cộng goal earmarks hoặc P/L thêm lần nữa; thiếu giá => tổng chưa đầy đủ |
| M-04 | P/L chưa thực hiện | U = S - B | Chỉ chứng khoán còn giữ; stale P kéo theo stale P/L |
| M-05 | P/L đã thực hiện | R = sum(net_sale - basis_sold) | Tính theo active ledger, không bao gồm event reversed |
| M-06 | Lãi/lỗ tổng | R + U + I | Ghi “Lãi/lỗ đầu tư từ mốc theo dõi”; không bao gồm biến động A hoặc tiền nạp/rút |
| M-07 | Tỷ lệ lãi vị thế còn giữ | U/B * 100 nếu B>0 | Nhãn “Lãi/lỗ trên giá vốn còn giữ”; B=0 => NULL; không gọi là CAGR/hiệu suất toàn danh mục |
| M-08 | P/L trong ngày | V_t - V_prev - F_external(scope,prev,t) | Chỉ C+S; opening trong khoảng làm kết quả N/A; thiếu mốc trước hoặc giá => N/A |
| M-09 | Biến động tổng tài sản | W_t - W_prev; % = chênh/W_prev nếu W_prev>0 | Ghi “Thay đổi tài sản”, không gọi lợi nhuận vì chứa nạp/rút và A |
| M-10 | Phân bổ theo loại | Cash, securities by asset_class, manual by category chia W | W=0 => empty; partial => không vẽ donut như 100% đầy đủ |
| M-11 | Phân bổ theo ngành | Security sector value / S | Không gồm cash/A; thiếu sector có nhóm “Chưa phân loại”; thiếu giá => partial |
| M-12 | Tiến độ mục tiêu | Sum active goal earmarks / target *100 | target>0; label có thể >100; thanh cap 100; chưa dự báo năm hoàn thành |
| M-13 | Thay đổi giá thị trường | P-reference_price; % = delta/reference nếu ref>0 | Không có ref/khác convention => N/A, không tự lấy quote đầu tiên |

M-08: F_external là signed external cash entries DEPOSIT/WITHDRAW; TRANSFER chỉ được tính F nếu qua biên scope. BUY/SELL/DIVIDEND không là F. Với portfolio đơn lẻ, transfer vào +x và ra -x; với user all-portfolios, hai phía triệt tiêu. OPENING_POSITION không là P/L ngày đầu; không hiển thị performance cho khoảng chứa thay đổi mốc opening. Net dividend đã tăng V nên không cộng I thêm vào M-08.

## 2. Contract chung dự kiến

```json
{
  "metric": "total_assets",
  "scope": {"type": "user"},
  "currency": "VND",
  "value": null,
  "known_value": "12000000.00000000",
  "valuation_at": "2026-09-15T17:00:00Z",
  "status": "partial",
  "freshness": "missing",
  "stream": "mock",
  "data_revision": 12,
  "price_revision": 3,
  "missing_instruments": ["EXAMPLE"]
}
```

`status`: complete/partial/empty/rebuilding. `freshness`: fresh/stale/missing/unknown. Stream độc lập với freshness; giá mock có thể đúng kỳ demo nhưng vẫn phải hiển thị mock. Decimal dùng chuỗi; frontend chỉ chuyển sang number để vẽ sau khi kiểm tra phạm vi an toàn, không dùng phép tính đó để ghi sổ.

## 3. Contract theo biểu đồ

| Biểu đồ | Series và dữ liệu | Hành vi |
| --- | --- | --- |
| Donut tài sản | label, amount, percentage, asset_class; cùng W/as_of | Percent có thể làm tròn lệch 0,1%; tooltip dùng số chính xác, không sửa dữ liệu gốc |
| Đường giá trị danh mục | business_date, C, S, V, status, revisions | EOD, không intraday; vùng thiếu để gap; không nội suy lợi nhuận |
| Watchlist sparkline | instrument, ordered points, unit, currency, price_type | Không đặt thang VND cho USD/point; chưa có history thì hiện “Chưa đủ dữ liệu” |
| Mục tiêu | target, reserved, pct, deadline | Không cộng reserved vào wealth |
| Summary | metric_id, scope, value, as_of, explanation_template | Không sinh nhận định khi metric partial hoặc revision mismatch |

Biểu đồ 1W/1M/3M/1Y/All lấy theo ngày; khi user mới có 10 ngày dữ liệu, 1Y vẫn chỉ 10 ngày và ghi mốc theo dõi. Ngày nghỉ có thể carry-forward last valid quote kèm price_as_of, không đánh dấu đó là quote phiên mới. Cần calendar để xác định phiên kỳ vọng.

## 4. Fixture đồng bộ cho review

Các con số sau là dữ liệu giả dùng riêng cho thiết kế; đơn vị VND. Không lấy các thẻ riêng trong ảnh làm expected output vì chúng chưa cộng khớp.

| Bước | Event | Cash cuối | Quantity | Basis còn lại | Realized |
| --- | --- | ---: | ---: | ---: | ---: |
| 1 | Deposit 30.000.000 | 30.000.000 | 0 | 0 | 0 |
| 2 | Buy 100 @100.000, fee 10.000 | 19.990.000 | 100 | 10.010.000 | 0 |
| 3 | Buy 100 @120.000, fee 10.000 | 7.980.000 | 200 | 22.020.000 | 0 |
| 4 | Sell 50 @130.000, fee 10.000 | 14.470.000 | 150 | 16.515.000 | 985.000 |
| 5 | Dividend gross 100.000, tax 5.000 | 14.565.000 | 150 | 16.515.000 | 985.000 |

Giả định tax giao dịch = 0 trong fixture, quote cuối 125.000. S=18.750.000; U=2.235.000; I=95.000; M-06=3.315.000; V=33.315.000. Thêm tài sản thủ công A=5.000.000 thì W=38.315.000. Dành 2.000.000 cho mục tiêu 10.000.000: progress=20%, cash spendable=12.565.000, W vẫn 38.315.000.

Nếu deposit thêm 10.000.000 và giá không đổi: V=43.315.000, P/L đầu tư vẫn 3.315.000; M-08 của khoảng này=0. Nếu chuyển 5.000.000 từ portfolio này sang portfolio khác cùng user: tổng user không đổi, performance mỗi portfolio phải loại dòng transfer tại biên của nó.

## 5. Các số chưa cam kết

CAGR, Sharpe, drawdown, benchmark, TWR, dự báo nghỉ hưu, 1D intraday và P/E chưa tính trong MVP. Khi bổ sung phải có định nghĩa frequency/risk-free rate/annualization, dữ liệu corporate actions/FX nếu cần, nguồn và test riêng; không gắn nhãn các chỉ số này lên phép chia đơn giản.
