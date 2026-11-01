<?php

namespace App\Http\Controllers\Relatorios;

use App\Http\Controllers\Controller;
use App\Services\Relatorios\HubService;
use Illuminate\Contracts\View\View;

class HubController extends Controller
{
    public function __construct(
        protected HubService $service
    ) {}

    public function index(): View
    {
        return $this->service->index();
    }
}
