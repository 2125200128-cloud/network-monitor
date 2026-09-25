<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VlanProvisionRequest extends FormRequest
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
            'vlan_id' => 'required|integer|min:2|max:4094',
            'vlan_name' => 'required|string|regex:/^[a-zA-Z0-9_-]+$/|max:32',
            'ip_address' => 'nullable|ipv4',
            'subnet_mask' => 'required_with:ip_address|nullable|ipv4',
        ];
    }
    
    public function messages(): array
    {
        return [
            'vlan_name.regex' => 'El nombre de la VLAN solo puede contener letras, números, guiones y guiones bajos (sin espacios).',
        ];
    }
}
