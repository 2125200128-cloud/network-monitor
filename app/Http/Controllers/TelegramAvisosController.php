<?php

namespace App\Http\Controllers;

use App\Models\ConfiguracionGeneral;
use App\Services\AlertasTelegram;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Alertas → Telegram: los avisos enviados tal como llegan (copia en telegram_mensajes), para quien no tiene Telegram.
 */
class TelegramAvisosController extends Controller
{
    public function index(Request $request)
    {
        $cfg = ConfiguracionGeneral::first();
        $grupo = in_array($request->query('grupo'), ['redes', 'servidores'], true) ? $request->query('grupo') : null;

        $mensajes = DB::table('telegram_mensajes')->orderByDesc('id')->limit(300)->get()
            ->each(fn ($m) => $m->grupo = AlertasTelegram::grupoDe($cfg, $m->chat_id))
            ->when($grupo, fn ($c) => $c->filter(fn ($m) => in_array($m->grupo, [$grupo, 'todo'], true)))
            ->values();

        // Nombre con el que el bot aparece en los grupos (se pregunta a Telegram una vez al día por token)
        $token = (string) $cfg?->getRawOriginal('telegram_bot_token');
        $botNombre = $token === '' ? 'Bot del NOC' : \Illuminate\Support\Facades\Cache::remember('telegram_bot_nombre_' . md5($token), 86400, function () use ($cfg) {
            try {
                return \App\Services\TelegramBot::desdeConfiguracion($cfg)?->yo()['first_name'] ?? 'Bot del NOC';
            } catch (\Throwable) {
                return 'Bot del NOC';
            }
        });

        return view('alertas.telegram', [
            'botNombre' => $botNombre,
            'cfg' => $cfg,
            'mensajes' => $mensajes,
            'grupo' => $grupo,
            'enviados24h' => DB::table('telegram_mensajes')->where('created_at', '>=', now()->subDay())->where('ok', true)->count(),
            'fallidos24h' => DB::table('telegram_mensajes')->where('created_at', '>=', now()->subDay())->where('ok', false)->count(),
            'pestana' => 'telegram',
        ]);
    }

    /** Imagen (tarjeta) que acompañó a un aviso enviado. */
    public function imagen(int $id)
    {
        abort_unless(Storage::exists("telegram/{$id}.png"), 404);
        return response(Storage::get("telegram/{$id}.png"), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=86400']);
    }
}
