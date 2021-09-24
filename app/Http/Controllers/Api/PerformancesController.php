<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GetPerformancesRequest;
use App\Models\Performances;

class PerformancesController extends Controller
{
    /**
     * @api {get} /api/performances Get Performances
     * @apiName GetPerformances
     * @apiGroup Performances
     *
     * @apiParam {integer} [limit]
     * @apiParam {string=theater,movie} [type]
     * @apiParam {integer} [id]
     */

    public function index(GetPerformancesRequest $request)
    {
        $limit = @$request->limit;
        $type = @$request->type;
        $id = @$request->id;
        $query = Performances::with(['theaters']);
        if ($type)
            $query->where('type', $type);
        if ($limit)
            $query->limit($limit);
        if ($id)
            $query->where('id', $id);
        $collection = $query->get();
        $collection->map(function ($item) use ($request) {
            $imageUrls = [];
            if (!empty($item['images'])) {
                foreach (json_decode($item['images']) as $image)
                    $imageUrls[] = $request->getScheme() . '://' . $request->getHttpHost() . '/images/thumb_' . $image;
            }
            $item['image_urls'] = $imageUrls;
            $seance_dt_list = $item->theaters[0]->pivot->seance_dt_list;
            $item->theaters[0]->pivot->seance_dt_list = json_decode($seance_dt_list);
        });
        return response()->json($collection);
    }
}
