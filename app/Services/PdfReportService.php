<?php

namespace App\Services;

use App\Libraries\FPDF\FPDF;
use App\Models\ConfiguracionDispositivo;
use App\Models\Dispositivo;
use Carbon\Carbon;

/**
 * Plantilla institucional de la Secretaría de la Hacienda Pública (Gobierno de Jalisco):
 * colores del logotipo, encabezado con logo, pie con folio de páginas y componentes reutilizables.
 */
class InstitutionalPdf extends FPDF
{
    // Colores del logotipo institucional y neutros de lectura
    const AZUL = [91, 125, 149];        // #5b7d95
    const AZUL_OSCURO = [52, 76, 95];
    const NARANJA = [240, 127, 18];     // #f07f12
    const TEXTO = [28, 39, 60];
    const TENUE = [99, 112, 135];
    const BORDE = [216, 222, 232];
    const FONDO = [246, 248, 251];
    const VERDE = [5, 150, 105];
    const ROJO = [200, 38, 45];
    const AMBAR = [180, 95, 6];

    public $reportTitle = 'Reporte técnico de infraestructura';
    public $reportSubtitle = '';
    public $generadoPor = 'Sistema NOC';
    public $generadoEn;

    function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->SetMargins(12, 12, 12);
        $this->SetAutoPageBreak(true, 20);
        $this->generadoEn = now();
        $this->SetTitle($this->encodeText('Secretaría de la Hacienda Pública - NOC'));
        $this->SetAuthor($this->encodeText('Secretaría de la Hacienda Pública'));
    }

    public function ancho(): float
    {
        return $this->GetPageWidth() - $this->lMargin - $this->rMargin;
    }

    function Header()
    {
        $w = $this->GetPageWidth();

        // Franja superior con los dos colores del logotipo
        $this->color('fill', self::AZUL);
        $this->Rect(0, 0, $w, 3, 'F');
        $this->color('fill', self::NARANJA);
        $this->Rect(0, 0, 42, 3, 'F');

        $logo = public_path('images/logo.png');
        if (is_file($logo)) {
            $this->Image($logo, 12, 8, 46);
        }

        // Dependencia (alineado a la derecha)
        $this->SetXY(12, 8.5);
        $this->SetFont('Helvetica', '', 7);
        $this->color('text', self::TENUE);
        $this->Cell(0, 4, $this->encodeText('GOBIERNO DEL ESTADO DE JALISCO'), 0, 1, 'R');
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->color('text', self::AZUL_OSCURO);
        $this->Cell(0, 4.5, $this->encodeText('Centro de Operaciones de Red (NOC)'), 0, 1, 'R');
        $this->SetFont('Helvetica', '', 7);
        $this->color('text', self::TENUE);
        $this->Cell(0, 4, $this->encodeText('Sistema de Monitoreo de Infraestructura de Red'), 0, 1, 'R');

        // Título del documento
        $this->SetXY(12, 27);
        $this->SetFont('Helvetica', 'B', 14);
        $this->color('text', self::TEXTO);
        $this->Cell(0, 7, $this->encodeText($this->reportTitle), 0, 1, 'L');
        if ($this->reportSubtitle !== '') {
            $this->SetFont('Helvetica', '', 8.5);
            $this->color('text', self::TENUE);
            $this->Cell(0, 4.5, $this->encodeText($this->reportSubtitle), 0, 1, 'L');
        }

        $y = $this->GetY() + 2;
        $this->color('draw', self::BORDE);
        $this->SetLineWidth(0.3);
        $this->Line(12, $y, $w - 12, $y);
        $this->color('draw', self::NARANJA);
        $this->SetLineWidth(0.8);
        $this->Line(12, $y, 30, $y);
        $this->SetLineWidth(0.2);
        $this->SetY($y + 5);
    }

    function Footer()
    {
        $w = $this->GetPageWidth();
        $this->SetY(-14);
        $this->color('draw', self::BORDE);
        $this->SetLineWidth(0.3);
        $this->Line(12, $this->GetY(), $w - 12, $this->GetY());

        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 7);
        $this->color('text', self::TENUE);
        $pie = 'Secretaría de la Hacienda Pública · Documento de uso interno · Generado el '
            . $this->generadoEn->format('d/m/Y H:i') . ' por ' . $this->generadoPor;
        $this->Cell(($w - 24) * 0.8, 4, $this->encodeText($pie), 0, 0, 'L');
        $this->SetFont('Helvetica', 'B', 7);
        $this->Cell(($w - 24) * 0.2, 4, $this->encodeText('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    // ===== Componentes =====

    public function color(string $tipo, array $rgb): void
    {
        match ($tipo) {
            'fill' => $this->SetFillColor(...$rgb),
            'draw' => $this->SetDrawColor(...$rgb),
            default => $this->SetTextColor(...$rgb),
        };
    }

    /** Título de sección con marca naranja y descripción opcional. */
    public function seccion(string $titulo, ?string $descripcion = null): void
    {
        $this->asegurarEspacio(16);
        $this->Ln(2);
        $y = $this->GetY();
        $this->color('fill', self::NARANJA);
        $this->Rect(12, $y + 1, 1.2, 4.5, 'F');
        $this->SetX(15.5);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->color('text', self::AZUL_OSCURO);
        $this->Cell(0, 6.5, $this->encodeText($titulo), 0, 1, 'L');
        if ($descripcion) {
            $this->SetX(15.5);
            $this->SetFont('Helvetica', '', 8);
            $this->color('text', self::TENUE);
            $this->MultiCell(0, 4, $this->encodeText($descripcion), 0, 'L');
        }
        $this->Ln(1.5);
    }

    /** Cuadrícula de pares etiqueta/valor dentro de un recuadro claro. */
    public function fichaDatos(array $pares, int $columnas = 2): void
    {
        $filas = (int) ceil(count($pares) / $columnas);
        $alto = $filas * 10 + 4;
        $this->asegurarEspacio($alto + 2);
        $x0 = 12;
        $y0 = $this->GetY();
        $anchoCol = $this->ancho() / $columnas;

        $this->color('fill', self::FONDO);
        $this->color('draw', self::BORDE);
        $this->RoundedRect($x0, $y0, $this->ancho(), $alto, 2, 'DF');

        foreach (array_values($pares) as $i => [$etiqueta, $valor, $colorValor]) {
            $col = $i % $columnas;
            $fila = intdiv($i, $columnas);
            $x = $x0 + 4 + $col * $anchoCol;
            $y = $y0 + 2.5 + $fila * 10;
            $this->SetXY($x, $y);
            $this->SetFont('Helvetica', '', 6.8);
            $this->color('text', self::TENUE);
            $this->Cell($anchoCol - 6, 3.5, $this->encodeText(mb_strtoupper($etiqueta)), 0, 2, 'L');
            $this->SetFont('Helvetica', 'B', 9);
            $this->color('text', $colorValor ?? self::TEXTO);
            $this->Cell($anchoCol - 6, 5, $this->encodeText($this->recortar($valor, $anchoCol - 6)), 0, 0, 'L');
        }
        $this->SetY($y0 + $alto + 3);
    }

    /** Fila de tarjetas de indicadores: [etiqueta, valor, rgb del acento]. */
    public function tarjetasKpi(array $tarjetas): void
    {
        $this->asegurarEspacio(24);
        $sep = 4;
        $n = count($tarjetas);
        $w = ($this->ancho() - $sep * ($n - 1)) / $n;
        $y = $this->GetY();
        foreach (array_values($tarjetas) as $i => [$etiqueta, $valor, $acento]) {
            $x = 12 + $i * ($w + $sep);
            $this->color('fill', [255, 255, 255]);
            $this->color('draw', self::BORDE);
            $this->RoundedRect($x, $y, $w, 20, 2, 'DF');
            $this->color('fill', $acento);
            $this->Rect($x, $y + 3, 1.2, 14, 'F');
            $this->SetXY($x + 5, $y + 3.5);
            $this->SetFont('Helvetica', '', 7);
            $this->color('text', self::TENUE);
            $this->Cell($w - 8, 4, $this->encodeText(mb_strtoupper($etiqueta)), 0, 2, 'L');
            $this->SetFont('Helvetica', 'B', 15);
            $this->color('text', $acento);
            $this->Cell($w - 8, 9, $this->encodeText((string) $valor), 0, 0, 'L');
        }
        $this->SetY($y + 24);
    }

    /** Encabezado de tabla. $cols = [[titulo, ancho, alineación], ...] */
    public function encabezadoTabla(array $cols): void
    {
        $this->SetX(12);
        $this->SetFont('Helvetica', 'B', 7.5);
        $this->color('fill', self::AZUL);
        $this->color('text', [255, 255, 255]);
        foreach ($cols as [$titulo, $ancho, $alin]) {
            $this->Cell($ancho, 7, $this->encodeText($titulo), 0, 0, $alin, true);
        }
        $this->Ln();
    }

    /**
     * Fila de tabla con fondo alterno; repite el encabezado al saltar de página.
     * Cada celda es texto o [texto, rgb] para colorearla.
     */
    public function filaTabla(array $cols, array $celdas, bool $alterna, float $alto = 6.2): void
    {
        if ($this->GetY() + $alto > $this->GetPageHeight() - 22) {
            $this->AddPage($this->CurOrientation);
            $this->encabezadoTabla($cols);
        }
        $this->SetX(12);
        $this->color('fill', $alterna ? self::FONDO : [255, 255, 255]);
        $this->color('draw', self::BORDE);
        foreach ($cols as $i => [, $ancho, $alin]) {
            [$texto, $rgb, $negrita] = is_array($celdas[$i]) ? $celdas[$i] + [1 => self::TEXTO, 2 => true] : [$celdas[$i], self::TEXTO, false];
            $this->SetFont('Helvetica', $negrita ? 'B' : '', 7.8);
            $this->color('text', $rgb);
            $this->Cell($ancho, $alto, $this->encodeText($this->recortar((string) $texto, $ancho - 2)), 'B', 0, $alin, true);
        }
        $this->Ln();
    }

    /** Recuadro de aviso (sin datos, notas). */
    public function aviso(string $texto): void
    {
        $this->asegurarEspacio(14);
        $y = $this->GetY();
        $this->color('fill', self::FONDO);
        $this->color('draw', self::BORDE);
        $this->RoundedRect(12, $y, $this->ancho(), 11, 2, 'DF');
        $this->SetXY(16, $y + 3);
        $this->SetFont('Helvetica', '', 8.5);
        $this->color('text', self::TENUE);
        $this->Cell($this->ancho() - 8, 5, $this->encodeText($texto), 0, 1, 'L');
        $this->SetY($y + 14);
    }

    public function asegurarEspacio(float $alto): void
    {
        if ($this->GetY() + $alto > $this->GetPageHeight() - 22) {
            $this->AddPage($this->CurOrientation);
        }
    }

    /** Recorta el texto con "…" para que quepa en el ancho dado (en mm). */
    public function recortar(string $texto, float $ancho): string
    {
        if ($this->GetStringWidth($this->encodeText($texto)) <= $ancho) {
            return $texto;
        }
        while (mb_strlen($texto) > 1 && $this->GetStringWidth($this->encodeText($texto . '...')) > $ancho) {
            $texto = mb_substr($texto, 0, -1);
        }
        return rtrim($texto) . '...';
    }

    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        $op = $style == 'F' ? 'f' : (($style == 'FD' || $style == 'DF') ? 'B' : 'S');
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $x * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1 * $this->k, ($h - $y1) * $this->k,
            $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }

    public function encodeText($text)
    {
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'windows-1252//TRANSLIT', (string) $text);
        }
        return mb_convert_encoding((string) $text, 'Windows-1252', 'UTF-8');
    }
}

class PdfReportService
{
    /** [etiqueta legible, color] de un estado de equipo. */
    private function estadoEquipo(?string $estado): array
    {
        return match ($estado) {
            'online' => ['En línea', InstitutionalPdf::VERDE],
            'warning' => ['Con advertencia', InstitutionalPdf::AMBAR],
            'offline' => ['Sin respuesta', InstitutionalPdf::ROJO],
            default => ['Sin dato', InstitutionalPdf::TENUE],
        };
    }

    private function nuevoPdf(string $orientacion, string $titulo, string $subtitulo): InstitutionalPdf
    {
        $pdf = new InstitutionalPdf($orientacion, 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->reportTitle = $titulo;
        $pdf->reportSubtitle = $subtitulo;
        $pdf->generadoPor = auth()->user()?->name ?? 'Sistema NOC';
        $pdf->AddPage();
        return $pdf;
    }

    private function responder(InstitutionalPdf $pdf, string $nombreArchivo)
    {
        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"',
        ]);
    }

    private function nombreSeguro(?string $texto): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $texto ?: 'equipo');
    }

    /**
     * Certificado de respaldo de configuración (NCM)
     */
    public function descargarReporteConfiguracion(ConfiguracionDispositivo $config)
    {
        $disp = $config->dispositivo;
        $pdf = $this->nuevoPdf('P', 'Certificado de respaldo de configuración',
            ($disp->nombre ?? 'Equipo') . ' · ' . ($disp->ip ?? 'sin IP') . ' · respaldo del ' . $config->created_at->format('d/m/Y H:i'));

        $pdf->seccion('Datos del respaldo', 'Copia de la configuración obtenida del equipo. La huella SHA-256 permite comprobar que el archivo no ha sido modificado.');
        $pdf->fichaDatos([
            ['Equipo', $disp->nombre ?? 'Sin dato', null],
            ['Dirección IP', $disp->ip ?? 'Sin dato', null],
            ['Ubicación', $disp->ubicacion ?: 'No especificada', null],
            ['Tipo de configuración', $config->tipo === 'startup' ? 'De arranque (startup)' : 'En ejecución (running)', InstitutionalPdf::NARANJA],
            ['Fecha del respaldo', $config->created_at->format('d/m/Y H:i:s'), null],
            ['Responsable', $config->user?->name ?? 'Respaldo automático', null],
        ]);

        $pdf->SetFont('Helvetica', '', 6.8);
        $pdf->color('text', InstitutionalPdf::TENUE);
        $pdf->Cell(0, 4, $pdf->encodeText('HUELLA SHA-256'), 0, 1);
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->color('text', InstitutionalPdf::TEXTO);
        $pdf->Cell(0, 5, $config->checksum_sha256, 0, 1);
        $pdf->Ln(2);

        $lineas = explode("\n", str_replace("\r", '', (string) $config->contenido));
        $pdf->seccion('Contenido de la configuración', count($lineas) . ' líneas.');

        foreach ($lineas as $idx => $linea) {
            if ($pdf->GetY() + 4 > $pdf->GetPageHeight() - 22) {
                $pdf->AddPage();
            }
            $pdf->SetX(12);
            $pdf->color('fill', $idx % 2 ? [255, 255, 255] : InstitutionalPdf::FONDO);
            $pdf->SetFont('Courier', '', 7.2);
            $pdf->color('text', InstitutionalPdf::TENUE);
            $pdf->Cell(12, 4, str_pad((string) ($idx + 1), 4, ' ', STR_PAD_LEFT), 0, 0, 'R', true);
            $pdf->color('text', InstitutionalPdf::TEXTO);
            $pdf->Cell($pdf->ancho() - 12, 4, ' ' . $pdf->encodeText(mb_substr($linea, 0, 118)), 0, 1, 'L', true);
        }

        return $this->responder($pdf, 'Respaldo_' . $this->nombreSeguro($disp->nombre) . '_' . $config->tipo . '_' . $config->created_at->format('Ymd_His') . '.pdf');
    }

    /**
     * Ficha técnica y estado de un equipo
     */
    public function descargarReporteDispositivo(Dispositivo $dispositivo)
    {
        $dispositivo->load(['interfaces.ultimaTelemetria', 'telemetriaChasis', 'metricas' => function ($q) {
            $q->latest('fecha_registro')->take(6);
        }]);
        $chasis = $dispositivo->telemetriaChasis;
        [$estadoTexto, $estadoColor] = $this->estadoEquipo($dispositivo->estado);

        $pdf = $this->nuevoPdf('P', 'Ficha técnica del equipo', $dispositivo->nombre . ' · ' . $dispositivo->ip);

        $pdf->seccion('Identificación');
        $pdf->fichaDatos([
            ['Equipo', $dispositivo->nombre, null],
            ['Estado actual', $estadoTexto, $estadoColor],
            ['Dirección IP', $dispositivo->ip, null],
            ['Tipo de equipo', $dispositivo->tipo_label, null],
            ['Fabricante', $dispositivo->fabricante ?: 'Sin dato', null],
            ['Modelo', $chasis?->model_name ?: ($dispositivo->modelo_detectado ?: 'Sin dato'), null],
            ['Número de serie', $chasis?->serial_number ?: 'Sin dato', null],
            ['Sistema operativo', $chasis?->os_version ?: 'Sin dato', null],
            ['Ubicación', $dispositivo->ubicacion ?: 'No especificada', null],
            ['Última respuesta', $dispositivo->ultima_vez_visto ? Carbon::parse($dispositivo->ultima_vez_visto)->format('d/m/Y H:i') : 'Nunca', null],
        ]);

        $pdf->seccion('Mediciones recientes', 'Últimas muestras registradas por el sondeo (latencia y pérdida por ICMP; CPU, memoria y tráfico por SNMP).');
        $metricas = $dispositivo->metricas;
        if ($metricas->isEmpty()) {
            $pdf->aviso('Todavía no hay mediciones registradas para este equipo.');
        } else {
            $w = $pdf->ancho();
            $cols = [['Fecha y hora', $w * 0.22, 'L'], ['Latencia', $w * 0.13, 'R'], ['Pérdida', $w * 0.13, 'R'],
                ['CPU', $w * 0.11, 'R'], ['Memoria', $w * 0.12, 'R'], ['Tráfico entrada', $w * 0.145, 'R'], ['Tráfico salida', $w * 0.145, 'R']];
            $pdf->encabezadoTabla($cols);
            $num = fn ($v, $suf, $dec = 1) => $v === null ? '—' : number_format((float) $v, $dec) . $suf;
            foreach ($metricas as $i => $m) {
                $perdida = $m->packet_loss;
                $pdf->filaTabla($cols, [
                    Carbon::parse($m->fecha_registro)->format('d/m/Y H:i'),
                    $num($m->ping_ms, ' ms'),
                    $perdida !== null && $perdida > 0 ? [$num($perdida, ' %'), $perdida >= 50 ? InstitutionalPdf::ROJO : InstitutionalPdf::AMBAR] : $num($perdida, ' %'),
                    $num($m->cpu_usage, ' %', 0),
                    $num($m->memory_usage, ' %', 0),
                    $num($m->traffic_in_mbps, ' Mbps', 2),
                    $num($m->traffic_out_mbps, ' Mbps', 2),
                ], $i % 2 === 1);
            }
            $pdf->Ln(3);
        }

        $interfaces = $dispositivo->interfaces->sortBy('nombre', SORT_NATURAL)->values();
        $pdf->seccion('Interfaces físicas', $interfaces->isEmpty() ? null : $interfaces->count() . ' interfaces en inventario.');
        if ($interfaces->isEmpty()) {
            $pdf->aviso('El equipo no ha reportado sus interfaces (sin respuesta SNMP o todavía no sondeado).');
        } else {
            $w = $pdf->ancho();
            $cols = [['Interfaz', $w * 0.26, 'L'], ['Estado', $w * 0.18, 'L'], ['Velocidad', $w * 0.14, 'R'],
                ['Dúplex', $w * 0.12, 'C'], ['Consumo PoE', $w * 0.14, 'R'], ['Errores', $w * 0.16, 'R']];
            $pdf->encabezadoTabla($cols);
            foreach ($interfaces as $i => $intf) {
                $t = $intf->ultimaTelemetria;
                $estado = match (true) {
                    (bool) $t?->is_errdisabled => ['Deshabilitada por error', InstitutionalPdf::ROJO],
                    $t?->oper_status === 'up' => ['Activa', InstitutionalPdf::VERDE],
                    $t?->oper_status === 'down' => ['Inactiva', InstitutionalPdf::TENUE],
                    default => ['Sin dato', InstitutionalPdf::TENUE],
                };
                $errores = $t && $t->in_errors !== null ? (int) $t->in_errors + (int) ($t->out_errors ?? 0) : null;
                $pdf->filaTabla($cols, [
                    $intf->nombre,
                    $estado,
                    $intf->velocidad_mbps ? number_format($intf->velocidad_mbps) . ' Mbps' : '—',
                    match (strtolower((string) $intf->duplex)) { 'full' => 'Completo', 'half' => 'Medio', default => '—' },
                    $t && $t->poe_watts_consumo > 0 ? number_format($t->poe_watts_consumo, 1) . ' W' : '—',
                    $errores === null ? '—' : ($errores > 0 ? [number_format($errores), InstitutionalPdf::AMBAR] : '0'),
                ], $i % 2 === 1);
            }
        }

        return $this->responder($pdf, 'Ficha_' . $this->nombreSeguro($dispositivo->nombre) . '_' . date('Ymd_His') . '.pdf');
    }

    /**
     * Inventario completo de equipos de red
     */
    public function descargarReporteInventario()
    {
        $prioridad = ['offline' => 0, 'warning' => 1, 'online' => 2];
        $dispositivos = Dispositivo::all()
            ->sortBy([fn ($a, $b) => ($prioridad[$a->estado] ?? 3) <=> ($prioridad[$b->estado] ?? 3), fn ($a, $b) => strnatcasecmp($a->nombre, $b->nombre)])
            ->values();

        $total = $dispositivos->count();
        $enLinea = $dispositivos->where('estado', 'online')->count();
        $advertencia = $dispositivos->where('estado', 'warning')->count();
        $sinRespuesta = $dispositivos->where('estado', 'offline')->count();
        $disponibilidad = $total ? round($enLinea / $total * 100, 1) : 0;

        $pdf = $this->nuevoPdf('L', 'Inventario de equipos de red',
            "Corte al " . now()->format('d/m/Y H:i') . " · {$total} equipos · primero los que requieren atención");

        $pdf->tarjetasKpi([
            ['Equipos registrados', $total, InstitutionalPdf::AZUL_OSCURO],
            ['En línea', $enLinea, InstitutionalPdf::VERDE],
            ['Con advertencia', $advertencia, InstitutionalPdf::AMBAR],
            ['Sin respuesta', $sinRespuesta, InstitutionalPdf::ROJO],
            ['Disponibilidad', number_format($disponibilidad, 1) . ' %', $disponibilidad >= 95 ? InstitutionalPdf::VERDE : ($disponibilidad >= 80 ? InstitutionalPdf::AMBAR : InstitutionalPdf::ROJO)],
        ]);

        $w = $pdf->ancho();
        $cols = [['Equipo', $w * 0.25, 'L'], ['Dirección IP', $w * 0.11, 'L'], ['Tipo', $w * 0.12, 'L'],
            ['Fabricante', $w * 0.11, 'L'], ['Ubicación', $w * 0.2, 'L'], ['Última respuesta', $w * 0.11, 'C'], ['Estado', $w * 0.1, 'L']];
        $pdf->seccion('Detalle por equipo');
        $pdf->encabezadoTabla($cols);
        foreach ($dispositivos as $i => $d) {
            $pdf->filaTabla($cols, [
                [$d->nombre, InstitutionalPdf::TEXTO, true],
                $d->ip,
                $d->tipo_label,
                $d->fabricante ?: '—',
                $d->ubicacion ?: 'No especificada',
                $d->ultima_vez_visto ? Carbon::parse($d->ultima_vez_visto)->format('d/m/Y H:i') : 'Nunca',
                $this->estadoEquipo($d->estado),
            ], $i % 2 === 1);
        }

        return $this->responder($pdf, 'Inventario_NOC_' . date('Ymd_His') . '.pdf');
    }
}
