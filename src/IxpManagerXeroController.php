<?php

namespace bluntelk\IxpManagerXero;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

class IxpManagerXeroController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __construct()
        {
//        $this->middleware('auth');
    }

    public function index()
    {
        return view('IxpManagerXero::index');
    }
}