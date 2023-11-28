<?php

namespace App\Console\Commands;

use App\Service\TelegramBot;
use App\Service\TelegramCommandService;
use Illuminate\Console\Command;
use InvalidArgumentException;

class TelegramCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'run:telegram {--operation=method}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        dd(1);
        $tgBot = new TelegramBot;
        $tgService = new TelegramCommandService($tgBot);
        $callMethod = '_'.$this->option('operation');
        if(!in_array($callMethod,get_class_methods($tgService))){
            throw new InvalidArgumentException('Bele Bir Method Mövcud Deyil !',404);
        }
        $tgService->{$callMethod}();
    }
}
