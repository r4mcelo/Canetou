<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class Supervisor extends Command
{
    private object $proccessInfo;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:supervisor {--restart} {--stop}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Supervisor of the system that keeps the processes running';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $restart = $this->option('restart');
        $stop = $this->option('stop');

        /**
         * List of commands to check if they are running and start if necessary
         * @var array<string, string> $checkComandProccess
         */
        $checkComandProccess = [
            "queue:work --queue=webhooks --tries=3 --sleep=3 --max-time=3600" => "php artisan queue:work --queue=webhooks --tries=3 --sleep=3 --max-time=3600 &",
        ];

        foreach ($checkComandProccess as $check => $comand) {
            $checker = $this->checkCommandStatus($check);

            if (!$checker) {
                $command = $this->executeCommand($comand);

                $checker = $this->checkCommandStatus($check);

                if ($command->status) {
                    if ($checker) {
                        $this->info($command->message);
                    } else {
                        $this->error($command->message);
                    }
                } else {
                    $this->error($command->message);
                }

            } else {

                $this->info('| ⚠️  Command already running');

                if ($restart) {

                    $this->info('| 📒  Restarting process...');
                    $this->executeCommand("pkill -f '{$check}'");
                    $command = $this->executeCommand($comand);
                    $this->info($command->message);

                } elseif ($stop) {
                    $this->info('| 🔴  Stopping process...');
                    $this->executeCommand("pkill -f '{$check}'");
                }
            }
        }
    }

    /**
     * Execute a command
     * @param string $command
     * @return object
     */
    private function executeCommand(string $command)
    {
        $this->info('| ℹ️  Executing command: ' . $command);

        $process = Process::fromShellCommandline($command);

        $process->start(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->proccessInfo = (object) [
                    'status' => false,
                    'message' => '| ❌ ERROR: an error occurred while executing the process',
                    'buffer' => $buffer,
                ];
            } else {
                $this->proccessInfo = (object) [
                    'status' => true,
                    'message' => '| ✅ Command executed successfully!',
                    'buffer' => $buffer,
                ];
            }
        });

        // Wait until the process is started
        while ($process->isRunning()) {
            sleep(1);
        }

        if (isset($this->proccessInfo)) {
            return $this->proccessInfo;
        }

        return (object) [
            'status' => false,
            'message' => '| Process returned null or empty',
        ];
    }

    /**
     * Check if the command is running
     * @param string $comand
     * @return bool
     */
    private function checkCommandStatus(string $comand)
    {
        $process = $this->executeCommand('ps aux');
        return strpos($process->buffer, $comand);
    }
}
