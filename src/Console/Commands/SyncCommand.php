<?php

namespace bluntelk\IxpManagerXero\Console\Commands;

use bluntelk\IxpManagerXero\Services\XeroSync;
use Illuminate\Console\Command;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    protected $signature = 'xero:sync';

    protected $description = 'Sync all members into Xero';

    public function __construct() {
        parent::__construct();
        Event::listen(MessageLogged::class, function(MessageLogged $event) {
            switch($event->level) {
                case LogLevel::CRITICAL:
                case LogLevel::ERROR:
                case LogLevel::ALERT:
                case LogLevel::EMERGENCY:
                    $this->getOutput()->error($event->message);
                    break;
                case LogLevel::WARNING:
                    $this->getOutput()->warning($event->message);
                    break;
                case LogLevel::DEBUG:
                    $this->getOutput()->writeln($event->message, OutputInterface::VERBOSITY_VERY_VERBOSE);
                    break;
                case LogLevel::INFO:
                default:
                    $this->getOutput()->writeln($event->message, OutputInterface::VERBOSITY_VERBOSE);
                    break;
            }
        });

    }

    public function handle(XeroSync $xeroSync)
    {
        $this->output->title("IXP Manager -> Xero Sync");

        if (!$xeroSync->isXeroConfigValid()) {
            $this->error("Your Xero Config is invalid. Please follow the setup steps in the README.md", OutputInterface::VERBOSITY_QUIET);
            return 1;
        }

        $actions = $xeroSync->performSync();

        if ($this->verbosity >= OutputInterface::VERBOSITY_NORMAL) {
            $rows = [];
            foreach ($actions as $action) {
                $rows[] = [
                    $action->customer->getName(),
                    $action->action,
                    $action->performed ? 'Yes' : 'No',
                    $action->failed ? 'No' : 'Yes',
                    implode(".\n", $action->errors),
                ];
            }
            $this->table(['Customer', 'Action', 'Performed?', 'Worked?', 'Errors'], $rows);
        }

        return 0;
    }
}