<?php

namespace App\Http\Requests\Project;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'name' => ['required', 'string', Rule::unique('projects', 'name')],
            'description' => 'string|nullable',
            'area_id' => [
                'required',
                'integer',
                'exists:areas,id',
                function ($attribute, $value, $fail) {
                    $authUser = auth()->user();
                    if ($authUser->isSuperAdmin()) {
                        return;
                    }

                    // Solo se pueden crear/mover proyectos a áreas a las que pertenece el usuario
                    if (! in_array((int) $value, $authUser->areaIds(), true)) {
                        $fail('No tenés permisos para usar esa área.');
                    }
                },
            ],
            'users' => 'array',
        ];
    }
}
