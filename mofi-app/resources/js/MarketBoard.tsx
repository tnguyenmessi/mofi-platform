import { useEffect, useMemo, useState, type FormEvent } from 'react';

type Row = Record<string, any>;
type Props = { portfolioId: number; instruments: Row[]; initialOrders: Row[] };
type Level = { level: number; price: string; quantity: string };
type Board = { instrument: Row; session: Row; quote: Row };

const price = (value: any) => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(Number(value));
const quantity = (value: any) => new Intl.NumberFormat('vi-VN', { maximumFractionDigits: 0 }).format(Number(value));
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';

export default function MarketBoard({ portfolioId, instruments, initialOrders }: Props) {
    const stocks = useMemo(() => instruments.filter((item) => item.tradable && item.market === 'VN' && item.asset_class === 'stock' && item.currency === 'VND'), [instruments]);
    const [instrumentId, setInstrumentId] = useState(stocks[0]?.id ?? '');
    const [board, setBoard] = useState<Board | null>(null);
    const [orders, setOrders] = useState<Row[]>(initialOrders ?? []);
    const [error, setError] = useState('');
    const [message, setMessage] = useState('');
    const [side, setSide] = useState('BUY');
    const [orderType, setOrderType] = useState('LIMIT');
    const [orderQuantity, setOrderQuantity] = useState('10');
    const [limitPrice, setLimitPrice] = useState('');
    const [busy, setBusy] = useState(false);

    async function loadBoard(signal?: AbortSignal) {
        if (!instrumentId) return;
        try {
            const response = await fetch(`/api/v1/instruments/${instrumentId}/market-board`, { signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Không tải được bảng giá.');
            setBoard(data);
            setError('');
            if (!limitPrice && data.quote?.asks?.[0]?.price) setLimitPrice(String(data.quote.asks[0].price));
        } catch (reason: any) {
            if (reason?.name !== 'AbortError') setError(reason instanceof Error ? reason.message : 'Không tải được bảng giá mô phỏng.');
        }
    }

    async function loadOrders(signal?: AbortSignal) {
        if (!instrumentId) return;
        try {
            const response = await fetch(`/api/v1/portfolios/${portfolioId}/orders?instrument_id=${instrumentId}&per_page=20`, { signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (response.ok) setOrders((await response.json()).data ?? []);
        } catch (reason: any) {
            if (reason?.name !== 'AbortError') return;
        }
    }

    useEffect(() => {
        const controller = new AbortController();
        setBoard(null);
        setError('');
        void loadBoard(controller.signal);
        void loadOrders(controller.signal);
        const timer = window.setInterval(() => { void loadBoard(controller.signal); void loadOrders(controller.signal); }, 2000);
        return () => { controller.abort(); window.clearInterval(timer); };
    }, [instrumentId]);

    useEffect(() => {
        const best = side === 'BUY' ? board?.quote?.asks?.[0]?.price : board?.quote?.bids?.[0]?.price;
        if (best) setLimitPrice(String(best));
    }, [side, board?.session?.revision]);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setBusy(true);
        setMessage('');
        try {
            const payload: Row = { request_key: crypto.randomUUID(), instrument_id: Number(instrumentId), side, order_type: orderType, quantity: orderQuantity };
            if (orderType === 'LIMIT') payload.limit_price = limitPrice;
            const response = await fetch(`/api/v1/portfolios/${portfolioId}/orders`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(payload) });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors ?? {}).flat().join(' ') || data.message || 'Không thể đặt lệnh.');
            const order = data.data;
            setMessage(data.replayed ? 'Lệnh đã được xác nhận trước đó.' : order.status === 'FILLED' ? `Lệnh đã khớp đủ ${quantity(order.filled_quantity)} cổ phiếu.` : order.status === 'PARTIALLY_FILLED' ? `Lệnh khớp một phần: ${quantity(order.filled_quantity)} cổ phiếu, phần còn lại đang chờ.` : 'Lệnh đã vào sổ và đang chờ khớp.');
            await loadBoard();
            await loadOrders();
        } catch (reason) {
            setMessage(reason instanceof Error ? reason.message : 'Không thể đặt lệnh.');
        } finally {
            setBusy(false);
        }
    }

    async function cancel(orderId: number) {
        const response = await fetch(`/api/v1/orders/${orderId}/cancel`, { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        if (response.ok) { await loadOrders(); await loadBoard(); }
    }

    if (!stocks.length) return <section className="panel"><p className="empty-state">Chưa có mã cổ phiếu mô phỏng được bật giao dịch.</p></section>;
    if (!board) return <section className="market-terminal">{error && <p className="form-error" role="alert">{error} <button className="text-button" type="button" onClick={() => void loadBoard()}>Thử lại</button></p>}<p className="empty-state">Đang kết nối bảng giá mô phỏng…</p></section>;
    const quote = board.quote;
    const session = board.session;
    const asks: Level[] = quote?.asks ?? [];
    const bids: Level[] = quote?.bids ?? [];
    const visibleOrders = orders.filter((order) => order.instrument_id === Number(instrumentId));

    return <section className="market-terminal">
        <div className="market-terminal-head"><div><span className="eyebrow">MOFI PAPER MARKET</span><h2>Bảng giá chứng khoán mô phỏng</h2><p>Giá, khớp lệnh và thanh khoản được máy chủ mô phỏng; không dùng tiền thật.</p></div><label className="market-symbol-picker">Mã giao dịch<select value={instrumentId} onChange={(event) => { setInstrumentId(event.target.value); setBoard(null); setLimitPrice(''); setMessage(''); }}>{stocks.map((item) => <option key={item.id} value={item.id}>{item.symbol} · {item.name}</option>)}</select></label></div>
        {error && <p className="form-error" role="alert">{error} <button className="text-button" type="button" onClick={() => void loadBoard()}>Thử lại</button></p>}
        {board && <>
            <div className="market-session-strip"><div><b>{board.instrument.symbol}</b><span>{board.instrument.name}</span></div><span className="session-state"><i className={session.status === 'OPEN' ? 'live-dot' : ''}/>{session.market_status}</span><div><b>{session.simulated_time}</b><span>{session.date} · +{session.simulated_interval_minutes} phút mỗi tick</span></div><div><b>Tick {Number(session.current_tick)}/{session.total_ticks}</b><span>{session.next_update_at ? `Cập nhật sau ${Math.max(0, Math.ceil((new Date(session.next_update_at).getTime() - Date.now()) / 1000))} giây` : 'Đang chuẩn bị phiên mới'}</span></div></div>
            <div className="quote-summary"><div><span>Giá khớp cuối</span><strong>{price(quote.last_price)}</strong><small className={Number(quote.change) < 0 ? 'negative' : 'positive'}>{Number(quote.change) >= 0 ? '+' : ''}{price(quote.change)} ({Number(quote.change_percent).toFixed(2)}%)</small></div><div><span>Tham chiếu</span><b>{price(quote.reference_price)}</b></div><div><span>Trần</span><b className="ceiling-text">{price(quote.ceiling_price)}</b></div><div><span>Sàn</span><b className="floor-text">{price(quote.floor_price)}</b></div><div><span>KL khớp / tổng KL</span><b>{quantity(quote.matched_volume)} / {quantity(quote.total_volume)}</b></div></div>
            <div className="market-terminal-grid"><div className="quote-book panel"><div className="panel-head"><h3>Sổ lệnh</h3><span className="badge">Ưu tiên giá</span></div><div className="book-table"><div className="book-header"><span>Giá bán</span><span>KL bán</span></div>{asks.slice().reverse().map((level) => <div className="book-row ask" key={`ask-${level.level}`}><span>A{level.level} · {price(level.price)}</span><b>{quantity(level.quantity)}</b></div>)}<div className="book-last"><b>{price(quote.last_price)}</b><span>Giá khớp gần nhất</span></div><div className="book-header"><span>Giá mua</span><span>KL mua</span></div>{bids.map((level) => <div className="book-row bid" key={`bid-${level.level}`}><span>B{level.level} · {price(level.price)}</span><b>{quantity(level.quantity)}</b></div>)}</div></div>
                <div className="order-ticket panel"><div className="panel-head"><h3>Đặt lệnh</h3><span className={side === 'BUY' ? 'ticket-side buy' : 'ticket-side sell'}>{side === 'BUY' ? 'MUA' : 'BÁN'}</span></div><form className="terminal-order-form" onSubmit={submit}><div className="ticket-toggle"><button type="button" className={side === 'BUY' ? 'active buy' : ''} onClick={() => setSide('BUY')}>Mua</button><button type="button" className={side === 'SELL' ? 'active sell' : ''} onClick={() => setSide('SELL')}>Bán</button></div><label>Loại lệnh<select value={orderType} onChange={(event) => setOrderType(event.target.value)}><option value="LIMIT">LO · Lệnh giới hạn</option><option value="MARKET">MP · Lệnh thị trường</option></select></label><label>Khối lượng<input type="number" min="1" step="1" value={orderQuantity} onChange={(event) => setOrderQuantity(event.target.value)} required/></label>{orderType === 'LIMIT' && <label>Giá đặt (VND)<input type="number" min="1" step="100" value={limitPrice} onChange={(event) => setLimitPrice(event.target.value)} required/></label>}<p className="ticket-hint">{orderType === 'MARKET' ? 'Mua sẽ ăn Ask 1 → Ask 3; bán sẽ ăn Bid 1 → Bid 3.' : `Lệnh chỉ khớp khi giá đối ứng ${side === 'BUY' ? '≤ giá đặt' : '≥ giá đặt'}.`}</p><button className={`button ${side === 'BUY' ? 'button-blue' : 'button-red'}`} disabled={busy || session.status !== 'OPEN'}>{busy ? 'Đang gửi…' : `Đặt lệnh ${side === 'BUY' ? 'mua' : 'bán'}`}</button></form>{message && <p className="notice" role="status">{message}</p>}</div></div>
            <div className="panel terminal-orders"><div className="panel-head"><h3>Lệnh và khớp lệnh của tôi</h3><span className="badge">Tự cập nhật</span></div>{visibleOrders.length ? <div className="table-scroll"><table><thead><tr><th>Mã</th><th>Lệnh</th><th>KL đặt / khớp</th><th>Giá đặt</th><th>Trạng thái</th><th/></tr></thead><tbody>{visibleOrders.map((order) => <tr key={order.id}><td><b>{order.instrument?.symbol ?? board.instrument.symbol}</b></td><td className={order.side === 'BUY' ? 'positive' : 'negative'}>{order.side === 'BUY' ? 'Mua' : 'Bán'} · {order.order_type}</td><td>{quantity(order.quantity)} / {quantity(order.filled_quantity)}{order.executions?.length ? <small>{order.executions.length} lần khớp</small> : null}</td><td>{order.limit_price ? price(order.limit_price) : 'MP'}</td><td><span className={`order-status ${String(order.status).toLowerCase()}`}>{order.status}</span></td><td>{['OPEN', 'PARTIALLY_FILLED'].includes(order.status) && <button className="text-button" type="button" onClick={() => void cancel(order.id)}>Hủy</button>}</td></tr>)}</tbody></table></div> : <p className="empty-state">Chưa có lệnh nào cho mã này.</p>}</div>
        </>}
    </section>;
}
