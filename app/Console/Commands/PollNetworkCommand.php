<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class PollNetworkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:poll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ejecuta un ciclo de sondeo en vivo (ICMP y SNMP) contra los dispositivos físicos de red.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando sondeo de hardware en tiempo real...');

        $pythonBin = env('PYTHON_PATH', 'C:\\Users\\ssocial_redes1\\AppData\\Local\\Programs\\Python\\Python312\\python.exe');
        if (!file_exists($pythonBin)) $pythonBin = 'python';

        $process = new Process([$pythonBin, '-c', 'import asyncio; from worker.snmp_poller import run_poll_cycle; asyncio.run(run_poll_cycle())'], base_path());
        $process->setTimeout(30);
        $process->run();

        if ($process->isSuccessful()) {
            $this->line($process->getOutput());
            $this->info('Sondeo completado con éxito.');
            return Command::SUCCESS;
        } else {
            $this->error('Error ejecutando el sondeo: ' . $process->getErrorOutput());
            return Command::FAILURE;
        }
    }
}
