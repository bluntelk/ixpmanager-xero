<?php

namespace bluntelk\IxpManagerXero\Services;

use Illuminate\Support\Facades\Log;

class DiscordNotifier
{
    public static function notifyError(\Exception $exception, string $context): void
    {
        $payload = [
            "content" => "Unfortunately we have encountered an IXP 🡘 Xero Integration Issue",
            "embeds" => [
                [
                    "title" => "An Exception Occurred",
                    "description" => "Something has gone terribly wrong with the Xero Integration",
                    "color" => 16711680,
                    "fields" => [
                        [
                            "name" => "Message",
                            "value" => $exception->getMessage(),
                        ],
                        [
                            "name" => "Context",
                            "value" => $context,
                        ],
                    ],
                    "author" => [
                        "name" => "IXP Xero Integration",
                    ],
                    "footer" => [
                        "text" => "Sorry for the inconvenience",
                    ],
                    "timestamp" => date("c"),
                ],
            ],
            "username" => "IXP Xero Integration",
            "attachments" => [],
        ];

        self::sendWebhook($payload);
    }

    public static function sendWebhook(array $payload): void
    {
        $discordWebHook = config('ixpxero.discord_webhook');
        if (!$discordWebHook) {
            Log::info("No Discord WebHook configured. not notifying discord");
            return;
        }

        // do notify
        $json = json_encode($payload);

        $ctxOpts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json",
                'content' => $json,
            ]
        ];
        $ctx = stream_context_create($ctxOpts);

        @file_get_contents($discordWebHook, false, $ctx);
    }
}