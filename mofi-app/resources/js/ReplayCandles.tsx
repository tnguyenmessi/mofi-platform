import { useEffect, useState } from 'react';

type Candle = { time: string; open: string; high: string; low: string; close: string };
type Props = { instrument?: Record<string, any> };

export default function ReplayCandles({ instrument }: Props) {
    const [points, setPoints] = useState<Candle[]>([]);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!instrument?.id) return;
        const controller = new AbortController();
        fetch(`/api/v1/instruments/${instrument.id}/candles?days=30`, { headers: { Accept: 'application/json' }, signal: controller.signal })
            .then((response) => response.ok ? response.json() : Promise.reject())
            .then((data) => setPoints(data.points ?? []))
            .catch((reason) => { if (reason.name !== 'AbortError') setError('Không tải được biểu đồ mô phỏng.'); });
        return () => controller.abort();
    }, [instrument?.id]);

    if (!instrument) return null;
    const values = points.flatMap((point) => [Number(point.high), Number(point.low)]);
    const max = Math.max(...values, 1);
    const min = Math.min(...values, 0);
    const range = max - min || 1;

    return <section className="panel replay-panel">
        <div className="panel-head"><h2>Biểu đồ {instrument.symbol} · nến ngày</h2><span className="badge">Mô phỏng</span></div>
        <p className="notice">OHLC được tái tạo xác định từ lịch sử giá demo, chỉ dùng cho paper trading.</p>
        {error ? <p className="form-error">{error}</p> : <div className="candle-chart" aria-label="Biểu đồ nến mô phỏng">
            {points.map((point) => <div className="candle" key={point.time} title={`${point.time} · Đóng cửa ${point.close}`}>
                <i style={{ height: `${Math.max(8, (Number(point.high) - Number(point.low)) / range * 100)}%` }} />
                <b className={Number(point.close) >= Number(point.open) ? 'up' : 'down'} style={{ bottom: `${(Number(point.low) - min) / range * 100}%`, height: `${Math.max(3, Math.abs(Number(point.close) - Number(point.open)) / range * 100)}%` }} />
            </div>)}
        </div>}
    </section>;
}
