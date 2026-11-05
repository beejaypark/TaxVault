<?php

namespace App\Console\Commands;

use App\Domain\Notifications\Services\EmailNotificationSender;
use Illuminate\Console\Command;

class SendEmailNotifications extends Command
{
    protected $signature = 'notifications:send-emails';

    protected $description = 'Send the review digest and lodgement deadline reminder emails that users have turned on.';

    public function handle(EmailNotificationSender $sender): int
    {
        $sent = $sender->run();

        $this->info("Sent {$sent['review']} review digest(s) and {$sent['deadline']} deadline reminder(s).");

        return self::SUCCESS;
    }
}
