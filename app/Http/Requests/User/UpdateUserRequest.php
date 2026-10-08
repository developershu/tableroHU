<?php

namespace App\Http\Requests\User;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'job_title' => 'required|string',
            'name' => 'required|string',
            'phone' => 'string|nullable',
            'email' => ['required', 'email:rfc,dns', Rule::unique('users')->ignore($this->route('user')->id)],
            'password' => 'nullable|min:8|confirmed',
            'roles' => [
                'required',
                'array',
                'min:1',
                function ($attribute, $value, $fail) {
                    $authUser = auth()->user();
                    if ($authUser->isSuperAdmin()) {
                        return;
                    }

                    $forbidden = ['superadmin', 'admin'];
                    foreach ($value as $role) {
                        if (in_array($role, $forbidden)) {
                            $fail("No tenés permisos para asignar el rol '$role'.");
                        }
                    }
                },
            ],
            'avatar' => [File::image(), 'nullable'],
            'area_ids' => ['required', 'array', 'min:1'],
            'area_ids.*' => [
                'integer',
                'distinct',
                'exists:areas,id',
                function ($attribute, $value, $fail) {
                    $authUser = auth()->user();
                    if ($authUser->isSuperAdmin()) {
                        return;
                    }

                    // Un admin de área solo puede asignar áreas que él mismo tiene
                    if (! in_array((int) $value, $authUser->areaIds(), true)) {
                        $fail('No tenés permisos para asignar esa área.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'area_ids' => 'áreas',
            'area_ids.*' => 'área',
        ];
    }
}
