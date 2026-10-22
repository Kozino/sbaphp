<?php
declare(strict_types=1);

/*
 * Report generators (no Composer, no external services):
 *   xlsx_build()          — minimal Excel (.xlsx) writer using ZipArchive
 *   account_xlsx()        — Financial Report workbook (Summary, Income, Expenditure)
 *   account_pdf()         — Financial Report PDF on the association letterhead
 */

require_once __DIR__ . '/lib/fpdf/fpdf.php';

const NAVY_RGB = [0, 29, 105];
const GOLD_RGB = [253, 191, 46];

/* ------------------------------------------------------------------ */
/* Account helpers                                                     */
/* ------------------------------------------------------------------ */

function account_split(array $entries): array
{
    $sort = static fn(array $a, array $b) => [$a['entry_date'], (int)$a['id']] <=> [$b['entry_date'], (int)$b['id']];
    $income = array_values(array_filter($entries, fn($e) => $e['kind'] === 'income'));
    $expense = array_values(array_filter($entries, fn($e) => $e['kind'] === 'expense'));
    usort($income, $sort);
    usort($expense, $sort);
    $ti = array_sum(array_map(fn($e) => (float)$e['amount'], $income));
    $te = array_sum(array_map(fn($e) => (float)$e['amount'], $expense));
    return ['income' => $income, 'expense' => $expense, 'total_income' => $ti, 'total_expense' => $te];
}

function account_summary(array $yearRow, array $entries): array
{
    $s = account_split($entries);
    $opening = (float)$yearRow['opening_balance'];
    $net = $s['total_income'] - $s['total_expense'];
    return $s + [
        'opening' => $opening,
        'net' => $net,
        'closing' => $opening + $net,
    ];
}

function fmt_short_date(string $iso): string
{
    $t = strtotime($iso);
    return $t ? date('j M Y', $t) : $iso;
}

function fmt_long_date(int $ts): string
{
    return date('j F Y', $ts);
}

/** Classic accounting amount text for PDF: "NGN 1,234.56" / "(NGN 1,234.56)" */
function fmt_ngn(float $n): string
{
    $abs = number_format(abs($n), 2, '.', ',');
    return $n < 0 ? '(NGN ' . $abs . ')' : 'NGN ' . $abs;
}

function xml(string $s): string
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '';
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/* ------------------------------------------------------------------ */
/* Minimal XLSX writer                                                 */
/* ------------------------------------------------------------------ */

/** Style names -> cellXfs index (see xlsx_styles_xml) */
const XS = [
    'default' => 0, 'bold' => 1, 'header' => 2, 'title' => 3, 'subtitle' => 4, 'italic_c' => 5,
    'money' => 6, 'money_b' => 7, 'note' => 8, 'text' => 9, 'bold_box' => 10, 'total_label' => 11,
    'total_money' => 12, 'italic' => 13, 'header_r' => 14, 'bold_italic' => 15,
    'sum_last_label' => 16, 'sum_last_money' => 17, 'bold_text' => 18,
];

function xlsx_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="7">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'                                           // 0
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'                                       // 1 bold
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'                // 2 bold white
        . '<font><b/><sz val="16"/><color rgb="FF001D69"/><name val="Calibri"/></font>'                // 3 title
        . '<font><b/><sz val="13"/><name val="Calibri"/></font>'                                       // 4 subtitle
        . '<font><i/><sz val="11"/><color rgb="FF555555"/><name val="Calibri"/></font>'                // 5 italic grey
        . '<font><b/><i/><sz val="11"/><name val="Calibri"/></font>'                                   // 6 bold italic
        . '</fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF001D69"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="4">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FF999999"/></left><right style="thin"><color rgb="FF999999"/></right><top style="thin"><color rgb="FF999999"/></top><bottom style="thin"><color rgb="FF999999"/></bottom><diagonal/></border>'
        . '<border><left/><right/><top style="thin"><color rgb="FF000000"/></top><bottom style="double"><color rgb="FF000000"/></bottom><diagonal/></border>'
        . '<border><left style="thin"><color rgb="FF999999"/></left><right style="thin"><color rgb="FF999999"/></right><top style="thin"><color rgb="FF999999"/></top><bottom style="double"><color rgb="FF000000"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="19">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                   // 0 default
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                     // 1 bold
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>' // 2 header
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 3 title
        . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 4 subtitle
        . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 5 italic centre
        . '<xf numFmtId="4" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 6 money
        . '<xf numFmtId="4" fontId="1" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 7 money bold
        . '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="top" wrapText="1"/></xf>' // 8 note
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'  // 9 text
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'                    // 10 bold boxed
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="2" xfId="0" applyFont="1" applyBorder="1"/>'                    // 11 total label
        . '<xf numFmtId="4" fontId="1" fillId="0" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 12 total money
        . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                    // 13 italic
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="center"/></xf>' // 14 header right
        . '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                    // 15 bold italic
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="3" xfId="0" applyFont="1" applyBorder="1"/>'                    // 16 summary last label
        . '<xf numFmtId="4" fontId="1" fillId="0" borderId="3" xfId="0" applyNumberFormat="1" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right"/></xf>' // 17 summary last money
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>'                    // 18 bold text boxed
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function xlsx_col(int $i): string
{
    $s = '';
    while ($i > 0) {
        $m = ($i - 1) % 26;
        $s = chr(65 + $m) . $s;
        $i = intdiv($i - 1, 26);
    }
    return $s;
}

/**
 * $sheets: [ ['name'=>, 'cols'=>[width,...], 'rows'=>[[cell,...],...], 'heights'=>[rowNo=>pt], 'merges'=>['A1:B1'], 'freeze'=>bool] ]
 * cell: scalar | null | ['v'=>value, 's'=>styleName]
 */
function xlsx_build(array $sheets): string
{
    $zipPath = tempnam(sys_get_temp_dir(), 'sbaxlsx');
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Could not create the Excel file.');
    }
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wbSheets = '';
    $wbRels = '';
    foreach ($sheets as $i => $sh) {
        $n = $i + 1;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wbSheets .= '<sheet name="' . xml(mb_substr($sh['name'], 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        $wbRels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', xlsx_sheet_xml($sh));
    }
    $ct .= '</Types>';
    $wbRels .= '<Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $zip->addFromString('[Content_Types].xml', $ct);
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');
    $zip->addFromString('xl/styles.xml', xlsx_styles_xml());
    $zip->close();
    $bin = (string)file_get_contents($zipPath);
    @unlink($zipPath);
    return $bin;
}

function xlsx_sheet_xml(array $sh): string
{
    $cols = '';
    foreach (($sh['cols'] ?? []) as $i => $w) {
        $c = $i + 1;
        $cols .= '<col min="' . $c . '" max="' . $c . '" width="' . (float)$w . '" customWidth="1"/>';
    }
    $rows = '';
    foreach ($sh['rows'] as $r => $cells) {
        $rowNo = $r + 1;
        $ht = $sh['heights'][$rowNo] ?? null;
        $attr = $ht ? ' ht="' . (float)$ht . '" customHeight="1"' : '';
        $xml = '';
        foreach ($cells as $c => $cell) {
            $ref = xlsx_col($c + 1) . $rowNo;
            $style = 'default';
            $v = $cell;
            if (is_array($cell)) {
                $style = $cell['s'] ?? 'default';
                $v = $cell['v'] ?? null;
            }
            $s = XS[$style] ?? 0;
            if ($v === null || $v === '') {
                $xml .= '<c r="' . $ref . '" s="' . $s . '"/>';
            } elseif (is_int($v) || is_float($v)) {
                $xml .= '<c r="' . $ref . '" s="' . $s . '"><v>' . rtrim(rtrim(sprintf('%.2F', (float)$v), '0'), '.') . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . xml((string)$v) . '</t></is></c>';
            }
        }
        $rows .= '<row r="' . $rowNo . '"' . $attr . '>' . $xml . '</row>';
    }
    $pane = !empty($sh['freeze'])
        ? '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        : '';
    $merges = '';
    if (!empty($sh['merges'])) {
        $merges = '<mergeCells count="' . count($sh['merges']) . '">';
        foreach ($sh['merges'] as $m) $merges .= '<mergeCell ref="' . $m . '"/>';
        $merges .= '</mergeCells>';
    }
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0">' . $pane . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
        . '<sheetData>' . $rows . '</sheetData>'
        . $merges
        . '<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
        . '</worksheet>';
}

/** Financial Report workbook — same structure as the React export */
function account_xlsx(array $yearRow, array $entries): string
{
    $sum = account_summary($yearRow, $entries);
    $year = (int)$yearRow['year'];
    $S = [];
    $S[] = [
        'name' => 'Summary',
        'cols' => [52, 26],
        'merges' => ['A1:B1', 'A2:B2', 'A3:B3'],
        'heights' => [1 => 24, 4 => 20],
        'rows' => [
            [['v' => 'SAVIOBOSCO ALPHAS 2012', 's' => 'title'], ['v' => '', 's' => 'title']],
            [['v' => 'FINANCIAL REPORT', 's' => 'subtitle'], ['v' => '', 's' => 'subtitle']],
            [['v' => 'Income and Expenditure Statement for the Year ' . $year, 's' => 'italic_c'], ['v' => '', 's' => 'italic_c']],
            [['v' => 'Particulars', 's' => 'header'], ['v' => 'Amount (NGN)', 's' => 'header_r']],
            [['v' => 'Balance Brought Forward', 's' => 'text'], ['v' => $sum['opening'], 's' => 'money']],
            [['v' => 'Total Income', 's' => 'text'], ['v' => $sum['total_income'], 's' => 'money']],
            [['v' => 'Total Expenditure', 's' => 'text'], ['v' => $sum['total_expense'], 's' => 'money']],
            [['v' => 'Net Surplus / (Deficit)', 's' => 'bold_box'], ['v' => $sum['net'], 's' => 'money_b']],
            [['v' => 'Balance Carried Forward', 's' => 'sum_last_label'], ['v' => $sum['closing'], 's' => 'sum_last_money']],
            [],
        ],
    ];
    $rowsRef = &$S[0]['rows'];
    $note = trim((string)($yearRow['report_note'] ?? ''));
    if ($note !== '') {
        $rowNo = count($rowsRef) + 1;
        $S[0]['merges'][] = 'A' . $rowNo . ':B' . $rowNo;
        $S[0]['heights'][$rowNo] = max(20, 16 * (1 + intdiv(mb_strlen($note), 90)));
        $rowsRef[] = [['v' => 'Note: ' . $note, 's' => 'note'], ['v' => '', 's' => 'note']];
        $rowsRef[] = [];
    }
    $rowsRef[] = [['v' => 'Prepared by', 's' => 'bold'], ['v' => $yearRow['financial_secretary'] ?: 'Financial Secretary', 's' => 'italic']];
    $rowsRef[] = [['v' => 'Position', 's' => 'bold'], ['v' => 'Financial Secretary, Savio Bosco Alphas 2012', 's' => 'italic']];
    $rowsRef[] = [['v' => 'Date prepared', 's' => 'bold'], ['v' => fmt_long_date(time()), 's' => 'italic']];
    unset($rowsRef);

    foreach ([['Income', $sum['income'], $sum['total_income']], ['Expenditure', $sum['expense'], $sum['total_expense']]] as [$title, $rows, $total]) {
        $sheetRows = [[
            ['v' => 'Date', 's' => 'header'],
            ['v' => 'Particulars', 's' => 'header'],
            ['v' => 'Amount (NGN)', 's' => 'header_r'],
        ]];
        foreach ($rows as $e) {
            $sheetRows[] = [
                ['v' => fmt_short_date($e['entry_date']), 's' => 'text'],
                ['v' => $e['note'] !== '' ? $e['name'] . ' (' . $e['note'] . ')' : $e['name'], 's' => 'text'],
                ['v' => (float)$e['amount'], 's' => 'money'],
            ];
        }
        $sheetRows[] = [
            ['v' => 'Total ' . $title, 's' => 'total_label'],
            ['v' => '', 's' => 'total_label'],
            ['v' => $total, 's' => 'total_money'],
        ];
        $S[] = ['name' => $title, 'cols' => [18, 60, 22], 'freeze' => true, 'rows' => $sheetRows, 'heights' => [1 => 20]];
    }
    return xlsx_build($S);
}

/* ------------------------------------------------------------------ */
/* PDF (FPDF, letterhead)                                              */
/* ------------------------------------------------------------------ */

class AccountPdf extends FPDF
{
    public const W = 595.28;
    public const H = 841.89;
    public const M = 48;
    public const LETTERHEAD_H = 79.9; // letterhead.png 2550x342 scaled to page width
    public bool $showLetterhead = true;
    public string $letterheadPath = '';

    public function Header(): void
    {
        if ($this->PageNo() === 1 && $this->showLetterhead && is_file($this->letterheadPath)) {
            $this->Image($this->letterheadPath, 0, 0, self::W, self::LETTERHEAD_H);
            $this->SetY(self::LETTERHEAD_H + 4);
            return;
        }
        $this->SetFont('Times', 'B', 9);
        $this->SetTextColor(...NAVY_RGB);
        $this->SetXY(self::M, 26);
        $this->Cell(0, 10, pdf_text('SAVIOBOSCO ALPHAS 2012 — Financial Report'));
        $this->SetDrawColor(...GOLD_RGB);
        $this->SetLineWidth(1.4);
        $this->Line(self::M, 40, self::W - self::M, 40);
        $this->SetY(58);
    }

    public function Footer(): void
    {
        $this->SetFillColor(...GOLD_RGB);
        $this->Rect(0, self::H - 5, self::W, 5, 'F');
        $this->SetFont('Times', '', 8);
        $this->SetTextColor(120, 120, 120);
        $this->SetXY(self::M, self::H - 22);
        $this->Cell(0, 10, $this->PageNo() === 1 ? pdf_text('Savio Bosco Alphas 2012') : '', 0, 0, 'L');
        $this->SetXY(self::M, self::H - 22);
        $this->Cell(self::W - 2 * self::M, 10, pdf_text('Page ' . $this->PageNo() . ' of {nb}'), 0, 0, 'R');
    }
}

/** FPDF's built-in fonts are Windows-1252 — convert UTF-8 safely. */
function pdf_text(string $s): string
{
    $out = @iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $s);
    return $out === false ? $s : $out;
}

/** Splits $text into lines that fit in $width using the current font. */
function pdf_wrap(AccountPdf $pdf, string $text, float $width): array
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = [];
    foreach (explode("\n", $text) as $para) {
        $words = preg_split('/\s+/', trim($para)) ?: [''];
        $line = '';
        foreach ($words as $w) {
            $try = $line === '' ? $w : $line . ' ' . $w;
            if ($pdf->GetStringWidth(pdf_text($try)) <= $width - 8) {
                $line = $try;
            } else {
                if ($line !== '') $lines[] = $line;
                $line = $w;
            }
        }
        $lines[] = $line;
    }
    return $lines ?: [''];
}

function account_pdf(array $yearRow, array $entries, string $letterheadPath): string
{
    $sum = account_summary($yearRow, $entries);
    $year = (int)$yearRow['year'];
    $pdf = new AccountPdf('P', 'pt', 'A4');
    $pdf->letterheadPath = $letterheadPath;
    $pdf->showLetterhead = is_file($letterheadPath);
    $pdf->SetMargins(AccountPdf::M, 0, AccountPdf::M);
    $pdf->SetAutoPageBreak(true, 60);
    $pdf->AliasNbPages('{nb}');
    $pdf->AddPage();
    $M = AccountPdf::M;
    $R = AccountPdf::W - AccountPdf::M;
    $W = $R - $M;
    $bottomLimit = AccountPdf::H - 60;

    if (!$pdf->showLetterhead) {
        $pdf->SetFont('Times', 'B', 16);
        $pdf->SetTextColor(...NAVY_RGB);
        $pdf->SetXY(0, 40);
        $pdf->Cell(AccountPdf::W, 20, 'SAVIOBOSCO ALPHAS 2012', 0, 1, 'C');
        $pdf->SetY(70);
    }
    $y = $pdf->GetY() + 22;
    $pdf->SetXY(0, $y);
    $pdf->SetFont('Times', 'B', 15);
    $pdf->SetTextColor(25, 25, 25);
    $pdf->Cell(AccountPdf::W, 18, 'FINANCIAL REPORT', 0, 1, 'C');
    $pdf->SetFont('Times', '', 11);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->Cell(AccountPdf::W, 16, pdf_text('Income and Expenditure Statement for the Year ' . $year), 0, 1, 'C');
    $pdf->Ln(12);

    // Summary (classic ledger style)
    $rows = [
        ['Balance Brought Forward', fmt_ngn($sum['opening']), false],
        ['Add: Total Income', fmt_ngn($sum['total_income']), false],
        ['Less: Total Expenditure', fmt_ngn(-$sum['total_expense']), false],
        ['Net Surplus / (Deficit)', fmt_ngn($sum['net']), false],
        ['Balance Carried Forward', fmt_ngn($sum['closing']), true],
    ];
    $rowH = 20;
    foreach ($rows as $i => [$label, $amount, $bold]) {
        $pdf->SetFont('Times', $bold ? 'B' : '', 11);
        $pdf->SetTextColor($bold ? 0 : 30, $bold ? 29 : 30, $bold ? 105 : 30);
        $pdf->SetX($M);
        $pdf->Cell($W, $rowH, pdf_text($label), 0, 0, 'L');
        $pdf->SetX($M);
        $pdf->Cell($W, $rowH, pdf_text($amount), 0, 1, 'R');
        if ($i === 2) {
            $pdf->SetDrawColor(60, 60, 60);
            $pdf->SetLineWidth(0.7);
            $pdf->Line($M, $pdf->GetY() - 2, $R, $pdf->GetY() - 2);
        }
    }
    $pdf->SetDrawColor(25, 25, 25);
    $pdf->SetLineWidth(0.9);
    $pdf->Line($M, $pdf->GetY() - 1, $R, $pdf->GetY() - 1);
    $pdf->SetLineWidth(0.5);
    $pdf->Line($M, $pdf->GetY() + 2, $R, $pdf->GetY() + 2);
    $pdf->Ln(10);

    $cols = [80, $W - 80 - 110, 110];
    $tableHead = function () use ($pdf, $cols, $M) {
        $pdf->SetFont('Times', 'B', 10);
        $pdf->SetFillColor(...NAVY_RGB);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetX($M);
        $pdf->Cell($cols[0], 18, 'Date', 1, 0, 'L', true);
        $pdf->Cell($cols[1], 18, 'Particulars', 1, 0, 'L', true);
        $pdf->Cell($cols[2], 18, 'Amount (NGN)', 1, 1, 'R', true);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetFont('Times', '', 10);
    };

    $section = function (string $title, array $list, float $total) use ($pdf, $cols, $M, $bottomLimit, $tableHead, $year) {
        $pdf->Ln(8);
        if ($pdf->GetY() + 120 > $bottomLimit) $pdf->AddPage();
        $pdf->SetFont('Times', 'B', 12);
        $pdf->SetTextColor(...NAVY_RGB);
        $pdf->SetX($M);
        $pdf->Cell(0, 16, pdf_text($title), 0, 1, 'L');
        $pdf->SetDrawColor(...NAVY_RGB);
        $pdf->SetLineWidth(0.8);
        $pdf->Line($M, $pdf->GetY(), AccountPdf::W - $M, $pdf->GetY());
        $pdf->Ln(6);
        if (!$list) {
            $pdf->SetFont('Times', 'I', 10);
            $pdf->SetTextColor(110, 110, 110);
            $pdf->SetX($M);
            $pdf->Cell(0, 14, 'No records for this year.', 0, 1);
            return;
        }
        $tableHead();
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.4);
        foreach ($list as $i => $e) {
            $label = $e['note'] !== '' ? $e['name'] . ' (' . $e['note'] . ')' : $e['name'];
            $lines = pdf_wrap($pdf, $label, $cols[1]);
            $lh = 12;
            $rowH = max(18, count($lines) * $lh + 8);
            if ($pdf->GetY() + $rowH > $bottomLimit) {
                $pdf->AddPage();
                $tableHead();
            }
            $y0 = $pdf->GetY();
            $fill = $i % 2 === 1;
            if ($fill) {
                $pdf->SetFillColor(249, 250, 253);
            }
            $pdf->SetX($M);
            $pdf->SetFont('Times', '', 10);
            $pdf->Cell($cols[0], $rowH, pdf_text(fmt_short_date($e['entry_date'])), 1, 0, 'L', $fill);
            $x = $pdf->GetX();
            $pdf->Cell($cols[1], $rowH, '', 1, 0, 'L', $fill);
            foreach ($lines as $k => $ln) {
                $pdf->SetXY($x + 4, $y0 + 4 + $k * $lh);
                $pdf->Cell($cols[1] - 8, $lh, pdf_text($ln), 0, 0, 'L');
            }
            $pdf->SetXY($x + $cols[1], $y0);
            $pdf->Cell($cols[2], $rowH, pdf_text(fmt_ngn((float)$e['amount'])), 1, 1, 'R', $fill);
        }
        // total row
        $pdf->SetFont('Times', 'B', 10);
        $pdf->SetFillColor(240, 242, 248);
        $pdf->SetDrawColor(0, 29, 105);
        $pdf->SetX($M);
        $pdf->Cell($cols[0] + $cols[1], 18, 'Total', 1, 0, 'L', true);
        $pdf->Cell($cols[2], 18, pdf_text(fmt_ngn($total)), 1, 1, 'R', true);
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetFont('Times', '', 10);
    };

    $section('INCOME FOR THE YEAR ' . $year, $sum['income'], $sum['total_income']);
    $section('EXPENDITURE FOR THE YEAR ' . $year, $sum['expense'], $sum['total_expense']);

    // Note (bold + italic) + signature — kept on the current page whenever they fit.
    $note = trim((string)($yearRow['report_note'] ?? ''));
    $SIG_H = 120;
    $pdf->SetFont('Times', 'BI', 11);
    $noteLines = $note !== '' ? pdf_wrap($pdf, 'Note: ' . $note, $W) : [];
    $noteH = $noteLines ? 22 + count($noteLines) * 15 + 10 : 0;
    if ($pdf->GetY() + $noteH + $SIG_H > $bottomLimit) $pdf->AddPage();
    if ($noteLines) {
        $pdf->Ln(12);
        $pdf->SetTextColor(25, 25, 25);
        foreach ($noteLines as $ln) {
            $pdf->SetX($M);
            $pdf->SetFont('Times', 'BI', 11);
            $pdf->Cell($W, 15, pdf_text($ln), 0, 1, 'L');
        }
        $pdf->Ln(8);
    }
    if ($pdf->GetY() + $SIG_H > $bottomLimit) $pdf->AddPage();
    $pdf->Ln(22);
    $pdf->SetX($M);
    $pdf->SetFont('Times', '', 11);
    $pdf->SetTextColor(30, 30, 30);
    $pdf->Cell($W, 15, 'Prepared and submitted by:', 0, 1, 'L');
    $pdf->Ln(22);
    $pdf->SetDrawColor(30, 30, 30);
    $pdf->SetLineWidth(0.7);
    $pdf->Line($M, $pdf->GetY(), $M + 170, $pdf->GetY());
    $pdf->Ln(4);
    $pdf->SetX($M);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->Cell($W, 15, pdf_text($yearRow['financial_secretary'] ?: '_________________________'), 0, 1, 'L');
    $pdf->SetX($M);
    $pdf->SetFont('Times', '', 11);
    $pdf->Cell($W, 15, 'Financial Secretary, Savio Bosco Alphas 2012', 0, 1, 'L');
    $pdf->SetX($M);
    $pdf->Cell($W, 15, 'Date: ' . fmt_long_date(time()), 0, 1, 'L');

    return $pdf->Output('S');
}
