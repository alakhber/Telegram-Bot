<?php

namespace App\Http\Controllers;

use App\Models\Telegram;
use App\Service\QrScanner;
use App\Service\TelegramBot;
use Illuminate\Support\Facades\DB;

class TelegramBotController extends Controller
{

    
    public function __construct(private TelegramBot $tgBot)
    {
        
    }

    public function writeMessages()
    {
        $tg = Telegram::orderBy('id', 'Desc')->first();
        $lastUpdateMessageId = is_null($tg) ? null : $tg->update_id;
        $tgMessages = $this->tgBot->getMessages($lastUpdateMessageId);
        dd($tgMessages);
        foreach ($tgMessages as $value) {
            try {
                if (is_null($value['file'])) {
                    $this->tgBot->sendMessage($value['chat_id'], $value['message_id'],'Qr Düzgün Deyil Zəhmət Olmasa Yenidən Göndərin !');
                }
                DB::beginTransaction();
                Telegram::create($value);
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();
            }
            
        }
    }

    public function updateStatus()
    {
        $qrs = Telegram::whereIsRead(0)->whereCheckFile(1)->get();
        $qrScan = new QrScanner();
        $results = [];
        $updateTGID = [];
        foreach ($qrs as $qr) {
            $results[] = $qrScan->getResult(storage_path('app/public/'.$qr->file));
            $updateTGID[]=$qr->id;
        }

        dd($updateTGID);
       
    }

    public function connect(){
        $users = DB::connection('cango')->table('activity_log')->get();
    }
}
