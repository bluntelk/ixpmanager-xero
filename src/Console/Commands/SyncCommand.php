<?php

namespace bluntelk\IxpManagerXero\Console\Commands;

use bluntelk\IxpManagerXero\Services\XeroSync;
use Entities\Customer;
use Illuminate\Console\Command;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    protected $signature = 'xero:sync {customer_id?}';

    protected $description = 'Sync all members into Xero';

    public function __construct() {
        parent::__construct();
        Event::listen(MessageLogged::class, function(MessageLogged $event) {
            $output = $this->getOutput();
            if (!$output) {
                // no output? no problem - let's just be quiet
                return;
            }

            switch($event->level) {
                case LogLevel::CRITICAL:
                case LogLevel::ERROR:
                case LogLevel::ALERT:
                case LogLevel::EMERGENCY:
                    $output->error($event->message);
                    break;
                case LogLevel::WARNING:
                    $output->warning($event->message);
                    break;
                case LogLevel::DEBUG:
                    $output->writeln($event->message, OutputInterface::VERBOSITY_VERY_VERBOSE);
                    break;
                case LogLevel::INFO:
                default:
                    $output->writeln($event->message, OutputInterface::VERBOSITY_VERBOSE);
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
        if ($this->argument('customer_id') && $customer = \D2EM::getRepository(Customer::class )->find($this->argument('customer_id'))) {
            $actions = $xeroSync->performSyncOne($customer);
        } else {
            $actions = $xeroSync->performSyncAll();
        }


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