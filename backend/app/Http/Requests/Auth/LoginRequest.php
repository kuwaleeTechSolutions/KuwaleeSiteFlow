<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // anyone may attempt to log in; the controller enforces credentials
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            // Optional — used only for multi-org email collisions (rare) where
            // the same email exists under more than one organization.
            'organization_slug' => ['nullable', 'string', 'max:100'],
        ];
    }
}
