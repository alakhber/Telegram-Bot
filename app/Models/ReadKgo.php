<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadKgo extends Model
{
    use HasFactory;
    protected $fillable = ['kgo','is_update'];
}
