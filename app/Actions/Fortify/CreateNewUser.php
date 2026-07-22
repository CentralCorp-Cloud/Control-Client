<?php

namespace App\Actions\Fortify;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (! Setting::boolean('registration_open', true)) {
            throw ValidationException::withMessages(['email' => 'Les inscriptions sont actuellement fermées.']);
        }
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        return User::create([
            'uuid' => (string) Str::uuid(),
            'name' => $input['name'],
            'email' => Str::lower($input['email']),
            'password' => Hash::make($input['password']),
            'role' => UserRole::User,
            'status' => UserStatus::Active,
        ]);
    }
}
