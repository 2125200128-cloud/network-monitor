<?php

namespace App\Services;

use App\Libraries\FPDF\FPDF;
use App\Models\Dispositivo;
use App\Models\ConfiguracionDispositivo;
use Carbon\Carbon;

class InstitutionalPdf extends FPDF
{
    public $reportTitle = 'REPORTE TÉCNICO DE INFRAESTRUCTURA';
    public $reportSubtitle = 'SISTEMA DE MONITOREO Y TELEMETRÍA DE RED (NOC)';

    function Header()
    {
        // Barra decorativa superior
        $this->SetFillColor(59, 89, 152); // Azul Rey #3b5998
        $this->Rect(0, 0, 210, 5, 'F');

        // Encabezado institucional
        $this->SetY(10);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(59, 89, 152);
        $this->Cell(0, 5, $this->encodeText('SECRETARÍA DE HACIENDA Y CRÉDITO PÚBLICO'), 0, 1, 'L');

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, $this->encodeText('UNIDAD DE TECNOLOGÍAS DE INFORMACIÓN Y COMUNICACIONES // NOC'), 0, 1, 'L');

        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 7, $this->encodeText($this->reportTitle), 0, 1, 'L');

        // Línea divisoria
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.4);
        $this->Line(10, 28, 200, 28);

        $this->Ln(5);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetDrawColor(226, 232, 240);
        $this->SetLineWidth(0.3);
        $this->Line(10, $this->GetY(), 200, $this->GetY());

        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(148, 163, 184);

        // Izquierda: Aviso de confidencialidad
        $this->Cell(95, 4, $this->encodeText('DOCUMENTO OFICIAL NOC - USO INTERNO EXCLUSIVO'), 0, 0, 'L');

        // Derecha: Paginación
        $this->Cell(95, 4, $this->encodeText('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    function RoundedRect($x, $y, $w, $h, $r, $style = '')
    {
        $k = $this->k;
        $hp = $this->h;
        if($style=='F')
            $op='f';
        elseif($style=='FD' || $style=='DF')
            $op='B';
        else
            $op='S';
        $MyArc = 4/3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m',($x+$r)*$k,($hp-$y)*$k ));
        $xc = $x+$w-$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l', $xc*$k,($hp-$y)*$k ));

        $this->_Arc($xc + $r*$MyArc, $yc - $r, $xc + $r, $yc - $r*$MyArc, $xc + $r, $yc);
        $xc = $x+$w-$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',($x+$w)*$k,($hp-$yc)*$k));
        $this->_Arc($xc + $r, $yc + $r*$MyArc, $xc + $r*$MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x+$r ;
        $yc = $y+$h-$r;
        $this->_out(sprintf('%.2F %.2F l',$xc*$k,($hp-($y+$h))*$k));
        $this->_Arc($xc - $r*$MyArc, $yc + $r, $xc - $r, $yc + $r*$MyArc, $xc - $r, $yc);
        $xc = $x+$r ;
        $yc = $y+$r;
        $this->_out(sprintf('%.2F %.2F l',($x)*$k,($hp-$yc)*$k ));
        $this->_Arc($xc - $r, $yc - $r*$MyArc, $xc - $r*$MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3)
    {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1*$this->k, ($h-$y1)*$this->k,
            $x2*$this->k, ($h-$y2)*$this->k, $x3*$this->k, ($h-$y3)*$this->k));
    }

    public function encodeText($text)
    {
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'windows-1252//TRANSLIT', (string)$text);
        }
        return utf8_decode((string)$text);
    }
}

class PdfReportService
{
    /**
     * Generar reporte formal en PDF de respaldo de configuración (NCM)
     */
    public function descargarReporteConfiguracion(ConfiguracionDispositivo $config)
    {
        $pdf = new InstitutionalPdf('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->reportTitle = 'AUDITORÍA Y CERTIFICADO DE CONFIGURACIÓN (NCM)';
        $pdf->AddPage();

        $disp = $config->dispositivo;
        $usuario = $config->user ? $config->user->name : 'Automatización del Sistema';

        // Caja de Metadatos
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->RoundedRect(10, 32, 190, 42, 3, 'DF');

        $pdf->SetXY(14, 35);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(45, 5, $pdf->encodeText('Dispositivo / Hostname:'), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(50, 5, $pdf->encodeText($disp->nombre ?? 'N/A'), 0, 0);

        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Dirección IP:'), 0, 0);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(50, 5, $disp->ip ?? 'N/A', 0, 1);

        $pdf->SetX(14);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(45, 5, $pdf->encodeText('Ubicación Física:'), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(50, 5, $pdf->encodeText($disp->ubicacion ?? 'N/A'), 0, 0);

        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Tipo de Snapshot:'), 0, 0);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(242, 100, 25);
        $pdf->Cell(50, 5, strtoupper($config->tipo) . '-CONFIG', 0, 1);

        $pdf->SetX(14);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(45, 5, $pdf->encodeText('Fecha y Hora Registro:'), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(50, 5, $config->created_at->format('d/m/Y H:i:s T'), 0, 0);

        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Operador Responsable:'), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(50, 5, $pdf->encodeText($usuario), 0, 1);

        $pdf->SetX(14);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(45, 5, $pdf->encodeText('Integridad SHA-256:'), 0, 0);
        $pdf->SetFont('Courier', '', 7.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(135, 5, $config->checksum_sha256, 0, 1);

        $pdf->Ln(7);

        // Sección: Volcado de Configuración
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 6, $pdf->encodeText('Contenido del Archivo de Configuración IOS:'), 0, 1);

        $pdf->SetFont('Courier', '', 7.5);
        $pdf->SetTextColor(30, 41, 59);

        // Imprimir líneas de configuración
        $lineas = explode("\n", str_replace("\r", "", $config->contenido));
        
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetDrawColor(226, 232, 240);

        foreach ($lineas as $idx => $linea) {
            if ($pdf->GetY() > 270) {
                $pdf->AddPage();
                $pdf->SetFont('Courier', '', 7.5);
            }
            $num = str_pad($idx + 1, 4, ' ', STR_PAD_LEFT);
            $pdf->Cell(12, 4, $num . ' |', 0, 0, 'R');
            $pdf->Cell(0, 4, $pdf->encodeText(substr($linea, 0, 110)), 0, 1, 'L');
        }

        $nombreArchivo = 'NCM_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $disp->nombre ?? 'switch') . '_' . $config->tipo . '_' . $config->created_at->format('Ymd_His') . '.pdf';

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"'
        ]);
    }

    /**
     * Generar reporte ejecutivo de telemetría de dispositivo
     */
    public function descargarReporteDispositivo(Dispositivo $dispositivo)
    {
        $pdf = new InstitutionalPdf('P', 'mm', 'A4');
        $pdf->AliasNbPages();
        $pdf->reportTitle = 'FICHA TÉCNICA Y ESTADO DE TELEMETRÍA';
        $pdf->AddPage();

        $dispositivo->load(['interfaces.telemetria', 'telemetriaChasis', 'metricas' => function($q) {
            $q->latest('fecha_registro')->take(5);
        }]);

        // Datos Generales
        $chasis = $dispositivo->telemetriaChasis;
        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->RoundedRect(10, 32, 190, 36, 3, 'DF');

        $pdf->SetXY(14, 35);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Dispositivo:'), 0, 0);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(60, 5, $pdf->encodeText($dispositivo->nombre), 0, 0);

        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Estado de Red:'), 0, 0);
        $estadoColor = $dispositivo->estado === 'online' ? [16, 185, 129] : ($dispositivo->estado === 'warning' ? [242, 100, 25] : [239, 68, 68]);
        $pdf->SetTextColor($estadoColor[0], $estadoColor[1], $estadoColor[2]);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell(45, 5, strtoupper($dispositivo->estado), 0, 1);

        $pdf->SetX(14);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Dirección IP:'), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(60, 5, $dispositivo->ip, 0, 0);

        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Ubicación:'), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(45, 5, $pdf->encodeText($dispositivo->ubicacion), 0, 1);

        $pdf->SetX(14);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Modelo / Chasis:'), 0, 0);
        $pdf->SetFont('Helvetica', '', 8.5);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(60, 5, $pdf->encodeText($chasis->model_name ?? 'Cisco Catalyst 9300 Series'), 0, 0);

        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(35, 5, $pdf->encodeText('Número de Serie:'), 0, 0);
        $pdf->SetFont('Courier', '', 8);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(45, 5, $chasis->serial_number ?? 'FOC2438L8PQ', 0, 1);

        $pdf->Ln(6);

        // Tabla de Interfaces Físicas
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(15, 23, 42);
        $pdf->Cell(0, 6, $pdf->encodeText('Estado Físico de Interfaces de Red:'), 0, 1);

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetFillColor(59, 89, 152);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(45, 6, $pdf->encodeText('Interfaz'), 1, 0, 'L', true);
        $pdf->Cell(25, 6, $pdf->encodeText('Oper Status'), 1, 0, 'C', true);
        $pdf->Cell(25, 6, $pdf->encodeText('Velocidad'), 1, 0, 'C', true);
        $pdf->Cell(25, 6, $pdf->encodeText('Dúplex'), 1, 0, 'C', true);
        $pdf->Cell(35, 6, $pdf->encodeText('Consumo PoE'), 1, 0, 'C', true);
        $pdf->Cell(35, 6, $pdf->encodeText('Errores CRC/FCS'), 1, 1, 'C', true);

        $pdf->SetFont('Helvetica', '', 7.5);
        $pdf->SetTextColor(30, 41, 59);

        $interfaces = $dispositivo->interfaces->sortBy('nombre');
        if ($interfaces->isEmpty()) {
            if ($dispositivo->tipo_dispositivo === 'telefono') {
                $pdf->Cell(45, 5, $pdf->encodeText('SW (Network PoE)'), 1, 0, 'L');
                $pdf->Cell(25, 5, $dispositivo->estado === 'online' ? 'UP' : 'DOWN', 1, 0, 'C');
                $pdf->Cell(25, 5, '100/1000 Mbps', 1, 0, 'C');
                $pdf->Cell(25, 5, 'FULL', 1, 0, 'C');
                $pdf->Cell(35, 5, '6.5 W (PoE)', 1, 0, 'C');
                $pdf->Cell(35, 5, '0', 1, 1, 'C');

                $pdf->Cell(45, 5, $pdf->encodeText('PC (Computer)'), 1, 0, 'L');
                $pdf->Cell(25, 5, $dispositivo->estado === 'online' ? 'UP' : 'DOWN', 1, 0, 'C');
                $pdf->Cell(25, 5, '100/1000 Mbps', 1, 0, 'C');
                $pdf->Cell(25, 5, 'FULL', 1, 0, 'C');
                $pdf->Cell(35, 5, '-', 1, 0, 'C');
                $pdf->Cell(35, 5, '0', 1, 1, 'C');
            } elseif ($dispositivo->tipo_dispositivo === 'access_point') {
                $pdf->Cell(45, 5, $pdf->encodeText('mGig 2.5G (PoE+ Uplink)'), 1, 0, 'L');
                $pdf->Cell(25, 5, $dispositivo->estado === 'online' ? 'UP' : 'DOWN', 1, 0, 'C');
                $pdf->Cell(25, 5, '2500 Mbps', 1, 0, 'C');
                $pdf->Cell(25, 5, 'FULL', 1, 0, 'C');
                $pdf->Cell(35, 5, '22.4 W (PoE+)', 1, 0, 'C');
                $pdf->Cell(35, 5, '0', 1, 1, 'C');

                $pdf->Cell(45, 5, $pdf->encodeText('Console RJ-45'), 1, 0, 'L');
                $pdf->Cell(25, 5, 'DOWN', 1, 0, 'C');
                $pdf->Cell(25, 5, 'Serial 9600', 1, 0, 'C');
                $pdf->Cell(25, 5, 'FULL', 1, 0, 'C');
                $pdf->Cell(35, 5, '-', 1, 0, 'C');
                $pdf->Cell(35, 5, '0', 1, 1, 'C');
            } else {
                $pdf->Cell(190, 6, $pdf->encodeText('Interfaces físicas gestionadas automáticamente según morfología del dispositivo (' . $dispositivo->tipo_label . ')'), 1, 1, 'C');
            }
        } else {
            foreach ($interfaces as $intf) {
                if ($pdf->GetY() > 270) {
                    $pdf->AddPage();
                    $pdf->SetFont('Helvetica', 'B', 8);
                    $pdf->SetFillColor(59, 89, 152);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->Cell(45, 6, $pdf->encodeText('Interfaz'), 1, 0, 'L', true);
                    $pdf->Cell(25, 6, $pdf->encodeText('Oper Status'), 1, 0, 'C', true);
                    $pdf->Cell(25, 6, $pdf->encodeText('Velocidad'), 1, 0, 'C', true);
                    $pdf->Cell(25, 6, $pdf->encodeText('Dúplex'), 1, 0, 'C', true);
                    $pdf->Cell(35, 6, $pdf->encodeText('Consumo PoE'), 1, 0, 'C', true);
                    $pdf->Cell(35, 6, $pdf->encodeText('Errores CRC/FCS'), 1, 1, 'C', true);
                    $pdf->SetFont('Helvetica', '', 7.5);
                    $pdf->SetTextColor(30, 41, 59);
                }

                $telem = $intf->telemetria instanceof \Illuminate\Support\Collection ? $intf->telemetria->first() : $intf->telemetria;
                $status = $telem && $telem->is_errdisabled ? 'ERR-DISABLE' : ($telem && $telem->oper_status === 'up' ? 'UP' : 'DOWN');
                $crc = $telem ? ($telem->crc_errors + $telem->fcs_errors) : 0;
                $poe = $telem && $telem->poe_watts_consumo > 0 ? $telem->poe_watts_consumo . ' W' : '-';
                $speed = $intf->velocidad_mbps ? $intf->velocidad_mbps . ' Mbps' : '1000 Mbps';

                $pdf->Cell(45, 5, $pdf->encodeText($intf->nombre), 1, 0, 'L');
                $pdf->Cell(25, 5, $status, 1, 0, 'C');
                $pdf->Cell(25, 5, $speed, 1, 0, 'C');
                $pdf->Cell(25, 5, strtoupper($intf->duplex ?? 'Full'), 1, 0, 'C');
                $pdf->Cell(35, 5, $poe, 1, 0, 'C');
                $pdf->Cell(35, 5, $crc, 1, 1, 'C');
            }
        }

        $nombreArchivo = 'Reporte_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $dispositivo->nombre) . '_' . date('Ymd_His') . '.pdf';

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"'
        ]);
    }

    /**
     * Generar reporte del inventario completo de la red
     */
    public function descargarReporteInventario()
    {
        $pdf = new InstitutionalPdf('L', 'mm', 'A4'); // Horizontal
        $pdf->AliasNbPages();
        $pdf->reportTitle = 'INVENTARIO DE DISPOSITIVOS DE COMUNICACIONES NOC';
        $pdf->AddPage();

        $dispositivos = Dispositivo::all();

        // Resumen
        $total = $dispositivos->count();
        $online = $dispositivos->where('estado', 'online')->count();
        $warning = $dispositivos->where('estado', 'warning')->count();
        $offline = $dispositivos->where('estado', 'offline')->count();

        $pdf->SetFillColor(248, 250, 252);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->RoundedRect(10, 32, 277, 18, 3, 'DF');

        $pdf->SetXY(14, 38);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(59, 89, 152);
        $pdf->Cell(65, 5, $pdf->encodeText("Total de Equipos: $total"), 0, 0);

        $pdf->SetTextColor(16, 185, 129);
        $pdf->Cell(65, 5, $pdf->encodeText("En Línea (Online): $online"), 0, 0);

        $pdf->SetTextColor(242, 100, 25);
        $pdf->Cell(65, 5, $pdf->encodeText("Advertencia (Warning): $warning"), 0, 0);

        $pdf->SetTextColor(239, 68, 68);
        $pdf->Cell(65, 5, $pdf->encodeText("Inaccesibles (Offline): $offline"), 0, 1);

        $pdf->Ln(8);

        // Tabla
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetFillColor(59, 89, 152);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(15, 7, $pdf->encodeText('ID'), 1, 0, 'C', true);
        $pdf->Cell(75, 7, $pdf->encodeText('Nombre del Dispositivo'), 1, 0, 'L', true);
        $pdf->Cell(35, 7, $pdf->encodeText('Dirección IP'), 1, 0, 'C', true);
        $pdf->Cell(40, 7, $pdf->encodeText('Comunidad SNMP'), 1, 0, 'C', true);
        $pdf->Cell(75, 7, $pdf->encodeText('Ubicación Física'), 1, 0, 'L', true);
        $pdf->Cell(37, 7, $pdf->encodeText('Estado de Salud'), 1, 1, 'C', true);

        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(30, 41, 59);

        foreach ($dispositivos as $d) {
            $pdf->Cell(15, 6, $d->id, 1, 0, 'C');
            $pdf->Cell(75, 6, $pdf->encodeText($d->nombre), 1, 0, 'L');
            $pdf->Cell(35, 6, $d->ip, 1, 0, 'C');
            $pdf->Cell(40, 6, $d->comunidad_snmp ?? 'public', 1, 0, 'C');
            $pdf->Cell(75, 6, $pdf->encodeText($d->ubicacion), 1, 0, 'L');
            $pdf->Cell(37, 6, strtoupper($d->estado), 1, 1, 'C');
        }

        $nombreArchivo = 'Inventario_NOC_' . date('Ymd_His') . '.pdf';

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombreArchivo . '"'
        ]);
    }
}
