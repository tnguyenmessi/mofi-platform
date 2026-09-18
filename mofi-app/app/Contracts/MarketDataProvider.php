<?php

namespace App\Contracts;

interface MarketDataProvider
{
    /** @return array{symbol:string,days:int,fetched_at:string,source:string,points:array<int,array{date:string,close:float}>,status:string,is_demo:bool,stale_after:int} */
    public function history(string $symbol, int $days): array;
}
