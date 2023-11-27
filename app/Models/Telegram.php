<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Telegram extends Model
{
    use HasFactory;
    protected $fillable = ['message_id', 'update_id','chat_id', 'username', 'first_name', 'last_name', 'date', 'file_id', 'message','file','check_file','is_read'];
}
