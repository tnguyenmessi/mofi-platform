import { lazy, Suspense, type ComponentProps } from 'react';

const SparklineChart = lazy(() => import('./Charts').then(module => ({ default: module.Sparkline })));
const PortfolioValueChart = lazy(() => import('./Charts').then(module => ({ default: module.PortfolioChart })));
const AllocationChart = lazy(() => import('./Charts').then(module => ({ default: module.Allocation })));
const PriceChart = lazy(() => import('./Charts').then(module => ({ default: module.LivePriceChart })));

function Placeholder({ compact = false }: { compact?: boolean }) {
    return <div role="status" aria-label="Đang tải biểu đồ" style={{ minHeight: compact ? 32 : 250 }}>
        {!compact && <p className="empty-state">Đang tải biểu đồ…</p>}
    </div>;
}

export function Sparkline(props: ComponentProps<typeof SparklineChart>) {
    return <Suspense fallback={<Placeholder compact />}><SparklineChart {...props} /></Suspense>;
}

export function PortfolioChart(props: ComponentProps<typeof PortfolioValueChart>) {
    return <Suspense fallback={<Placeholder />}><PortfolioValueChart {...props} /></Suspense>;
}

export function Allocation(props: ComponentProps<typeof AllocationChart>) {
    return <Suspense fallback={<Placeholder />}><AllocationChart {...props} /></Suspense>;
}

export function LivePriceChart(props: ComponentProps<typeof PriceChart>) {
    return <Suspense fallback={<Placeholder />}><PriceChart {...props} /></Suspense>;
}
