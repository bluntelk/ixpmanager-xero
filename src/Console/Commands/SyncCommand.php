<?php

namespace bluntelk\IxpManagerXero\Console\Commands;

use bluntelk\IxpManagerXero\Services\XeroSync;
use Entities\Customer;
use Illuminate\Console\Command;
use Repositories\Customer as CustomerRepo;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends Command
{
    protected $signature = 'xero:sync';

    protected $description = 'Sync all members into Xero';

    public function __construct() {
        parent::__construct();
    }

    public function handle()
    {
        $this->output->title("IXP Manager -> Xero Sync");

        $xeroSync = \App::make(XeroSync::class);

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
    }
}