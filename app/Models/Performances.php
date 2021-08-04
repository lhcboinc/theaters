<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Performances extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'performances';

    const TYPE_THEATER = 'theater';

    public function theaters()
    {
        return $this->belongsToMany(Theaters::class, 'performance_theater', 'performance_id','theater_id');
    }
}
