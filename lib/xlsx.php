<?php
/**
 * Minimaler Excel Generator (.xlsx) ohne externe Bibliotheken.
 *
 * Eine .xlsx Datei ist ein ZIP Archiv mit XML Dateien. Das ZIP wird hier selbst
 * erzeugt (nur zlib nötig, bei jedem PHP vorhanden), damit der Export auch ohne
 * die PHP Erweiterung "zip" funktioniert.
 *
 * Aufbau einer Tabelle:
 *   [
 *     'name'    => 'Personen',
 *     'widths'  => [12, 20, ...],             // Spaltenbreiten (Zeichen)
 *     'header'  => ['Vorname', 'Nachname'],   // fett, fixiert, mit Filter
 *     'rows'    => [ [ 'Anna', 'Muster' ], ... ],
 *     'styles'  => [ 'rolle1', null, ... ],   // optional: Zeilenfarbe pro Zeile
 *   ]
 * Zellwerte: string = Text, int/float = Zahl, ['money' => 12.5] = Betrag mit 2 Stellen
 */
declare(strict_types=1);

final class XlsxWriter
{
    /** @var array<string,string> Zeilenfarbe => RGB */
    private array $fills;

    /** @param array<string,string> $fills Name => Hex Farbe (#rrggbb) für farbige Zeilen */
    public function __construct(array $fills = [])
    {
        $this->fills = [];
        foreach ($fills as $name => $hex) {
            $this->fills[$name] = strtoupper(ltrim((string) $hex, '#'));
        }
    }

    public function build(array $sheets): string
    {
        $files = [
            '[Content_Types].xml'        => $this->contentTypes(count($sheets)),
            '_rels/.rels'                => $this->rootRels(),
            'xl/workbook.xml'            => $this->workbook($sheets),
            'xl/_rels/workbook.xml.rels' => $this->workbookRels(count($sheets)),
            'xl/styles.xml'              => $this->styles(),
        ];
        foreach (array_values($sheets) as $i => $sheet) {
            $files['xl/worksheets/sheet' . ($i + 1) . '.xml'] = $this->sheet($sheet);
        }
        return self::zip($files);
    }

    // ------------------------------------------------------------------
    // XML Teile
    // ------------------------------------------------------------------

    private function contentTypes(int $n): string
    {
        $sheets = '';
        for ($i = 1; $i <= $n; $i++) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $sheets . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private function workbook(array $sheets): string
    {
        $xml = '';
        foreach (array_values($sheets) as $i => $s) {
            $name = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', '', (string) $s['name']) ?? 'Tabelle', 0, 31);
            $xml .= '<sheet name="' . self::esc($name) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $xml . '</sheets></workbook>';
    }

    private function workbookRels(int $n): string
    {
        $xml = '';
        for ($i = 1; $i <= $n; $i++) {
            $xml .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $xml .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $xml . '</Relationships>';
    }

    /**
     * Stile: 0 Standard, 1 Kopfzeile, 2 Betrag, 3 Titel (fett, gross),
     * danach pro Farbe je ein Stil für Text und Betrag.
     */
    private function styles(): string
    {
        $fills = '<fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFCC0000"/><bgColor indexed="64"/></patternFill></fill>';
        $xfs = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>';
        $fillId = 3;
        foreach ($this->fills as $rgb) {
            $fills .= '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $rgb . '"/><bgColor indexed="64"/></patternFill></fill>';
            $xfs   .= '<xf numFmtId="0" fontId="0" fillId="' . $fillId . '" borderId="0" xfId="0" applyFill="1"/>';
            $xfs   .= '<xf numFmtId="4" fontId="0" fillId="' . $fillId . '" borderId="0" xfId="0" applyFill="1" applyNumberFormat="1"/>';
            $fillId++;
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="' . $fillId . '">' . $fills . '</fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="' . (4 + 2 * count($this->fills)) . '">' . $xfs . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private function sheet(array $s): string
    {
        $header = $s['header'] ?? [];
        $rows   = $s['rows'] ?? [];
        $styles = $s['styles'] ?? [];
        $colCount = max(count($header), ...array_map('count', $rows ?: [[]]));

        $cols = '';
        foreach ($s['widths'] ?? [] as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float) $w . '" customWidth="1"/>';
        }

        $data = '';
        $r = 1;
        if ($header) {
            $data .= $this->row($r++, $header, 1);
        }
        foreach ($rows as $i => $row) {
            $fillName = $styles[$i] ?? null;
            $data .= $this->row($r++, $row, null, $fillName);
        }

        $views = $header
            ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            : '<sheetViews><sheetView workbookViewId="0"/></sheetViews>';
        $filter = ($header && $rows) ? '<autoFilter ref="A1:' . self::col($colCount) . ($r - 1) . '"/>' : '';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $views
            . ($cols ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $data . '</sheetData>'
            . $filter
            . '</worksheet>';
    }

    private function row(int $r, array $cells, ?int $forceStyle = null, ?string $fillName = null): string
    {
        $fillIdx = $fillName !== null ? array_search($fillName, array_keys($this->fills), true) : false;
        $xml = '<row r="' . $r . '">';
        foreach (array_values($cells) as $c => $v) {
            $ref = self::col($c + 1) . $r;
            $money = is_array($v) && array_key_exists('money', $v);
            $title = is_array($v) && array_key_exists('title', $v);
            if ($forceStyle !== null) {
                $style = $forceStyle;
            } elseif ($title) {
                $style = 3;
            } elseif ($fillIdx !== false) {
                $style = 4 + 2 * (int) $fillIdx + ($money ? 1 : 0);
            } else {
                $style = $money ? 2 : 0;
            }
            $s = $style ? ' s="' . $style . '"' : '';

            if ($money) {
                $xml .= '<c r="' . $ref . '"' . $s . '><v>' . round((float) $v['money'], 2) . '</v></c>';
            } elseif (is_int($v) || is_float($v)) {
                $xml .= '<c r="' . $ref . '"' . $s . '><v>' . $v . '</v></c>';
            } else {
                $text = $title ? (string) $v['title'] : (string) $v;
                if ($text === '' && !$s) {
                    continue;
                }
                // Inline Text wird von Excel nie als Formel ausgewertet
                $xml .= '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t xml:space="preserve">' . self::esc($text) . '</t></is></c>';
            }
        }
        return $xml . '</row>';
    }

    // ------------------------------------------------------------------
    // Hilfsfunktionen
    // ------------------------------------------------------------------

    private static function esc(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function col(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = intdiv($n - 1, 26);
        }
        return $s;
    }

    /** Einfacher ZIP Writer (Deflate), benötigt nur zlib. */
    private static function zip(array $files): string
    {
        $data = '';
        $dir  = '';
        $time = (int) (((date('Y') - 1980) << 25) | (date('n') << 21) | (date('j') << 16) | (date('G') << 11) | ((int) date('i') << 5) | ((int) date('s') >> 1));
        $dosTime = $time & 0xFFFF;
        $dosDate = ($time >> 16) & 0xFFFF;

        foreach ($files as $name => $content) {
            $crc  = crc32($content);
            $comp = gzdeflate($content, 6);
            $offset = strlen($data);
            $head = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen($comp), strlen($content), strlen($name), 0);
            $data .= $head . $name . $comp;
            $dir  .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 8, $dosTime, $dosDate, $crc, strlen($comp), strlen($content), strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        }
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($dir), strlen($data), 0);
        return $data . $dir . $end;
    }
}
