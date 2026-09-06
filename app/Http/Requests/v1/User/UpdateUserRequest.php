<?php
// app/Http/Requests/v1/User/UpdateUserRequest.php

namespace App\Http\Requests\v1\User;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');
        // Allow user to update own profile even without update permission
        if ($user && $this->user()->id === $user->id) {
            return true;
        }
        return $user && $this->user()->can('update', $user);
    }

    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'email'     => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $userId],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:20'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'exists:branches,id'],
            'password'  => ['sometimes', 'nullable', 'string', 'min:8', 'confirmed'],
            'is_active' => ['sometimes', 'boolean'],
            'role'      => ['sometimes', 'string', 'exists:roles,name', 'not_in:patient'],

            // ✅ Photo upload
            'photo'     => ['sometimes', 'nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'], // 5MB
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->has('role')) {
                return;
            }

            $role = $this->input('role');
            $actor = $this->user();

            if (! $actor || ! $actor->hasAnyRole(['super-admin', 'superadmin', 'admin'])) {
                $validator->errors()->add(
                    'role',
                    'You are not authorized to modify user roles.'
                );
                return;
            }

            $requiredRoles = [
                'super-admin' => ['super-admin', 'superadmin'],
                'superadmin'  => ['super-admin', 'superadmin'],
                'admin'       => ['admin', 'super-admin', 'superadmin'],
            ];

            if (
                isset($requiredRoles[$role])
                && ! $actor->hasAnyRole($requiredRoles[$role])
            ) {
                $validator->errors()->add(
                    'role',
                    "You are not allowed to assign the {$role} role."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'photo.image'  => 'The file must be an image',
            'photo.mimes'  => 'Photo must be JPEG, PNG, or WebP',
            'photo.max'    => 'Photo size must be less than 5MB',
            'email.unique' => 'This email is already in use',
        ];
    }
}