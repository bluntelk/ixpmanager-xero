<?php

namespace bluntelk\IxpManagerXero\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

abstract class LoggableCommand extends Command
{
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
}