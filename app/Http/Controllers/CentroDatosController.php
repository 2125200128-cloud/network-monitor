<?php

namespace App\Http\Controllers;

use App\Services\TopologiaCentroDatos;

/** Topología del centro de datos (inventario físico + estado en vivo del monitoreo). */
class CentroDatosController extends Controller
{
    public function index(TopologiaCentroDatos $topologia)
    {
        return view('topologia.centro-datos', ['datos' => $topologia->datos()]);
    }

    public function datos(TopologiaCentroDatos $topologia)
    {
        return response()->json($topologia->datos());
    }
}
