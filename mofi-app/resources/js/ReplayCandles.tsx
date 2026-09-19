import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

type Candle = { time: string; open: string; high: string; low: string; close: string; volume?: string };
type Props = { instrument?: Record<string, any>; portfolioId?: number };

export default function ReplayCandles({ instrument, portfolioId }: Props) {
    const [points, setPoints] = useState<Candle[]>([]);
    const [error, setError] = useState('');
    const [days, setDays] = useState(30);
    const [busy, setBusy] = useState(false);
    const [tick, setTick] = useState(0);
    const [ready, setReady] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        if (!instrument?.id) return;
        const controller = new AbortController();
        setReady(false);
        setError('');
        const read = async (url: string) => {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) throw new Error('Không tải được dữ liệu mô phỏng.');
            return response.json();
        };
        Promise.all([
            read(`/api/v1/instruments/${instrument.id}/candles?days=90`),
            portfolioId ? read(`/api/v1/portfolios/${portfolioId}/orders?per_page=1`) : Promise.resolve(null),
        ]).then(([data, orders]) => {
            setPoints(data.points ?? []);
            setTick(Number(orders?.meta?.replay_ticks?.[instrument.id] ?? -1) + 1);
            setReady(true);
        }).catch((reason) => { if (reason?.name !== 'AbortError') setError('Không tải được dữ liệu mô phỏng. Tải lại trang để thử lại.'); });
        return () => controller.abort();
    }, [instrument?.id, portfolioId]);

    async function advance() {
        if (!instrument || !portfolioId) return;
        setBusy(true);
        setError('');
        setMessage('');
        try {
            const response = await fetch(`/api/v1/portfolios/${portfolioId}/orders/advance`, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ instrument_id: instrument.id, tick }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'Không thể tiến phiên.');
            setTick(Number(result.data.tick) + 1);
            setMessage(`Đã xử lý phiên ${Number(result.data.tick) + 1}: ${result.data.filled.length} lệnh có khớp.`);
            router.reload();
        } catch (reason) {
            setError(reason instanceof Error ? reason.message : 'Kết nối bị gián đoạn. Tải lại trang để đồng bộ phiên.');
        } finally {
            setBusy(false);
        }
    }

    if (!instrument) return null;
    const visiblePoints = points.slice(-days);
    const values = visiblePoints.flatMap((point) => [Number(point.high), Number(point.low)]);
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;
    const maxVolume = Math.max(...points.map((point) => Number(point.volume || 0)), 1);

    return <section className="panel replay-panel">
        <div className="panel-head"><h2>Biểu đồ {instrument.symbol} · nến ngày</h2><span className="badge">Mô phỏng</span></div>
        <p className="notice">OHLC được tái tạo xác định từ lịch sử giá demo, chỉ dùng cho paper trading.</p>
        <div className="chart-ranges">{[7, 14, 30].map((range) => <button className={days === range ? 'selected' : ''} key={range} onClick={() => setDays(range)}>{range} ngày</button>)}{portfolioId && <button className="button button-blue" disabled={busy || !ready || !points.length || tick >= points.length} onClick={advance}>{busy ? 'Đang tiến phiên…' : !ready ? 'Đang tải phiên…' : !points.length ? 'Chưa có dữ liệu' : tick >= points.length ? 'Đã hết phiên' : `Tiến phiên ${tick + 1}`}</button>}</div>
        {message && <p role="status">{message}</p>}
        {error ? <p className="form-error">{error}</p> : <div className="candle-chart" aria-label="Biểu đồ nến mô phỏng">
            {visiblePoints.map((point) => <div className="candle" key={point.time} title={`${point.time} · Đóng cửa ${point.close}`}>
                <i style={{ height: `${Math.max(8, (Number(point.high) - Number(point.low)) / range * 100)}%` }} />
                <b className={Number(point.close) >= Number(point.open) ? 'up' : 'down'} style={{ bottom: `${(Number(point.low) - min) / range * 100}%`, height: `${Math.max(3, Math.abs(Number(point.close) - Number(point.open)) / range * 100)}%` }} />
                <em style={{ height: `${Math.max(4, Number(point.volume || 0) / maxVolume * 28)}px` }} />
            </div>)}
        </div>}
    </section>;
}
