<?php
/**
 * Exportador de reportes — PDF, XLSX, Word (.doc), CSV.
 *
 * Uso:  Exportar::generar($titulo, $cabeceras, $filas, $nombreBase, $subtotal);
 *
 * $cabeceras = ['ID','Nombre','Monto']          (array de strings)
 * $filas     = [[1,'A',100],[2,'B',200]]        (array de arrays)
 * $subtotal  = [['TOTAL','','300']]              (array de arrays con filas extra al final)
 */
class Exportar
{
    public static function generar(
        string $titulo,
        array $cabeceras,
        array $filas,
        string $nombreBase = 'reporte',
        array $subtotal = [],
        array $opciones = []
    ): void {
        $fmt = strtolower($_GET['formato'] ?? $_POST['formato'] ?? 'xlsx');

        switch ($fmt) {
            case 'xlsx': self::xlsx($titulo, $cabeceras, $filas, $nombreBase, $subtotal); break;
            case 'pdf':  self::pdf($titulo, $cabeceras, $filas, $nombreBase, $subtotal, $opciones); break;
            case 'doc':  self::doc($titulo, $cabeceras, $filas, $nombreBase, $subtotal); break;
            case 'csv':  self::csv($titulo, $cabeceras, $filas, $nombreBase, $subtotal); break;
            default:
                http_response_code(400);
                echo 'Formato no válido.';
                exit;
        }
    }

    /* ================================================================= */
    /*  EXCEL XLSX  (generación real con ZipArchive)                      */
    /* ================================================================= */
    private static function xlsx(string $titulo, array $cab, array $filas, string $name, array $sub): void
    {
        $zip = new ZipArchive();
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // --- [Content_Types].xml ---
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
</Types>');

        // --- _rels/.rels ---
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>');

        // --- docProps/core.xml ---
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>' . self::escXml($titulo) . '</dc:title>
  <dc:subject>Aura Automation &amp; Analytics - Sistema de Facturación</dc:subject>
</cp:coreProperties>');

        // --- docProps/app.xml ---
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
  <Application>Aura Automation Analytics</Application>
</Properties>');

        // --- xl/_rels/workbook.xml.rels ---
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

        // --- xl/workbook.xml ---
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>
</workbook>');

        // --- xl/styles.xml ---
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <fonts count="2">
    <font><sz val="11"/><name val="Calibri"/></font>
    <font><b/><sz val="11"/><name val="Calibri"/><color rgb="FFFFFFFF"/></font>
  </fonts>
  <fills count="3">
    <fill><patternFill patternType="none"/></fill>
    <fill><patternFill patternType="gray125"/></fill>
    <fill><patternFill patternType="solid"><fgColor rgb="FF0D9488"/><bgColor indexed="64"/></patternFill></fill>
  </fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="2">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
  </cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>');

        // --- Hoja de cálculo ---
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>';

        // Fila 1: título (opcional, fusionar concepto)
        $sheet .= '<row r="1"><c r="A1" t="inlineStr"><is><t>' . self::escXml($titulo) . '</t></is></c></row>';

        // Fila 2: encabezados (estilo 1 = bold+teal)
        $numCols = count($cab);
        $sheet .= '<row r="2" ht="20">';
        for ($c = 0; $c < $numCols; $c++) {
            $ref = self::colLetter($c + 1) . '2';
            $sheet .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . self::escXml($cab[$c]) . '</t></is></c>';
        }
        $sheet .= '</row>';

        // Filas de datos
        $rowNum = 3;
        foreach ($filas as $fila) {
            $sheet .= '<row r="' . $rowNum . '">';
            for ($c = 0; $c < $numCols; $c++) {
                $ref = self::colLetter($c + 1) . $rowNum;
                $val = (string) ($fila[$c] ?? '');
                $sheet .= '<c r="' . $ref . '" t="inlineStr"><is><t>' . self::escXml($val) . '</t></is></c>';
            }
            $sheet .= '</row>';
            $rowNum++;
        }

        // Subtotales
        foreach ($sub as $filaSub) {
            $sheet .= '<row r="' . $rowNum . '">';
            for ($c = 0; $c < $numCols; $c++) {
                $ref = self::colLetter($c + 1) . $rowNum;
                $val = (string) ($filaSub[$c] ?? '');
                $sheet .= '<c r="' . $ref . '" t="inlineStr" s="1"><is><t>' . self::escXml($val) . '</t></is></c>';
            }
            $sheet .= '</row>';
            $rowNum++;
        }

        $sheet .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();

        // Enviar como descarga
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $name . '.xlsx"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /* ================================================================= */
    /*  PDF (FPDF)                                                        */
    /* ================================================================= */
    private static function pdf(string $titulo, array $cab, array $filas, string $name, array $sub, array $opts = []): void
    {
        require_once __DIR__ . '/../lib/fpdf.php';

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();
        $pdf->SetTitle($titulo);
        $pdf->SetAuthor('Aura Automation & Analytics - Sistema de Facturación');

        // Encabezado del documento
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(13, 148, 136);  // teal
        $pdf->Cell(0, 10, self::lat($titulo), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(100, 116, 139); // muted
        $pdf->Cell(0, 5, 'Fecha: ' . date('d/m/Y H:i') . '  |  Aura Automation & Analytics', 0, 1, 'C');
        $pdf->Ln(4);

        $numCols = count($cab);
        if ($numCols === 0) return;

        // Ancho disponible (A4 210mm menos márgenes 10+10)
        $wPdf = $pdf->GetPageWidth() - 20;
        $wPdf = (int) $wPdf;
        $anchoCol = floor($wPdf / $numCols);
        $alturaFila = 7;

        // Encabezados de columna
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(13, 148, 136);
        $pdf->SetTextColor(255, 255, 255);
        foreach ($cab as $c) {
            $pdf->Cell($anchoCol, $alturaFila, self::lat($c), 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Filas
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(19, 78, 74);  // ink
        $fill = false;
        foreach ($filas as $fila) {
            // Salto de página si es necesario
            if ($pdf->GetY() + $alturaFila > $pdf->GetPageHeight() - 15) {
                $pdf->AddPage();
                // Re-dibujar encabezados
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetFillColor(13, 148, 136);
                $pdf->SetTextColor(255, 255, 255);
                foreach ($cab as $c) {
                    $pdf->Cell($anchoCol, $alturaFila, self::lat($c), 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 9);
                $pdf->SetTextColor(19, 78, 74);
            }
            $pdf->SetFillColor($fill ? 240 : 255, $fill ? 253 : 255, $fill ? 250 : 255);
            foreach ($fila as $c) {
                $pdf->Cell($anchoCol, $alturaFila, self::lat((string) $c), 1, 0, 'L', $fill);
            }
            $pdf->Ln();
            $fill = !$fill;
        }

        // Subtotales
        if (!empty($sub)) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(240, 253, 250);
            $pdf->SetTextColor(13, 148, 136);
            foreach ($sub as $filaSub) {
                foreach ($filaSub as $c) {
                    $pdf->Cell($anchoCol, $alturaFila, self::lat((string) $c), 1, 0, 'C', true);
                }
                $pdf->Ln();
            }
        }

        // Pie de página
        $pdf->Ln(6);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(0, 5, 'Aura Automation & Analytics — Sistema de Facturación  |  Página ' . $pdf->PageNo(), 0, 1, 'C');

        $pdf->Output('D', $name . '.pdf');
        exit;
    }

    /* ================================================================= */
    /*  WORD (.doc) — HTML compatible con Microsoft Word                   */
    /* ================================================================= */
    private static function doc(string $titulo, array $cab, array $filas, string $name, array $sub): void
    {
        $numCols = count($cab);
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:w="urn:schemas-microsoft-com:office:word"
 xmlns="http://www.w3.org/TR/REC-html40">
<head><meta charset="UTF-8">
<style>
body { font-family: Calibri, sans-serif; color: #134e4a; }
h1 { color: #0d9488; font-size: 18pt; }
table { border-collapse: collapse; width: 100%; margin-top: 12px; }
th { background: #0d9488; color: #fff; padding: 6px 8px; border: 1px solid #0f766e; font-size: 10pt; }
td { padding: 5px 8px; border: 1px solid #ccc; font-size: 10pt; }
tr:nth-child(even) td { background: #f0fdfa; }
.subtotal td { background: #e0f2fe; font-weight: bold; }
.footer { margin-top: 20px; font-size: 9pt; color: #64748b; text-align: center; }
</style></head><body>
<h1>' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '</h1>
<p style="color:#64748b;font-size:10pt">Fecha: ' . date('d/m/Y H:i') . ' &mdash; Aura Automation & Analytics</p>
<table>';

        $html .= '<tr>';
        foreach ($cab as $c) {
            $html .= '<th>' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        $html .= '</tr>';

        foreach ($filas as $fila) {
            $html .= '<tr>';
            for ($c = 0; $c < $numCols; $c++) {
                $html .= '<td>' . htmlspecialchars((string) ($fila[$c] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }

        foreach ($sub as $filaSub) {
            $html .= '<tr class="subtotal">';
            for ($c = 0; $c < $numCols; $c++) {
                $html .= '<td>' . htmlspecialchars((string) ($filaSub[$c] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</table>
<div class="footer">Aura Automation & Analytics &mdash; Sistema de Facturación</div>
</body></html>';

        header('Content-Type: application/msword');
        header('Content-Disposition: attachment; filename="' . $name . '.doc"');
        echo $html;
        exit;
    }

    /* ================================================================= */
    /*  CSV                                                                */
    /* ================================================================= */
    private static function csv(string $titulo, array $cab, array $filas, string $name, array $sub): void
    {
        $output = fopen('php://temp', 'r+');

        // BOM UTF-8 para que Excel detecte la codificación
        fwrite($output, "\xEF\xBB\xBF");

        // Comentario inicial
        fputcsv($output, [$titulo], ';');
        fputcsv($output, ['Generado: ' . date('d/m/Y H:i') . ' — Aura Automation & Analytics'], ';');
        fputcsv($output, [], ';');

        // Cabeceras
        fputcsv($output, $cab, ';');

        // Datos
        foreach ($filas as $fila) {
            fputcsv($output, $fila, ';');
        }

        // Subtotales
        if (!empty($sub)) {
            fputcsv($output, [], ';');
            foreach ($sub as $filaSub) {
                fputcsv($output, $filaSub, ';');
            }
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '.csv"');
        echo $csv;
        exit;
    }

    /* ================================================================= */
    /*  Helpers                                                            */
    /* ================================================================= */
    private static function colLetter(int $col): string
    {
        $s = '';
        while ($col > 0) {
            $col--;
            $s = chr(65 + ($col % 26)) . $s;
            $col = intdiv($col, 26);
        }
        return $s;
    }

    private static function escXml(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Convierte UTF-8 a ISO-8859-1 para FPDF (evita caracteres rotos). */
    private static function lat(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?? $s;
    }
}