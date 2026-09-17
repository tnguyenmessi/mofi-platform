<?php

namespace App\Policies;

use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PortfolioPolicy
{
    public function view(User $user, Portfolio $portfolio): Response
    {
        return (string) $user->id === (string) $portfolio->user_id
            ? Response::allow() : Response::denyAsNotFound();
    }

    public function recordTransaction(User $user, Portfolio $portfolio): Response
    {
        return $this->view($user, $portfolio);
    }
}
