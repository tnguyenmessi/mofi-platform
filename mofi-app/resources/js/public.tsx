import React, {useEffect,useState} from 'react';
import {createRoot} from 'react-dom/client';
import {AreaChart,Area,ResponsiveContainer,PieChart,Pie,Cell,Tooltip} from 'recharts';
import {Wallet,ChartLineUp,Target,Flask,GraduationCap,Robot,ArrowRight} from '@phosphor-icons/react';
const example=[30,29.8,30.2,30.1,31,30.7,31.4,32.3,32,31.7,32.4,33.1,32.8,33.3].map((value,index)=>({value,index}));
function Preview(){return <div className="preview-device"><div className="preview-toolbar"><b>MOFI</b><span>Không gian tài chính của bạn</span><i>Danh mục minh họa</i></div><div className="preview-screen"><aside><b>Tổng quan</b><span>Tài sản</span><span>Danh mục</span><span>Mục tiêu</span><span>Học đầu tư</span></aside><div className="preview-content"><small>Tổng tài sản · ví dụ mô phỏng</small><strong>38.315.000 ₫</strong><div className="preview-widgets"><div><div style={{height:140}}><ResponsiveContainer><PieChart><Pie data={[{value:48.94},{value:38.01},{value:13.05}]} dataKey="value" innerRadius={39} outerRadius={59} isAnimationActive={false}>{['#3e8ee5','#2ec89a','#efc35c'].map(c=><Cell key={c} fill={c}/>)}</Pie></PieChart></ResponsiveContainer></div><small>Phân bổ tài sản</small></div><div><b>Cùng tiến gần mục tiêu</b><p>Quỹ dự phòng</p><progress value={20} max={100}/><small>2.000.000 / 10.000.000 ₫</small><p className="positive">Một kế hoạch rõ ràng mỗi ngày.</p></div></div><div style={{height:85}}><ResponsiveContainer><AreaChart data={example}><Area dataKey="value" type="monotone" stroke="#18a77c" fill="#e5f8f0" strokeWidth={2} isAnimationActive={false}/></AreaChart></ResponsiveContainer></div><small>Đường biểu đồ minh họa · không phải lợi nhuận thực tế</small></div></div><div className="device-base"/></div>}
function Market(){const [rows,setRows]=useState<any[]>([]),[error,setError]=useState(false),[loading,setLoading]=useState(true),[retry,setRetry]=useState(0);useEffect(()=>{const controller=new AbortController();setLoading(true);setError(false);Promise.all(['BTCUSDT','ETHUSDT','SOLUSDT'].map(async symbol=>{const r=await fetch('/api/v1/market/live?symbol='+symbol+'&days=7',{signal:controller.signal,headers:{Accept:'application/json'}});if(!r.ok)throw Error();return r.json();})).then(setRows).catch(e=>{if(e.name!=='AbortError')setError(true);}).finally(()=>{if(!controller.signal.aborted)setLoading(false);});return()=>controller.abort();},[retry]);return <><div className="panel-head"><h2>Thị trường hôm nay</h2><a href="/market">Chi tiết →</a></div><p className="public-source">Giá thật · Binance · Đơn vị USDT</p>{loading?<p className="empty-state">Đang tải dữ liệu thị trường…</p>:error?<div className="empty-state">Nguồn giá chưa phản hồi.<button className="text-button" onClick={()=>setRetry(n=>n+1)}>Thử lại</button></div>:rows.map(r=>{const last=r.points.at(-1).close,first=r.points[0].close,change=(last/first-1)*100;return <div className="public-market-row" key={r.symbol}><div><b>{r.symbol.replace('USDT','')}</b><small>7 ngày</small></div><div style={{width:75,height:36}}><ResponsiveContainer><AreaChart data={r.points}><Area dataKey="close" stroke={change<0?'#d44b5e':'#18a77c'} fill="#eef8f5" isAnimationActive={false}/><Tooltip/></AreaChart></ResponsiveContainer></div><div><b>{last.toLocaleString('vi-VN',{maximumFractionDigits:2})}</b><small className={change<0?'negative':'positive'}>{change>=0?'+':''}{change.toFixed(2)}%</small></div></div>})}<p className="public-source">Biến động so với đầu khoảng 7 ngày. Dữ liệu có thể trễ; nến hiện tại chưa đóng.</p></>}
const features=[['market','Tra cứu & phân tích','Xem giá, xu hướng và nguồn dữ liệu.',ChartLineUp],['assets','Quản lý tài sản','Theo dõi danh mục và dòng tiền.',Wallet],['strategies','Chiến lược đầu tư','Khám phá cách phân bổ phù hợp.',Target],['simulation','Sàn tập ảo','Thử kịch bản bằng tiền mô phỏng.',Flask],['learn','Học đầu tư','Kiến thức nền tảng, lưu tiến độ học.',GraduationCap],['copilot','MOFI Copilot','Hiểu danh mục qua tóm tắt quy tắc.',Robot]] as const;
const preview=document.getElementById('product-preview');if(preview)createRoot(preview).render(<Preview/>);
if(preview){
    const motion=window.matchMedia('(prefers-reduced-motion: no-preference) and (pointer: fine)');
    let frame=0;
    preview.addEventListener('pointermove',event=>{
        if(!motion.matches)return;
        cancelAnimationFrame(frame);
        frame=requestAnimationFrame(()=>{
            const box=preview.getBoundingClientRect();
            preview.style.setProperty('--tilt-x',`${-(event.clientY-box.top-box.height/2)/box.height*3}deg`);
            preview.style.setProperty('--tilt-y',`${(event.clientX-box.left-box.width/2)/box.width*4}deg`);
        });
    });
    const reset=()=>{cancelAnimationFrame(frame);preview.style.setProperty('--tilt-x','0deg');preview.style.setProperty('--tilt-y','0deg');};
    preview.addEventListener('pointerleave',reset);
    motion.addEventListener('change',reset);
}
const market=document.getElementById('public-market');if(market)createRoot(market).render(<Market/>);
const feature=document.getElementById('public-features');if(feature)createRoot(feature).render(<>{features.map(([url,title,body,Icon])=><a href={'/'+url} className="feature-card" key={url}><span className="feature-icon"><Icon size={28} weight="duotone"/></span><h3>{title}</h3><p>{body}</p><ArrowRight size={16}/></a>)}</>);
