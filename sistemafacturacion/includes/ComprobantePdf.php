<?php
/**
 * Genera el PDF de un comprobante (factura / boleta / nota).
 * Independiente del ERP. Usa la librería FPDF de lib/fpdf.php.
 */

require_once __DIR__ . '/../lib/fpdf.php';

class ComprobantePdf
{
    /** @var FPDF */
    private $pdf;
    private $igvPorc;

    public function __construct(float $igvPorc)
    {
        $this->pdf = new FPDF('P', 'mm', 'A4');
        $this->pdf->SetAutoPageBreak(true, 20);
        $this->igvPorc = $igvPorc;
    }

    public static function generar(PDO $db, array $c, array $detalle, array $empresa): void
    {
        $gen = new self(porcentajeIgv($db));
        $gen->construir($c, $detalle, $empresa);
        $gen->pdf->Output('D', 'comprobante_' . numeroComprobante($c['serie'], (int) $c['numero']) . '.pdf');
        exit;
    }

    private function construir(array $c, array $detalle, array $empresa): void
    {
        $p  = $this->pdf;
        $p->SetTitle('Comprobante ' . numeroComprobante($c['serie'], (int) $c['numero']));
        $p->SetAuthor('Aura Automation & Analytics — Sistema de Facturación');
        $p->AddPage();

        $anulado = ($c['estado'] ?? '') === 'anulado';
        $tipo    = (string) $c['tipo'];

        /* -------------------- Encabezado -------------------- */
        $p->SetFont('Arial', 'B', 13);
        $p->SetTextColor(13, 148, 136);
        $p->Cell(0, 7, self::lat($empresa['razon_social']), 0, 1, 'L');
        $p->SetFont('Arial', '', 9);
        $p->SetTextColor(70, 70, 70);
        $p->Cell(0, 4.5, 'RUC: ' . self::lat($empresa['ruc']), 0, 1, 'L');
        if (!empty($empresa['direccion'])) $p->Cell(0, 4.5, self::lat($empresa['direccion']), 0, 1, 'L');
        if (!empty($empresa['telefono']) || !empty($empresa['correo'])) {
            $p->Cell(0, 4.5, self::lat('Tel: ' . $empresa['telefono'] . '   Correo: ' . $empresa['correo']), 0, 1, 'L');
        }
        $p->Ln(2);

        /* Bloque derecho: tipo de comprobante + número */
        $p->SetXY(125, 10);
        $anchoDer = $p->GetPageWidth() - 125 - 12;
        $p->SetFillColor(13, 148, 136);
        $p->SetTextColor(255, 255, 255);
        $p->SetFont('Arial', 'B', 13);
        $p->Cell($anchoDer, 10, self::lat($tipo), 1, 1, 'C', true);
        $p->SetX(125);
        $p->SetFont('Arial', 'B', 12);
        $p->SetTextColor(19, 78, 74);
        $p->Cell($anchoDer, 8, numeroComprobante($c['serie'], (int) $c['numero']), 1, 1, 'C');
        $p->SetX(125);
        $p->SetFont('Arial', '', 9);
        $p->SetTextColor(70, 70, 70);
        $p->SetFillColor(240, 253, 250);
        $p->Cell($anchoDer, 6, 'Fecha emisión: ' . formatoFecha($c['fecha_emision']), 1, 1, 'L', true);
        $p->SetX(125);
        $p->Cell($anchoDer, 6, 'Fecha vencimiento: ' . ($c['fecha_vencimiento'] ? formatoFecha($c['fecha_vencimiento']) : '—'), 1, 1, 'L', true);
        $p->SetX(125);
        $p->Cell($anchoDer, 6, 'Condición de pago: ' . self::lat($c['condicion_pago']), 1, 1, 'L', true);

        if ($anulado) {
            $p->SetFont('Arial', 'B', 16);
            $p->SetTextColor(220, 38, 38);
            $p->SetY(58);
            $p->Cell(0, 8, 'ANULADO', 0, 1, 'C');
            $p->SetTextColor(70, 70, 70);
        }

        /* -------------------- Cliente -------------------- */
        $p->Ln(2);
        $p->SetFont('Arial', 'B', 9);
        $p->SetTextColor(13, 148, 136);
        $p->Cell(0, 5, 'DATOS DEL CLIENTE', 0, 1, 'L');
        $p->SetDrawColor(13, 148, 136);
        $p->Line(12, $p->GetY(), $p->GetPageWidth() - 12, $p->GetY());
        $p->Ln(2);

        $p->SetFont('Arial', '', 9);
        $p->SetTextColor(35, 35, 35);
        $p->Cell(0, 5, 'Señor(es): ' . self::lat($c['razon_social']), 0, 1, 'L');
        $p->Cell(0, 5, $c['tipo_doc'] . ': ' . self::lat($c['ruc_dni']), 0, 1, 'L');
        if (!empty($c['direccion'])) $p->Cell(0, 5, 'Dirección: ' . self::lat($c['direccion']), 0, 1, 'L');
        if (!empty($c['telefono']))  $p->Cell(0, 5, 'Teléfono: ' . self::lat($c['telefono']), 0, 1, 'L');
        $p->Ln(3);

        /* -------------------- Tabla de ítems -------------------- */
        $col = [8, 70, 16, 24, 24, 20, 24];  // total 186mm
        $headers = ['#', 'Descripción', 'Cant.', 'P. Unitario', 'Importe', 'IGV', 'Total'];

        $p->SetFont('Arial', 'B', 8);
        $p->SetFillColor(13, 148, 136);
        $p->SetTextColor(255, 255, 255);
        foreach ($col as $i => $w) {
            $p->Cell($w, 7, $headers[$i], 1, 0, 'C', true);
        }
        $p->Ln();

        $p->SetFont('Arial', '', 8);
        $p->SetTextColor(35, 35, 35);
        $fil = true;
        $n = 0;
        foreach ($detalle as $d) {
            $n++;
            if ($p->GetY() > $p->GetPageHeight() - 45) {
                $p->AddPage();
                $p->SetFont('Arial', 'B', 8);
                $p->SetFillColor(13, 148, 136);
                $p->SetTextColor(255, 255, 255);
                foreach ($col as $i => $w) {
                    $p->Cell($w, 7, $headers[$i], 1, 0, 'C', true);
                }
                $p->Ln();
                $p->SetFont('Arial', '', 8);
                $p->SetTextColor(35, 35, 35);
            }
            $p->SetFillColor($fil ? 240 : 255, 253, 250);
            $p->Cell($col[0], 6, (string) $n, 1, 0, 'C', $fil);
            $p->Cell($col[1], 6, ' ' . self::lat(trim((string) $d['descripcion'])), 1, 0, 'L', $fil);
            $p->Cell($col[2], 6, rtrim(rtrim(number_format((float) $d['cantidad'], 3, '.', ''), '0'), '.'), 1, 0, 'C', $fil);
            $p->Cell($col[3], 6, number_format((float) $d['precio_unitario'], 2), 1, 0, 'R', $fil);
            $p->Cell($col[4], 6, number_format((float) $d['importe'], 2), 1, 0, 'R', $fil);
            $p->Cell($col[5], 6, number_format((float) $d['igv'], 2), 1, 0, 'R', $fil);
            $p->Cell($col[6], 6, number_format((float) $d['total'], 2), 1, 0, 'R', $fil);
            $p->Ln();
            $fil = !$fil;
        }

        /* -------------------- Totales -------------------- */
        $p->Ln(1);
        $x = $p->GetPageWidth() - 12 - 80;
        $p->SetX($x);
        $p->SetFont('Arial', '', 8);
        $p->SetTextColor(70, 70, 70);
        $p->Cell(50, 6, 'SUBTOTAL', 1, 0, 'R');
        $p->Cell(30, 6, number_format((float) $c['subtotal'], 2), 1, 1, 'R');
        $p->SetX($x);
        $p->Cell(50, 6, 'IGV (' . rtrim(rtrim(number_format($this->igvPorc, 2, '.', ''), '0'), '.') . '%)', 1, 0, 'R');
        $p->Cell(30, 6, number_format((float) $c['igv'], 2), 1, 1, 'R');
        $p->SetX($x);
        $p->SetFont('Arial', 'B', 10);
        $p->SetFillColor(13, 148, 136);
        $p->SetTextColor(255, 255, 255);
        $p->Cell(50, 8, 'TOTAL', 1, 0, 'R', true);
        $p->Cell(30, 8, number_format((float) $c['total'], 2), 1, 1, 'R', true);

        $p->SetTextColor(19, 78, 74);
        $p->SetFont('Arial', 'B', 8);
        $p->SetX(12);
        $p->Cell(0, 6, self::lat($c['letras']), 0, 1, 'L');
        $p->SetFont('Arial', '', 8);
        $p->SetTextColor(70, 70, 70);
        if (!empty($c['observaciones'])) {
            $p->SetX(12);
            $p->MultiCell(0, 4.5, 'Observaciones: ' . self::lat($c['observaciones']), 0, 'L');
        }

        /* -------------------- Pie -------------------- */
        $p->Ln(8);
        $p->SetY(-18);
        $p->SetFont('Arial', 'I', 7);
        $p->SetTextColor(130, 140, 150);
        $p->Cell(0, 5, self::lat('Aura Automation & Analytics — Sistema de Facturación  |  Documento generado: ' . date('d/m/Y H:i') . '  |  Página ' . $p->PageNo() . ' de {nb}'), 0, 1, 'C');
        $p->AliasNbPages();
    }

    /** Convierte UTF-8 a ISO-8859-1 para FPDF. */
    private static function lat(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?? $s;
    }
}