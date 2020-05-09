<?php

namespace bluntelk\IxpManagerXero;

use Illuminate\Routing\Controller;

class IxpManagerXeroController extends Controller
{
    public function index()
    {
        return view('ixpmanagerxero.index');
    }
}