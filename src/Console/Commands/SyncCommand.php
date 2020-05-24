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

        $customerRepo = \D2EM::getRepository(Customer::class );

        $xeroSync = \App::make(XeroSync::class);

        /** @var Customer[] $list */
        $list = $customerRepo->getCurrentActive();
        $this->info("There are " . count($list) . " members to sync", OutputInterface::VERBOSITY_VERBOSE);
        foreach ($list as $customer) {
            $this->info("* Syncing <comment>{$customer->getName()}</comment>", OutputInterface::VERBOSITY_VERBOSE);

            $xeroSync->syncCustomerToXero($customer);
        }

        $this->info('Sync Complete');
    }
}