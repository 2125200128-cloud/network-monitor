<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

/**
 * Lee las hojas de un .xlsx sin dependencias (un .xlsx es un ZIP con XML). Devuelve solo los valores, como texto:
 * ['Hoja' => [[celda, celda, …], …]], sin filas vacías. Las fórmulas llegan con su último valor calculado.
 */
class LectorXlsx
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_PAQ = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** @return array<string, array<int, array<int, string>>> */
    public static function hojas(string $ruta): array
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('No es un archivo .xlsx válido (guárdalo como «Libro de Excel (.xlsx)»).');
        }
        try {
            $compartidas = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach (self::dom($xml)->getElementsByTagNameNS(self::NS, 'si') as $si) {
                    $compartidas[] = self::texto($si);
                }
            }

            $rels = [];
            foreach (self::dom(self::leer($zip, 'xl/_rels/workbook.xml.rels'))->getElementsByTagNameNS(self::NS_PAQ, 'Relationship') as $rel) {
                $rels[$rel->getAttribute('Id')] = ltrim($rel->getAttribute('Target'), '/');
            }

            $hojas = [];
            foreach (self::dom(self::leer($zip, 'xl/workbook.xml'))->getElementsByTagNameNS(self::NS, 'sheet') as $hoja) {
                $destino = $rels[$hoja->getAttributeNS(self::NS_REL, 'id')] ?? null;
                if (!$destino) {
                    continue;
                }
                $destino = str_starts_with($destino, 'xl/') ? $destino : 'xl/' . $destino;
                $hojas[$hoja->getAttribute('name')] = self::filas(self::dom(self::leer($zip, $destino)), $compartidas);
            }
            return $hojas;
        } finally {
            $zip->close();
        }
    }

    private static function filas(DOMDocument $hoja, array $compartidas): array
    {
        $filas = [];
        foreach ($hoja->getElementsByTagNameNS(self::NS, 'row') as $fila) {
            $celdas = [];
            foreach ($fila->childNodes as $c) {
                if (!$c instanceof DOMElement || $c->localName !== 'c') {
                    continue;
                }
                $v = '';
                foreach ($c->childNodes as $hijo) {
                    if ($hijo instanceof DOMElement && $hijo->localName === 'v') {
                        $v = $hijo->textContent;
                    }
                }
                $valor = match ($c->getAttribute('t')) {
                    's' => $compartidas[(int) $v] ?? '',
                    'inlineStr' => self::texto($c),
                    default => $v,
                };
                $celdas[self::columna($c->getAttribute('r'))] = trim($valor);
            }
            if (array_filter($celdas, fn ($v) => $v !== '')) {
                $ancho = max(array_keys($celdas)) + 1;
                $filas[] = array_map(fn ($i) => $celdas[$i] ?? '', range(0, $ancho - 1));
            }
        }
        return $filas;
    }

    /** Texto de un <si>/<is>: simple (<t>) o con formato (varios <r><t>); sin la guía fonética (<rPh>). */
    private static function texto(DOMElement $nodo): string
    {
        $partes = [];
        foreach ($nodo->getElementsByTagNameNS(self::NS, 't') as $t) {
            if ($t->parentNode?->localName !== 'rPh') {
                $partes[] = $t->textContent;
            }
        }
        return implode('', $partes);
    }

    /** "AB12" → índice de columna base 0 (AB = 27). */
    private static function columna(string $ref): int
    {
        preg_match('/^[A-Z]+/', $ref, $m);
        $n = 0;
        foreach (str_split($m[0] ?? 'A') as $letra) {
            $n = $n * 26 + ord($letra) - 64;
        }
        return $n - 1;
    }

    private static function leer(ZipArchive $zip, string $nombre): string
    {
        $contenido = $zip->getFromName($nombre);
        if ($contenido === false) {
            throw new RuntimeException("El .xlsx no trae {$nombre}.");
        }
        return $contenido;
    }

    private static function dom(string $contenido): DOMDocument
    {
        $dom = new DOMDocument();
        // Sin red ni entidades externas: el archivo lo sube un usuario
        if (!$dom->loadXML($contenido, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException('El .xlsx tiene un XML dañado.');
        }
        return $dom;
    }
}
