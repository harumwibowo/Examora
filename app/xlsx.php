<?php
declare(strict_types=1);

/**
 * Penulis XLSX minimal tanpa ekstensi zip.
 * Menghasilkan file .xlsx valid (metode store, tanpa kompresi).
 */
final class ZipWriter
{
    private string $data = '';
    /** @var list<string> */
    private array $central = [];

    private static function dosDateTime(): array
    {
        $t = getdate();
        $time = (($t['hours'] & 0x1F) << 11) | (($t['minutes'] & 0x3F) << 5) | ((int) ($t['seconds'] / 2) & 0x1F);
        $date = ((($t['year'] - 1980) & 0x7F) << 9) | (($t['mon'] & 0x0F) << 5) | ($t['mday'] & 0x1F);
        return [$time, $date];
    }

    public function add(string $name, string $content): void
    {
        $crc = crc32($content);
        $size = strlen($content);
        $offset = strlen($this->data);
        [$time, $date] = self::dosDateTime();
        $nameLen = strlen($name);

        $this->data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $size, $size, $nameLen, 0)
            . $name . $content;

        $this->central[] = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0x0800,
            0,
            $time,
            $date,
            $crc,
            $size,
            $size,
            $nameLen,
            0,
            0,
            0,
            0,
            0,
            $offset
        ) . $name;
    }

    public function build(): string
    {
        $central = implode('', $this->central);
        $count = count($this->central);
        $offset = strlen($this->data);
        return $this->data . $central
            . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), $offset, 0);
    }
}

final class Xlsx
{
    /** @var list<array{0:string,1:array}> */
    private array $sheets;
    private string $title;

    public function __construct(string $title = 'Sheet1')
    {
        $this->title = $title;
        $this->sheets = [];
    }

    public function addSheet(string $name, array $rows): self
    {
        $this->sheets[] = [$name, $rows];
        return $this;
    }

    private static function col(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = (int) (($n - $m - 1) / 26);
        }
        return $s;
    }

    private static function xml(string $s): string
    {
        $s = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function sheetXml(array $rows): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="15"/>';

        $maxCols = 0;
        foreach ($rows as $row) {
            $maxCols = max($maxCols, count($row));
        }
        if ($maxCols > 0) {
            $out .= '<cols>';
            for ($i = 1; $i <= $maxCols; $i++) {
                $w = $i === 1 ? 28 : 16;
                $out .= '<col min="' . $i . '" max="' . $i . '" width="' . $w . '" customWidth="1"/>';
            }
            $out .= '</cols>';
        }

        $out .= '<sheetData>';
        foreach ($rows as $r => $row) {
            $rn = $r + 1;
            $out .= '<row r="' . $rn . '">';
            $c = 0;
            foreach ($row as $value) {
                $c++;
                $ref = self::col($c) . $rn;
                $style = $rn === 1 ? '1' : '0';
                if (is_int($value) || is_float($value)) {
                    $out .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>';
                } else {
                    $text = (string) $value;
                    if (preg_match('/^-?\d+(\.\d+)?$/', $text) && strlen($text) < 15 && !str_starts_with($text, '0')) {
                        $out .= '<c r="' . $ref . '" s="' . $style . '"><v>' . $text . '</v></c>';
                    } else {
                        $out .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">'
                            . self::xml($text) . '</t></is></c>';
                    }
                }
            }
            $out .= '</row>';
        }
        $out .= '</sheetData></worksheet>';
        return $out;
    }

    public function save(string $filename): void
    {
        if ($this->sheets === []) {
            $this->sheets[] = ['Sheet1', []];
        }

        $zip = new ZipWriter();
        $zip->add('[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . implode('', array_map(
                static fn ($i) => '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>',
                array_keys($this->sheets)
            ))
            . '</Types>');

        $zip->add('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $sheetsXml = '';
        $rels = '';
        foreach ($this->sheets as $i => [$name, $rows]) {
            $n = $i + 1;
            $sheetsXml .= '<sheet name="' . self::xml(mb_substr($name, 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
            $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $zip->add('xl/worksheets/sheet' . $n . '.xml', $this->sheetXml($rows));
        }
        $rels .= '<Relationship Id="rId' . (count($this->sheets) + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        $zip->add('xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetsXml . '</sheets></workbook>');

        $zip->add('xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels . '</Relationships>');

        $zip->add('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>');

        $binary = $zip->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: no-store');
        echo $binary;
        exit;
    }
}
