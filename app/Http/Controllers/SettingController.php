<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ConfiguracionGeneral;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class SettingController extends Controller
{
    public function index(Request $request)
    {
        $settings = ConfiguracionGeneral::firstOrCreate([], [
            'intervalo_sondeo_segundos' => 60,
            'comunidad_snmp_default' => 'public',
            'ip_switch_core' => env('IP_SWITCH_CORE', '10.4.254.3'),
            'umbral_cpu_warning' => 85,
            'umbral_loss_warning' => 5,
        ]);

        $users = User::all();
        $section = $request->query('section', 'general');

        return view('settings.index', compact('settings', 'users', 'section'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'intervalo_sondeo_segundos' => 'required|integer|min:10',
            'comunidad_snmp_default' => 'required|string|max:255',
            'ip_switch_core' => 'required|ip',
            'umbral_cpu_warning' => 'required|integer|min:1|max:100',
            'umbral_loss_warning' => 'required|integer|min:1|max:100',
        ]);

        $settings = ConfiguracionGeneral::first();
        if ($settings) {
            $settings->update($request->all());
        }

        return redirect()->route('settings.index', ['section' => 'general'])->with('success', 'Configuraciones guardadas exitosamente.');
    }

    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:Admin,Operador,admin,operator,Auditor',
        ]);

        $newRole = ucfirst(strtolower($request->role));
        if ($newRole == 'Operator') $newRole = 'Operador';

        // Evitar que el admin activo se quite su propio rol por accidente si es el unico
        if ($user->id === auth()->id() && $newRole === 'Operador') {
            return redirect()->route('settings.index', ['section' => 'cuentas'])->with('error', 'No puedes quitarte el rol de Administrador a ti mismo.');
        }

        $user->update(['role' => $newRole]);

        return redirect()->route('settings.index', ['section' => 'cuentas'])->with('success', 'Rol de "' . $user->name . '" actualizado a ' . $newRole . '.');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'prefijo' => 'nullable|string|in:Ing.,Lic.,Tec.,TSU.,Dr.,Mtro.,Mtra.,C.P.,Arq.',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role' => 'required|in:Admin,Operador,Auditor',
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        User::create([
            'name' => $request->name,
            'prefijo' => $request->prefijo,
            'avatar' => $avatarPath,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return redirect()->route('settings.index', ['section' => 'cuentas'])->with('success', 'Cuenta de "' . $request->name . '" creada exitosamente.');
    }

    public function deleteUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('settings.index', ['section' => 'cuentas'])->with('error', 'No puedes eliminar tu propia cuenta.');
        }

        // Delete avatar file if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('settings.index', ['section' => 'cuentas'])->with('success', 'Cuenta de "' . $name . '" eliminada.');
    }

    public function updateUser(Request $request, User $user)
    {
        if ($user->id === auth()->id() && $request->role !== 'Admin') {
            return redirect()->route('settings.index', ['section' => 'cuentas'])->with('error', 'No puedes quitarte el rol de Administrador a ti mismo.');
        }

        $rules = [
            'name' => 'required|string|max:255',
            'prefijo' => 'nullable|string|in:Ing.,Lic.,Tec.,TSU.,Dr.,Mtro.,Mtra.,C.P.,Arq.',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:Admin,Operador,Auditor',
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Password::min(8)];
        }

        $request->validate($rules);

        $data = [
            'name' => $request->name,
            'prefijo' => $request->prefijo,
            'email' => $request->email,
            'role' => $request->role,
        ];

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // Handle remove avatar checkbox
        if ($request->has('remove_avatar') && !$request->hasFile('avatar')) {
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = null;
        }

        // Only update password if provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('settings.index', ['section' => 'cuentas'])->with('success', 'Cuenta de "' . $request->name . '" actualizada exitosamente.');
    }
}
