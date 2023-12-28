<?php

namespace bluntelk\IxpManagerXero\Console\Commands;

use bluntelk\IxpManagerXero\Services\DiscordNotifier;
use Webfox\Xero\OauthCredentialManager;

class RefreshXeroToken extends LoggableCommand
{
    protected $signature = 'xero:refresh-token';

    protected $description = 'Keep the Xero integration tokens up to date';

    public function handle(OauthCredentialManager $xeroCredentials): int
    {
        $this->output->title("Refreshing Xero OAuth Token");
        try {
            $xeroCredentials->refresh();
            $this->output->success("Refreshed.");
        } catch (\Exception $e) {
            $this->output->error($e->getMessage());
            DiscordNotifier::notifyError($e, "Refreshing OAuth Token");
        }

        return 0;
    }
}