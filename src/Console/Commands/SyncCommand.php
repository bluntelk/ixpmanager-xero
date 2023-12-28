<?php

namespace bluntelk\IxpManagerXero\Console\Commands;

use bluntelk\IxpManagerXero\Services\XeroSync;
use IXP\Models\Customer;
use Symfony\Component\Console\Output\OutputInterface;

class SyncCommand extends LoggableCommand
{
    protected $signature = 'xero:sync {customer_id?}';

    protected $description = 'Sync all members into Xero';

    public function handle(XeroSync $xeroSync): int
    {
        $this->output->title("IXP Manager -> Xero Sync");

        if (!$xeroSync->isXeroConfigValid()) {
            $this->error(
                "Your Xero Config is invalid. Please follow the setup steps in the README.md",
                OutputInterface::VERBOSITY_QUIET
            );
            return 1;
        }
        if ($this->argument('customer_id') && $customer = Customer::find($this->argument('customer_id'))) {
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