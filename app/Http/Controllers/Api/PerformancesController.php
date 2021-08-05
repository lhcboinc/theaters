<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetPerformancesRequest;
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
     * @apiParam {string=theater,movie} type
     */

    public function index(GetPerformancesRequest $request)
    {
        $limit = @$request->limit;
        $type = @$request->type;
        $query = Performances::with(['theaters']);
        if ($type)
            $query->where('type', $type);
        if ($limit)
            $query->limit($limit);
        $collection = $query->get();
        $collection->map(function ($item) {
            $item['image_urls'] = json_decode($item['image_urls']);
            $seance_dt_list = $item->theaters[0]->pivot->seance_dt_list;
            $item->theaters[0]->pivot->seance_dt_list = json_decode($seance_dt_list);
        });
        return response()->json($collection);
    }
}
