<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Performances;
use Illuminate\Http\Request;

class PerformancesController extends Controller
{
    /**
     * @api {get} /api/performances Get Performances
     * @apiName GetPerformances
     * @apiGroup Performances
     */

    public function index()
    {
        $collection = Performances::with(['theaters'])->limit(5)->get();
        $collection->map(function ($item) {
            $item['seance_dt_list'] = json_decode($item['seance_dt_list']);
            $item['image_urls'] = json_decode($item['image_urls']);
        });
        return response()->json($collection);
    }
}
