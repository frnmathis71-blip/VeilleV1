<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'veille:create-admin';

    protected $description = 'Créer un administrateur sans exposer son mot de passe';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Cette commande nécessite une saisie interactive.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->ask('Nom'),
            'email' => mb_strtolower(trim((string) $this->ask('Adresse e-mail'))),
            'password' => $this->secret('Mot de passe (12 caractères minimum)', false),
            'password_confirmation' => $this->secret('Confirmer le mot de passe', false),
        ];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $data['password'];
        $user->is_admin = true;
        // A trusted operator provisions this private account.
        $user->email_verified_at = Carbon::now();
        $user->save();
        $this->info('Administrateur créé. Vous pouvez vous connecter.');

        return self::SUCCESS;
    }
}
