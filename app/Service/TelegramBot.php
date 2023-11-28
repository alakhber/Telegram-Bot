<?php

namespace App\Service;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Service\QrScanner;

class TelegramBot
{


    private $token;
    private $apiUrl = 'https://api.telegram.org/';
    private  $stderr;

    public function __construct()
    {
        $this->stderr =  fopen('php://temp', 'w+');
        $this->token = 'bot' . env('TELEGRAM_BOT_TOKEN');
    }

    public function getMessages(int $updateId = null)
    {
        $offset = is_null($updateId) ? '' : '?offset=' . $updateId;
        $url = $this->apiUrl . $this->token . '/getUpdates' . $offset;
        $result = $this->sendRequest($url);
        $result = json_decode($result, true);
        $this->checkJsonDecodeError();
        if (!$result['ok']) {
            throw new Exception('Undefine Error Response Data False', 500);
        }
        $result = $this->prettyGetMessageData($result['result'], $updateId);
        return $result;
    }

    private function getFileName($fileId)
    {
        $url = $this->apiUrl . $this->token . '/getFile?file_id='  . $fileId;
        $result = $this->sendRequest($url);
        $result = json_decode($result, true);
        $this->checkJsonDecodeError();
        if (!$result['ok']) {
            throw new Exception('Undefine Error Response Data False', 500);
        }
        return $result['result']['file_path'];
    }

    private function fileDownLoad(string $fileId)
    {
        $getFileName = $this->getFileName($fileId);
        $url = $this->apiUrl . 'file/' . $this->token . '/' . $getFileName;
        $fileSoruce = $this->sendRequest($url);
        $fileName = 'tmp/' . (string) Str::uuid() . '.png';
        file_put_contents(storage_path('app/public/' . $fileName), $fileSoruce);
        $fileFullPath = storage_path('app/public/' . $fileName);
        $fileName = $this->checkQR($fileFullPath);
        return $fileName;
    }


    private function checkQR(string $file)
    {
        $newFileName = null;
        $qrScanner = new QrScanner();
        if ($qrScanner->checkOurQR($file)) {
            $newFileName = str_replace('tmp', 'telegram', $file);
            File::move($file, $newFileName);
            $newFileName = explode('public/', $newFileName);
            $newFileName = $newFileName[count($newFileName) - 1];
        }
        File::delete($file);
        return $newFileName;
    }


    private function sendRequest(string $url)
    {
        $curl = curl_init();
        if (!$curl) {
            throw new Exception('Curl Not Initialized', 500);
        }
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_ENCODING, '');
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_STDERR, $this->stderr);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        $result = curl_exec($curl);

        $this->checkCurlError($curl, $result);
        curl_close($curl);
        return $result;
        die;
    }
    private function checkCurlError($curl, $result)
    {
        if (!$result) {
            $data['error_no'] = curl_errno($curl);
            $data['error_message'] = curl_error($curl);
            rewind($this->stderr);
            $log = "cURL error (#" . curl_errno($curl) . "): " . htmlspecialchars(curl_error($curl)) . PHP_EOL
                . htmlspecialchars(curl_error($curl)) . PHP_EOL . "\n"
                . "Verbose information: " . PHP_EOL . PHP_EOL . "\n"
                . htmlspecialchars(stream_get_contents($this->stderr));
            $data['log'] = $log;
            $data['response'] = 'False';
            curl_close($curl);
            $data = $this->errorLog($data);
            throw new Exception('Curl Error:' . $data, 500);
        }
    }
    private function checkJsonDecodeError()
    {
        if (json_last_error() != 0) {
            throw new Exception('Json Decode Error: JSON ' . json_last_error_msg(), 500);
        }
    }
    private function errorLog(array $data)
    {
        $result = '';
        foreach ($data as $key => $value) {
            $result .= "$key: $value\n\t ";
        }
        return $result;
    }
    private function prettyGetMessageData(array $result, int $updateId = null)
    {
        $prettyData = [];
        foreach ($result as $key => $value) {
            if (isset($value['message']['photo']) and $value['update_id'] != $updateId) {
                $fileId = $value['message']['photo'][count($value['message']['photo']) - 1]['file_id'];
                $file = $this->fileDownLoad($fileId);
                $prettyData[] = [
                    'update_id' => $value['update_id'],
                    'chat_id' => $value['message']['chat']['id'],
                    'message_id' => $value['message']['message_id'],
                    'first_name' => $value['message']['from']['first_name'],
                    'last_name' => $value['message']['from']['last_name'],
                    'username' => $value['message']['from']['username'],
                    'date' => $value['message']['date'],
                    'message' => isset($value['message']['caption']) ?  $value['message']['caption'] : null,
                    'file_id' => $fileId,
                    'file' => $file,
                    'check_file' => is_null($file) ? 0 : 1,
                ];
            }
        }
        return $prettyData;
    }

    public function sendMessage($chatID, $messageID, $message)
    {
        $text = urlencode($message);
        $url = $this->apiUrl . $this->token . "/sendMessage?chat_id=$chatID&text=$text&reply_to_message_id=$messageID";
        $this->sendRequest($url);
    }
}
