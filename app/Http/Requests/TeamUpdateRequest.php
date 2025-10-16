<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeamUpdateRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array {
        $id = $this->route('id');

        return [
            'name'    => ['required','string','max:120',
                Rule::unique('teams')
                    ->ignore($id) // ignora el actual
                    ->where(fn($q) => $q->where('city', $this->input('city'))),
            ],
            'city'    => ['required','string','max:120'],
            'logoUrl' => ['nullable','string','max:255'],
        ];
    }

    public function validated($key = null, $default = null) {
        $data = parent::validated();
        return [
            'name'     => $data['name'],
            'city'     => $data['city'],
            'logo_url' => $data['logoUrl'] ?? null,
        ];
    }
}
