<?php

namespace App\Http\Requests\v1\User;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['nullable', 'boolean'],
            // Staff accounts must land with a role, or they log in with zero
            // permissions. Block `patient` — that role is for the self-service
            // portal and is assigned by the registration flow, not here.
            'role' => [
                'required',
                'string',
                'exists:roles,name',
                'not_in:patient',
            ],
        ];
    }

    public function withValidator($validator): void
    {
        // Prevent privilege escalation: a creator may not hand out a role more
        // powerful than their own. `super-admin` requires super-admin; `admin`
        // requires admin or super-admin. This keeps a dentist (who now holds
        // `user.create`) from minting admin or super-admin accounts.
        $validator->after(function ($validator) {
            $role    = $this->input('role');
            $creator = $this->user();

            $requiredRoles = [
                'super-admin' => ['super-admin', 'superadmin'],
                'superadmin'  => ['super-admin', 'superadmin'],
                'admin'       => ['admin', 'super-admin', 'superadmin'],
            ];

            if (
                isset($requiredRoles[$role])
                && ! $creator->hasAnyRole($requiredRoles[$role])
            ) {
                $validator->errors()->add(
                    'role',
                    "You are not allowed to assign the {$role} role."
                );
            }
        });
    }
}