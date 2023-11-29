<?php

namespace App\Service;

use App\Models\ReadKgo;
use App\Models\Telegram;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TelegramCommandService
{
    const ORDER_COMPLETED       = 7;
    const ORDER_BAKU_OFFICE_KASSA     = 15;
    const COURIER_STATUS_SENT = 2;
    const COURIER_STATUS_DELIVERED = 3;
    const PACKAGE_COMPLETED       = 7;




    private $tgBot;
    private $connection;
    public function __construct(TelegramBot $tgBot)
    {
        $this->tgBot = $tgBot;
        $this->connection = DB::connection('cango');
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

                $qr->kgo()->create(['kgo' =>  $result]);
                $qr->update(['is_read' => true]);

                DB::commit();
                $this->tgBot->sendMessage($qr['chat_id'], $qr['message_id'], 'QR İcraya Göndərildi !');
            } catch (\Throwable $th) {
                DB::rollBack();
            }
        }
    }

    public function _updateStatus()
    {
        $getKgos = ReadKgo::whereIsUpdate(0)->get();
        foreach ($getKgos as $key => $value) {
            $orderId = $value->kgo;
            try {
                DB::beginTransaction();
                // Check Operations 
                $order = $this->getOrder($orderId);
                $package = $this->getPackage($orderId, $order);
                $courierReqPackages = $this->getCourierRequestPackages($orderId, $order);
                $courierRequest = $this->getCourierRequest($orderId, $courierReqPackages, $order);
                $this->checkPackageStatusPreventDublicate($orderId,$package);
                // Update Operations
                $order->update(['status' => self::ORDER_COMPLETED]);
                $package->update(['status' => self::PACKAGE_COMPLETED]);
                $courierRequest->update(['status' => self::COURIER_STATUS_DELIVERED]);
                // Write Log
                $this->writePackageLogs($order,$package);
                //Send Message And Update TG Message
                $this->tgBot->sendMessage($value->telegram->chat_id, $value->telegram->message_id, 'Çatdırılma Tamalandı !');
                $value->update(['is_update' => true]);

                DB::commit();
            } catch (\Throwable $th) {
                
                DB::rollback();
                $this->tgBot->sendMessage($value->telegram->chat_id, $value->telegram->message_id, 'Əməliyat Zamanı Xəta Baş Verdi.Yenidən Yoxlayın Və Ya Bildirin !');
            }
        }
    }

    private function log($orderId, $message)
    {
        Log::channel($orderId . '/' . now()->format('d-m-Y H:i:s') . '_' . $orderId)->info($message);
    }

    private function getOrder($orderId)
    {
        $order =  $this->connection->table('orders')->where('id', $orderId)->first();
        if (is_null($order)) {
            $log = $orderId . ' ID Sifariş Tapılmadı !';
            $this->log($orderId, $log);
            throw new Exception($log, 404);
        }
        if ($order && $order->status !== self::ORDER_COMPLETED && $order->status !== self::ORDER_BAKU_OFFICE_KASSA) {
            $log = $order->id . ' Sifarisler ödənilməyib !';
            $this->log($orderId, $log);
            throw new Exception($log, 403);
        }
        return $order;
    }

    private function getPackage($orderId, $order)
    {
        $package = $this->connection->table('packages')->where('id', $order->package_id)->first();
        if (is_null($package)) {
            $log = $order->package_id . ' ID Paket Tapılmadı !';
            $this->log($orderId, $log);
            throw new Exception($log, 404);
        }
        return $package;
    }

    private function getCourierRequestPackages($orderId, $order)
    {
        $courierRequestPackages =  $this->connection->table('courier_request_packages')->where('package_id', $order->package_id)->first();
        if (is_null($courierRequestPackages)) {
            $log = $order->package_id . ' ID-li Bağlamanin Verildiyi Kuryer Yoxdur !';
            $this->log($orderId, $log);
            throw new Exception($log, 404);
        }
        return $courierRequestPackages;
    }

    private function getCourierRequest($orderId, $courierReqPackages, $order)
    {
        $courierRequest =  $this->connection->table('courier_requests')->where('id', $courierReqPackages->courier_request_id)->first();
        if (is_null($courierRequest)) {
            $log = $order->package_id . ' ID-li Bağlamanin Məlumatları Yoxdur !';
            $this->log($orderId, $log);
            throw new Exception($log, 404);
        }
        if ($courierRequest->status !== self::COURIER_STATUS_SENT) {
            $log = $order->package_id . ' Bağlama kuryerdə deyil!';
            $this->log($orderId, $log);
            throw new Exception($log, 404);
        }

        return $courierReqPackages;
    }

    private function checkPackageStatusPreventDublicate($package)
    {
        $packageStatus = $this->connection->table('package_statuses');
        $packageStatusPreventDublicate = $packageStatus->where('package_id', $package->id)
            ->where('status', self::ORDER_COMPLETED)
            ->first();
        if (!$packageStatusPreventDublicate) {
            // $packageStatus->insert([
            //     'package_id' => $order->package_id,
            //     'user_id'    =>  $order->user_id,
            //     'status'     => self::ORDER_COMPLETED,
            // ]);

        }
    }

    private function writePackageLogs($order,$package){
        $this->connection->table('package_logs')->insert([
            'package_id' =>  $package->id,
            'user_id'    =>  $order->user_id,
            'action'     => 'Tehvil',
            'comment'    => 'Sifarişlər təhvil verildi. #1',
        ]);
    }
}
