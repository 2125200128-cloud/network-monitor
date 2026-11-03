<?php

namespace App\Services;

use App\Models\ConfiguracionGeneral;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Lectura del vCenter por su API REST (vSphere 7.0 U2+): hosts, VMs, datastores y clústeres.
 * Solo consultas GET con un usuario de solo lectura; la sesión se cierra al terminar.
 */
class VcenterService
{
    private const TIMEOUT_S = 15;
    public const CACHE_S = 60;

    /** ¿Hay host, usuario y contraseña guardados? */
    public static function configurado(): bool
    {
        $c = ConfiguracionGeneral::first();
        return $c && $c->vcenter_host && $c->vcenter_usuario && $c->getRawOriginal('vcenter_password');
    }

    /**
     * Prueba una conexión (con los datos dados o los guardados) y cuenta lo que el usuario puede ver.
     * @return array{ok: bool, mensaje: string}
     */
    public function probar(?string $host = null, ?string $usuario = null, ?string $clave = null): array
    {
        $c = ConfiguracionGeneral::first();
        $host = $host ?: $c?->vcenter_host;
        $usuario = $usuario ?: $c?->vcenter_usuario;
        $clave = $clave ?: $c?->vcenter_password;
        if (!$host || !$usuario || !$clave) {
            return ['ok' => false, 'mensaje' => 'Falta el host, el usuario o la contraseña del vCenter.'];
        }
        try {
            return $this->conSesion($host, $usuario, $clave, function ($get) use ($host) {
                $hosts = $get('/api/vcenter/host');
                $vms = $get('/api/vcenter/vm');
                return ['ok' => true, 'mensaje' => "Conectado a {$host}: el usuario ve " . count($hosts) . ' hosts y ' . count($vms) . ' VMs.'];
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    /**
     * Inventario del vCenter, compartido CACHE_S segundos entre pantallas.
     * @return array{ok: bool, error?: string, hosts?: array, vms?: array, datastores?: array, clusters?: array, leido?: string}
     */
    public function resumen(): array
    {
        if (!self::configurado()) {
            return ['ok' => false, 'error' => 'sin_configurar'];
        }
        return Cache::remember('vcenter_resumen', self::CACHE_S, function () {
            $c = ConfiguracionGeneral::first();
            try {
                return $this->conSesion($c->vcenter_host, $c->vcenter_usuario, $c->vcenter_password, function ($get) {
                    $hosts = $get('/api/vcenter/host');
                    // Qué VMs corre cada host (filtro por host de la propia API)
                    foreach ($hosts as &$h) {
                        $h['vms'] = array_column($get('/api/vcenter/vm', ['hosts' => $h['host']]), 'vm');
                    }
                    unset($h);
                    $vms = $get('/api/vcenter/vm');
                    $hostDeVm = [];
                    foreach ($hosts as $h) {
                        foreach ($h['vms'] as $vm) {
                            $hostDeVm[$vm] = $h['name'];
                        }
                    }
                    foreach ($vms as &$vm) {
                        $vm['host'] = $hostDeVm[$vm['vm']] ?? null;
                    }
                    unset($vm);
                    return [
                        'ok' => true,
                        'hosts' => $hosts,
                        'vms' => $vms,
                        'datastores' => $get('/api/vcenter/datastore'),
                        'clusters' => $get('/api/vcenter/cluster'),
                        'leido' => now()->toIso8601String(),
                    ];
                });
            } catch (\Throwable $e) {
                return ['ok' => false, 'error' => $e->getMessage()];
            }
        });
    }

    /** https://<vcenter> tal como está en Ajustes, o null si no hay. */
    public static function base(): ?string
    {
        $host = ConfiguracionGeneral::first()?->vcenter_host;
        return $host ? 'https://' . preg_replace('#^https?://#', '', rtrim($host, '/')) : null;
    }

    /**
     * Ligas directas al vSphere Client, por nombre en minúsculas: ['vms' => [nombre => url], 'hosts' => [nombre => url]].
     * Salen del inventario ya leído (resumen); vacías si el vCenter no se puede leer.
     */
    public function ligas(): array
    {
        $r = $this->resumen();
        $uuid = ($r['ok'] ?? false) ? $this->instancia() : null;
        if (!$uuid) {
            return ['vms' => [], 'hosts' => []];
        }
        $liga = fn (string $tipo, string $clase, string $ref) => self::base() . "/ui/app/{$tipo};nav=h/urn:vmomi:{$clase}:{$ref}:{$uuid}/summary";
        // Dos VMs con el mismo nombre: no se sabe cuál es, mejor sin liga
        $vms = collect($r['vms'] ?? [])->groupBy(fn ($v) => mb_strtolower($v['name'] ?? ''))->filter(fn ($g) => $g->count() === 1)
            ->map(fn ($g) => $liga('vm', 'VirtualMachine', $g->first()['vm']));
        $hosts = collect($r['hosts'] ?? [])->mapWithKeys(fn ($h) => [mb_strtolower($h['name'] ?? '') => $liga('host', 'HostSystem', $h['host'])]);
        return ['vms' => $vms->all(), 'hosts' => $hosts->all()];
    }

    /**
     * Identificador de la instancia del vCenter (va dentro de las ligas del vSphere Client).
     * RetrieveServiceContent de la API SOAP no pide sesión. Se guarda un día; si falla, se reintenta en 10 min.
     */
    private function instancia(): ?string
    {
        $clave = 'vcenter_instancia_' . md5((string) self::base());
        $uuid = Cache::get($clave);
        if ($uuid === null) {
            $xml = '<?xml version="1.0"?><soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Body>'
                . '<RetrieveServiceContent xmlns="urn:vim25"><_this type="ServiceInstance">ServiceInstance</_this></RetrieveServiceContent>'
                . '</soapenv:Body></soapenv:Envelope>';
            try {
                $resp = Http::timeout(self::TIMEOUT_S)->connectTimeout(5)->withOptions(['verify' => false])
                    ->withHeaders(['SOAPAction' => 'urn:vim25/7.0'])->withBody($xml, 'text/xml')->post(self::base() . '/sdk');
                $uuid = preg_match('#<instanceUuid>([0-9a-f-]+)</instanceUuid>#i', $resp->body(), $m) ? $m[1] : '';
            } catch (\Throwable) {
                $uuid = '';
            }
            Cache::put($clave, $uuid, $uuid ? 86400 : 600);
        }
        return $uuid ?: null;
    }

    /** Abre una sesión, ejecuta las consultas y la cierra aunque algo falle. */
    private function conSesion(string $host, string $usuario, string $clave, callable $trabajo): array
    {
        $base = 'https://' . preg_replace('#^https?://#', '', rtrim($host, '/'));
        $http = fn () => Http::timeout(self::TIMEOUT_S)->connectTimeout(5)
            ->withOptions(['verify' => false])   // certificado propio del vCenter (red interna)
            ->acceptJson();

        try {
            $login = $http()->withBasicAuth($usuario, $clave)->post("{$base}/api/session");
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw new \RuntimeException("No se pudo conectar a {$host} (red o puerto 443).");
        }
        if ($login->status() === 401) {
            throw new \RuntimeException('El vCenter rechazó el usuario o la contraseña.');
        }
        if (!$login->successful()) {
            throw new \RuntimeException("El vCenter respondió HTTP {$login->status()} al iniciar sesión.");
        }
        $token = trim((string) $login->json(), '"');

        $get = function (string $ruta, array $consulta = []) use ($http, $base, $token) {
            $r = $http()->withHeaders(['vmware-api-session-id' => $token])->get($base . $ruta, $consulta);
            if ($r->status() === 403) {
                throw new \RuntimeException("El usuario no tiene permiso de lectura sobre {$ruta}.");
            }
            if (!$r->successful()) {
                throw new \RuntimeException("El vCenter respondió HTTP {$r->status()} en {$ruta}.");
            }
            return (array) $r->json();
        };

        try {
            return $trabajo($get);
        } finally {
            try {
                $http()->withHeaders(['vmware-api-session-id' => $token])->delete("{$base}/api/session");
            } catch (\Throwable) {
                // la sesión expira sola en el vCenter
            }
        }
    }
}
