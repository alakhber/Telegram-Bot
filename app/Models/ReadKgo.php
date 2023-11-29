<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadKgo extends Model
{
    protected $fillable = ['kgo','telegram_id','is_update'];

    public function telegram(){
        return $this->belongsTo(Telegram::class,'telegram_id');
    }
}
