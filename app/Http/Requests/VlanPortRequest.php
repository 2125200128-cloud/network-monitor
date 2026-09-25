<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VlanPortRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'dispositivo_id' => 'required|exists:dispositivos,id',
            'interface_name' => 'required|string|regex:/^[a-zA-Z0-9\/\.]+$/',
            'mode' => 'required|in:access,trunk',
            'vlan_id' => 'required_if:mode,access|nullable|integer|min:2|max:4094',
        ];
    }

    public function messages(): array
    {
        return [
            'interface_name.regex' => 'El nombre de la interfaz contiene caracteres inválidos. Ejemplo válido: GigabitEthernet0/1',
        ];
    }
}
