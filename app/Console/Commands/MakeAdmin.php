<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeAdmin extends Command
{
    protected $signature = 'spendless:make-admin {email : Email address of an existing user} {--revoke : Remove admin rights instead}';

    protected $description = 'Give (or take away) access to the admin area';

    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that email address.');

            return self::FAILURE;
        }

        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();

        $this->info(sprintf('%s is %s an admin.', $user->email, $user->is_admin ? 'now' : 'no longer'));

        return self::SUCCESS;
    }
}
