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
     *
     * @apiParam {integer} limit
     */

    public function index(Request $request)
    {
        $limit = $request->limit;
        $collection = Performances::with(['theaters'])->limit($limit)->get();
        $collection->map(function ($item) {
            $item['seance_dt_list'] = json_decode($item['seance_dt_list']);
            $item['image_urls'] = json_decode($item['image_urls']);
        });
        return response()->json($collection);
    }
}
