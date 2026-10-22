<?php
require __DIR__ . '/_auth.php';
require_once __DIR__ . '/../../private/reports.php';

$year = valid_year($_GET['year'] ?? null, 2000, 2100);
if (!$year) { http_response_code(400); exit('Please provide a valid year.'); }
$yr = one('SELECT * FROM account_years WHERE year = ?', [$year]) ?? ['year' => $year, 'opening_balance' => 0, 'financial_secretary' => '', 'report_note' => ''];
$entries = all('SELECT * FROM account_entries WHERE year = ? ORDER BY entry_date ASC, id ASC', [$year]);
$format = $_GET['format'] ?? 'pdf';
$file = 'sba12-financial-report-' . $year;

if ($format === 'xlsx') {
    $bin = account_xlsx($yr, $entries);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $file . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    echo $bin;
    exit;
}
$bin = account_pdf($yr, $entries, __DIR__ . '/../../private/letterhead.png');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $file . '.pdf"');
header('Content-Length: ' . strlen($bin));
echo $bin;
