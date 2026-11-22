<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Sends one test email so you can confirm password-reset and invoice emails really get delivered. */
class MailTest extends Command
{
    protected $signature = 'bizcore:mail-test {to : Address to send the test email to}';

    protected $description = 'Send a test email using the configured mail settings';

    public function handle(): int
    {
        $mailer = config('mail.default');
        $this->line('Mailer: '.$mailer.' · from '.config('mail.from.address'));

        if (in_array($mailer, ['log', 'array'], true)) {
            $this->warn('MAIL_MAILER is "'.$mailer.'": emails are NOT delivered, only written to the log. Set MAIL_MAILER=smtp and the MAIL_* settings (see docs/EMAIL_SETUP.md).');
        }

        try {
            Mail::raw('This is a test email from BIZCORE ERP. If you can read it, password-reset and invoice emails will be delivered.', function ($m) {
                $m->to($this->argument('to'))->subject('BIZCORE test email');
            });
        } catch (\Throwable $e) {
            $this->error('Sending failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent to '.$this->argument('to').'.');

        return self::SUCCESS;
    }
}
