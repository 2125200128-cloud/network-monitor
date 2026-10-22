<?php

namespace App\Support;

/**
 * Cómo lanzar los scripts de worker/ desde PHP, igual en Windows (desarrollo) y en Linux (servidor).
 */
class Python
{
    /**
     * Intérprete de Python, por prioridad: PYTHON_PATH del .env (config noc.python_path), el entorno virtual del proyecto (.venv),
     * la instalación de usuario en Windows y, al final, el del sistema (python3 en Linux, python en Windows).
     */
    public static function binario(): string
    {
        $windows = PHP_OS_FAMILY === 'Windows';
        $localAppData = getenv('LOCALAPPDATA') ?: '';
        $candidatos = array_filter([
            config('noc.python_path'),
            base_path($windows ? '.venv\\Scripts\\python.exe' : '.venv/bin/python'),
            $windows && $localAppData ? $localAppData . '\\Programs\\Python\\Python312\\python.exe' : null,
            $windows && $localAppData ? $localAppData . '\\Programs\\Python\\Python311\\python.exe' : null,
        ]);
        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                return $ruta;
            }
        }
        return $windows ? 'python' : 'python3';
    }

    /** Ruta de un script de worker/. */
    public static function script(string $nombre): string
    {
        return base_path('worker' . DIRECTORY_SEPARATOR . $nombre);
    }

    /**
     * Variables de entorno para el proceso hijo. En Windows, Python necesita SystemRoot para abrir sockets;
     * en Linux basta con PATH, HOME y la configuración regional.
     */
    public static function entorno(array $extra = []): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $systemRoot = getenv('SystemRoot') ?: 'C:\\Windows';
            $base = [
                'SystemRoot' => $systemRoot, 'SYSTEMROOT' => $systemRoot, 'WINDIR' => getenv('WINDIR') ?: $systemRoot,
                'PATH' => getenv('PATH') ?: $systemRoot . '\\system32;' . $systemRoot,
                'TEMP' => getenv('TEMP') ?: $systemRoot . '\\Temp', 'TMP' => getenv('TMP') ?: $systemRoot . '\\Temp',
                'LOCALAPPDATA' => getenv('LOCALAPPDATA') ?: '', 'APPDATA' => getenv('APPDATA') ?: '',
                'COMSPEC' => getenv('COMSPEC') ?: $systemRoot . '\\system32\\cmd.exe',
            ];
        } else {
            $base = [
                'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
                'HOME' => getenv('HOME') ?: sys_get_temp_dir(),
                'LANG' => getenv('LANG') ?: 'C.UTF-8',
                'TMPDIR' => sys_get_temp_dir(),
            ];
        }
        return $base + ['PYTHONIOENCODING' => 'utf-8', 'PYTHONUTF8' => '1'] + $extra;
    }
}
