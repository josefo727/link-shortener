<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

final class CreateApiTokenCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'api:token:create
                            {email : The user email}
                            {--name=api-token : The token name}';

    /**
     * @var string
     */
    protected $description = 'Create an API token for a user';

    public function handle(): int
    {
        /** @var string $email */
        $email = $this->argument('email');

        /** @var string $tokenName */
        $tokenName = $this->option('name');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $token = $user->createToken($tokenName);

        $this->info('Token created successfully:');
        $this->newLine();
        $this->line($token->plainTextToken);
        $this->newLine();
        $this->warn('Save this token - it will not be shown again.');

        return self::SUCCESS;
    }
}
