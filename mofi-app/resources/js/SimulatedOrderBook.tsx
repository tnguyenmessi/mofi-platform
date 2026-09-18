type Props = { price?: string | number | null };

export default function SimulatedOrderBook({ price }: Props) {
    const midpoint = Number(price || 124000);
    const bids = [120, 240, 180].map((size, index) => ({ price: midpoint - (index + 1) * 100, size }));
    const asks = [95, 210, 140].map((size, index) => ({ price: midpoint + (index + 1) * 100, size }));
    const format = (value: number) => new Intl.NumberFormat('vi-VN').format(value);

    return <section className="panel order-book-panel">
        <div className="panel-head"><h2>Sổ lệnh mô phỏng</h2><span className="badge">Bid / ask</span></div>
        <div className="order-book">
            <div><b>Bên mua</b>{bids.map((row) => <span key={row.price}>{format(row.price)} <small>{row.size}</small></span>)}</div>
            <div><b>Bên bán</b>{asks.map((row) => <span key={row.price}>{format(row.price)} <small>{row.size}</small></span>)}</div>
            <small>Độ sâu được tạo từ giá mô phỏng; không đại diện thanh khoản thị trường thật.</small>
        </div>
    </section>;
}
