<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MakeMasterAdmin extends Command
{
    protected $signature = 'app:make-master-admin
                            {email : Email do usuário}
                            {--name= : Nome do usuário}
                            {--password= : Senha (se omitida, gera uma aleatória)}';

    protected $description = 'Cria (ou promove) um usuário como master admin (bypass de pagamento/onboarding).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) ($this->option('name') ?: 'Master Admin');
        $password = (string) ($this->option('password') ?: Str::password(16));

        $user = User::firstOrNew(['email' => $email]);
        $created = ! $user->exists;

        if ($created) {
            $user->name = $name;
            $user->password = Hash::make($password);
        } else {
            if (! $user->name) {
                $user->name = $name;
            }
            if ($this->option('password')) {
                $user->password = Hash::make($password);
            }
        }

        $user->is_master_admin = true;
        $user->onboarding_completed_at ??= now();
        $user->save();

        $this->info("Master admin OK: {$user->email}");

        if ($created) {
            $this->line("Senha gerada: {$password}");
        } elseif ($this->option('password')) {
            $this->line('Senha atualizada.');
        }

        return self::SUCCESS;
    }
}

