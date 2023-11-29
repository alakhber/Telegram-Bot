<?php

namespace App\Service;

use App\Models\ReadKgo;
use App\Models\Telegram;
use Illuminate\Support\Facades\DB;

class TelegramCommandService
{

    private $tgBot;
    public function __construct(TelegramBot $tgBot)
    {
        $this->tgBot = $tgBot;
    }

    public function _getMessage()
    {
        $tg = Telegram::orderBy('id', 'Desc')->first();
        $lastUpdateMessageId = is_null($tg) ? null : $tg->update_id;

        $tgMessages = $this->tgBot->getMessages($lastUpdateMessageId);
        
        foreach ($tgMessages as $value) {
            try {
        
                if (is_null($value['file'])) {
                    $this->tgBot->sendMessage($value['chat_id'], $value['message_id'], 'Qr Düzgün Deyil Zəhmət Olmasa Yenidən Göndərin !');
                }
        
                DB::beginTransaction();
                    Telegram::create($value);
                DB::commit();
           
            } catch (\Throwable $th) {
                DB::rollBack();
            }
        }
    }

    public function _readQr()
    {
        $qrs = Telegram::whereIsRead(0)->whereCheckFile(1)->get();
        
        $qrScan = new QrScanner();
        
        foreach ($qrs as $qr) {
            $result = $qrScan->getResult(storage_path('app/public/' . $qr->file));
        
            try {
        
                DB::beginTransaction();
        
                ReadKgo::create(['kgo' => str_replace('KGO9920','',$result)]);
                $qr->update(['is_read' => true]);
        
                DB::commit();
                $this->tgBot->sendMessage($qr['chat_id'], $qr['message_id'], 'QR İcraya Göndərildi !');
        
            } catch (\Throwable $th) {
                DB::rollBack();
            }
        }
    }
    
    public function _updateStatus(){
        $connection = DB::connection('cango');
        $getKgos = ReadKgo::whereIsUpdate(0)->get();

        foreach ($getKgos as $key => $value) {
            $orderId = $connection->table('orders')->where('id',str_replace('KGO9920','',$value->kgo))->first();
            if(!is_null($orderId)){
            //    $courierReqPackages = 
                dump($orderId->id);

            }
        }
    }
}
