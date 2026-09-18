import { useEffect, useState } from 'react';

type Candle = { time: string; open: string; high: string; low: string; close: string; volume?: string };
type Props = { instrument?: Record<string, any>; portfolioId?: number };

export default function ReplayCandles({ instrument, portfolioId }: Props) {
    const [points, setPoints] = useState<Candle[]>([]);
    const [error, setError] = useState('');
    const [days, setDays] = useState(30);
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        if (!instrument?.id) return;
        const controller = new AbortController();
        fetch(`/api/v1/instruments/${instrument.id}/candles?days=${days}`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((data) => setPoints(data.points ?? []))
            .catch((reason) => { if (reason.name !== 'AbortError') setError('Không tải được biểu đồ mô phỏng.'); });
        return () => controller.abort();
    }, [instrument?.id, days]);

    if (!instrument) return null;
    const values = points.flatMap((point) => [Number(point.high), Number(point.low)]);
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;
    const maxVolume = Math.max(...points.map((point) => Number(point.volume || 0)), 1);

    return <section className="panel replay-panel">
        <div className="panel-head"><h2>Biểu đồ {instrument.symbol} · nến ngày</h2><span className="badge">Mô phỏng</span></div>
        <p className="notice">OHLC được tái tạo xác định từ lịch sử giá demo, chỉ dùng cho paper trading.</p>
        <div className="chart-ranges">{[7, 14, 30].map((range) => <button className={days === range ? 'selected' : ''} key={range} onClick={() => setDays(range)}>{range} ngày</button>)}{portfolioId && <button className="button button-blue" disabled={busy || !points.length} onClick={async () => { setBusy(true); await fetch(`/api/v1/portfolios/${portfolioId}/orders/advance`, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' }, body: JSON.stringify({ instrument_id: instrument.id, tick: points.length - 1 }) }); setBusy(false); }}> {busy ? 'Đang tiến phiên…' : 'Tiến phiên mô phỏng'} </button>}</div>
        {error ? <p className="form-error">{error}</p> : <div className="candle-chart" aria-label="Biểu đồ nến mô phỏng">
            {points.map((point) => <div className="candle" key={point.time} title={`${point.time} · Đóng cửa ${point.close}`}>
                <i style={{ height: `${Math.max(8, (Number(point.high) - Number(point.low)) / range * 100)}%` }} />
                <b className={Number(point.close) >= Number(point.open) ? 'up' : 'down'} style={{ bottom: `${(Number(point.low) - min) / range * 100}%`, height: `${Math.max(3, Math.abs(Number(point.close) - Number(point.open)) / range * 100)}%` }} />
                <em style={{ height: `${Math.max(4, Number(point.volume || 0) / maxVolume * 28)}px` }} />
            </div>)}
        </div>}
    </section>;
}
