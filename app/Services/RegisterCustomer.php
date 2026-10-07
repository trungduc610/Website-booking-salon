<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterCustomer
{
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $role = Role::where('code', 'CUSTOMER')->sole();
            $user = User::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password_hash' => Hash::make($data['password']),
            ]);
            $user->customerProfile()->create();
            $user->roles()->attach($role->id, ['granted_at' => now()]);
            return $user;
        }, 3);
    }
}
