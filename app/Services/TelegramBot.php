<?php

namespace App\Services;

use App\Models\ConfiguracionGeneral;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente mínimo de la API de bots de Telegram (https://core.telegram.org/bots/api).
 * Los errores nunca incluyen el token: se reemplaza por "***" antes de lanzarlos.
 */
class TelegramBot
{
    /** Límites de Telegram: texto de mensaje y pie de foto. */
    public const MAX_TEXTO = 4096;
    public const MAX_PIE_FOTO = 1024;

    public function __construct(private string $token)
    {
    }

    public static function desdeConfiguracion(?ConfiguracionGeneral $cfg = null): ?self
    {
        $cfg ??= ConfiguracionGeneral::first();
        $token = $cfg ? trim((string) $cfg->telegram_bot_token) : '';
        return $token !== '' ? new self($token) : null;
    }

    /** Datos del bot (sirve para validar el token). */
    public function yo(): array
    {
        return $this->llamar('getMe');
    }

    /**
     * Chats que escribieron recientemente al bot (o donde lo agregaron), para obtener el chat_id
     * sin herramientas externas. Telegram solo guarda las actualizaciones de las últimas 24 h.
     */
    public function chatsRecientes(): array
    {
        $chats = [];
        foreach ($this->llamar('getUpdates', ['limit' => 100, 'timeout' => 0]) as $u) {
            $chat = $u['message']['chat'] ?? $u['channel_post']['chat'] ?? $u['my_chat_member']['chat'] ?? null;
            if ($chat) {
                $nombre = $chat['title'] ?? trim(($chat['first_name'] ?? '') . ' ' . ($chat['last_name'] ?? ''));
                $chats[$chat['id']] = ['id' => (string) $chat['id'], 'tipo' => $chat['type'] ?? '', 'nombre' => $nombre ?: ($chat['username'] ?? '')];
            }
        }
        return array_values($chats);
    }

    public function enviarMensaje(string $chatId, string $html): array
    {
        return $this->llamar('sendMessage', [
            'chat_id' => $chatId,
            'text' => self::recortar($html, self::MAX_TEXTO),
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    public function enviarFoto(string $chatId, string $png, string $pieHtml = ''): array
    {
        return $this->llamar('sendPhoto', [
            'chat_id' => $chatId,
            'caption' => self::recortar($pieHtml, self::MAX_PIE_FOTO),
            'parse_mode' => 'HTML',
        ], $png);
    }

    private function llamar(string $metodo, array $datos = [], ?string $foto = null): mixed
    {
        $url = "https://api.telegram.org/bot{$this->token}/{$metodo}";
        try {
            $peticion = Http::timeout(20)->connectTimeout(8);
            $respuesta = $foto !== null
                ? $peticion->attach('photo', $foto, 'grafica.png')->post($url, $datos)
                : $peticion->asForm()->post($url, $datos);
        } catch (\Throwable $e) {
            throw new RuntimeException('Sin conexión con Telegram: ' . $this->ocultarToken($e->getMessage()));
        }

        $json = $respuesta->json();
        if (!is_array($json) || !($json['ok'] ?? false)) {
            $detalle = $json['description'] ?? ('HTTP ' . $respuesta->status());
            throw new RuntimeException('Telegram rechazó la petición: ' . $this->ocultarToken($detalle));
        }
        return $json['result'];
    }

    private function ocultarToken(string $texto): string
    {
        return str_replace($this->token, '***', $texto);
    }

    /** Recorta sin dejar etiquetas HTML abiertas (Telegram rechaza HTML mal formado). */
    private static function recortar(string $html, int $max): string
    {
        if (mb_strlen($html) <= $max) {
            return $html;
        }
        $corto = mb_substr($html, 0, $max - 1);
        $corto = preg_replace('/<[^>]*$/', '', $corto);          // etiqueta cortada a la mitad
        $corto = preg_replace('/&[a-z#0-9]*$/i', '', $corto);    // entidad cortada a la mitad
        foreach (['b', 'i', 'code', 'a'] as $tag) {
            $abiertas = preg_match_all("/<{$tag}[\s>]/", $corto) - preg_match_all("/<\/{$tag}>/", $corto);
            $corto .= str_repeat("</{$tag}>", max(0, $abiertas));
        }
        return $corto . '…';
    }
}
