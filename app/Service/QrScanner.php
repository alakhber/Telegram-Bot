<?php

namespace App\Service;


class QrScanner
{
    private $result;
    private $kgoPrefix = 'KGO9920';

    public function checkOurQR(string $qrFile)
    {
        $this->result = shell_exec("zbarimg $qrFile");
        return preg_match('/^QR-Code:.*\b' . $this->kgoPrefix . '[0-9]+$/', $this->result);
    }

    public function getResult(string $qrFile)
    {
        $this->result = shell_exec("zbarimg $qrFile");
        $this->prettyData();
        return $this->result;
    }

    private function prettyData()
    {
        preg_match('/' . $this->kgoPrefix . '\w*/', $this->result, $matches);
        $this->result = $matches[0];
    }
}
