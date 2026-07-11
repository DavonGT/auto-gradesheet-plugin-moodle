<?php
require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_item.php');
require_once(dirname($CFG->dirroot) . '/vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

require_login();

$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);
require_capability('local/gradesheet:manage', $context);

// Load course and config
$course        = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursename    = format_string($course->fullname);
$config        = $DB->get_record('local_gradesheet_config', ['courseid' => $courseid]);

$semester      = ($config && !empty($config->semester))        ? $config->semester        : 'Second Semester';
$schoolyear    = ($config && !empty($config->schoolyear))      ? $config->schoolyear      : '2025-2026';
$coursenumber  = ($config && !empty($config->coursenumber))    ? $config->coursenumber    : $coursename;
$descriptive   = ($config && !empty($config->descriptive))     ? $config->descriptive     : $coursename;
$courseandyear = ($config && !empty($config->courseandyear))   ? $config->courseandyear   : '';
$schedule      = ($config && !empty($config->schedule))        ? $config->schedule        : '';
$units         = ($config && !empty($config->units))           ? $config->units           : '3';
$instructor    = ($config && !empty($config->instructor))      ? $config->instructor      : '';
$depthead      = ($config && !empty($config->department_head)) ? $config->department_head : '';
$registrar     = ($config && !empty($config->registrar))       ? $config->registrar       : '';
$collegedean   = ($config && !empty($config->college_dean))    ? $config->college_dean    : '';

$midweight = $config ? floatval($config->quizweight) / 100 : 0.50;
$finweight = $config ? floatval($config->examweight) / 100 : 0.50;

// Load categories
$categories    = $DB->get_records('local_gradesheet_categories', ['courseid' => $courseid], 'sortorder ASC');
$hascategories = !empty($categories);
$catlist       = array_values($categories);

// Get students and grade items
$students = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname ASC, u.firstname ASC');
$gitems   = $DB->get_records_select(
    'grade_items',
    'courseid = ? AND itemtype != ? AND itemname IS NOT NULL',
    [$courseid, 'course']
);

// Build rows
$rows      = [];
$passcount = 0;
$failcount = 0;

foreach ($students as $student) {
    if (is_siteadmin($student->id)) continue;
    $roles     = get_user_roles($context, $student->id);
    $isteacher = false;
    foreach ($roles as $role) {
        if ($role->shortname === 'teacher' || $role->shortname === 'editingteacher') {
            $isteacher = true; break;
        }
    }
    if ($isteacher) continue;

    $cattotals = [];
    foreach ($categories as $cat) {
        $cattotals[$cat->id] = ['total' => 0, 'count' => 0, 'weight' => $cat->weight, 'name' => $cat->name];
    }

    $midTotal = 0; $midCount = 0;
    $finTotal = 0; $finCount = 0;

    foreach ($gitems as $gitem) {
        $ggrade = $DB->get_record('grade_grades', ['itemid' => $gitem->id, 'userid' => $student->id]);
        $val    = ($ggrade && $ggrade->finalgrade !== null) ? floatval($ggrade->finalgrade) : 0;
        $max    = floatval($gitem->grademax);
        if ($max > 0 && $max != 100) $val = ($val / $max) * 100;

        $map    = $DB->get_record('local_gradesheet_itemmap', ['courseid' => $courseid, 'gradeitemid' => $gitem->id]);
        $period = $map ? $map->period     : 'finals';
        $catid  = $map ? $map->categoryid : 0;

        if ($catid && isset($cattotals[$catid])) {
            $cattotals[$catid]['total'] += $val;
            $cattotals[$catid]['count']++;
        }

        if ($period === 'midterm') { $midTotal += $val; $midCount++; }
        else                       { $finTotal += $val; $finCount++; }
    }

    $weightedFinal = 0;
    $totalWeight   = 0;
    foreach ($cattotals as $data) {
        if ($data['count'] > 0) {
            $weightedFinal += ($data['total'] / $data['count']) * ($data['weight'] / 100);
            $totalWeight   += $data['weight'];
        }
    }

    $midAvg = $midCount > 0 ? $midTotal / $midCount : 0;
    $finAvg = $finCount > 0 ? $finTotal / $finCount : 0;
    if ($totalWeight == 0) {
        $weightedFinal = ($midAvg * $midweight) + ($finAvg * $finweight);
    }

    $remarks = $weightedFinal >= 75 ? 'Passed' : 'Failed';
    if ($remarks === 'Passed') $passcount++; else $failcount++;

    $rows[] = [
        'idnumber'  => $student->idnumber,
        'name'      => $student->lastname . ', ' . $student->firstname,
        'midterm'   => round($midAvg, 2),
        'finals'    => round($finAvg, 2),
        'average'   => round($weightedFinal, 2),
        'remarks'   => $remarks,
        'cattotals' => $cattotals,
    ];
}

$total    = $passcount + $failcount;
$passrate = $total > 0 ? round(($passcount / $total) * 100, 1) : 0;

// ── DETERMINE COLUMN LAYOUT ───────────────────────────────────────────────────
// Columns: A=NO, B=NAME, C=STUDENT NO, then dynamic, then AVERAGE, REMARKS
// We'll use letters dynamically
$colNO      = 'A';
$colNAME    = 'B';
$colSTUDENT = 'C';

$dynamicCols = []; // e.g. ['D', 'E'] for categories or ['D', 'E'] for midterm/finals
$colIdx      = 4;  // D=4, E=5, F=6...

$alphabet = range('A', 'Z');

if ($hascategories) {
    foreach ($catlist as $cat) {
        $dynamicCols[$cat->id] = $alphabet[$colIdx - 1];
        $colIdx++;
    }
} else {
    $dynamicCols['midterm'] = $alphabet[$colIdx - 1]; $colIdx++;
    $dynamicCols['finals']  = $alphabet[$colIdx - 1]; $colIdx++;
}

$colAVERAGE = $alphabet[$colIdx - 1]; $colIdx++;
$colREMARKS = $alphabet[$colIdx - 1];
$lastCol    = $colREMARKS;

// ── BUILD SPREADSHEET ─────────────────────────────────────────────────────────
$spreadsheet = new Spreadsheet();
$sheet       = $spreadsheet->getActiveSheet();
$sheet->setTitle('Report of Grades');

$allBorderThin = [
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
];

// ── HEADER ────────────────────────────────────────────────────────────────────
$sheet->mergeCells("A1:{$lastCol}1");
$sheet->setCellValue('A1', 'EASTERN SAMAR STATE UNIVERSITY');
$sheet->getStyle('A1')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 14],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$sheet->mergeCells("A2:{$lastCol}2");
$sheet->setCellValue('A2', 'Excellence  •  Accountability  •  Service');
$sheet->getStyle('A2')->applyFromArray([
    'font'      => ['italic' => true, 'size' => 9],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$sheet->mergeCells("A3:{$lastCol}3");
$sheet->setCellValue('A3', 'REPORT OF GRADES');
$sheet->getStyle('A3')->applyFromArray([
    'font'      => ['bold' => true, 'size' => 13],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$sheet->mergeCells("A4:{$lastCol}4");
$sheet->setCellValue('A4', $semester . '  SY ' . $schoolyear);
$sheet->getStyle('A4')->applyFromArray([
    'font'      => ['size' => 10],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// ── COURSE INFO (rows 6-10) ───────────────────────────────────────────────────
$infoData = [
    6  => ['Subject and Course No. :', $coursenumber],
    7  => ['Descriptive Title :',      $descriptive],
    8  => ['Course and Year :',        $courseandyear],
    9  => ['Schedule of Classes :',    $schedule],
    10 => ['Number of Units :',        $units],
];
foreach ($infoData as $r => [$label, $val]) {
    $sheet->setCellValue('A' . $r, $label);
    $sheet->mergeCells('B' . $r . ':D' . $r);
    $sheet->setCellValue('B' . $r, $val);
    $sheet->getStyle('A' . $r)->getFont()->setItalic(true);
    $sheet->getStyle('B' . $r)->getFont()->setBold(true);
}

// ── RATING LEGEND (rows 6-17, columns E-G) ───────────────────────────────────
$legend = [
    ['Actual Rating', 'Equivalent Rating', 'Adjectival Rating'],
    ['100',   '1.0',     'Outstanding'],
    ['94-90', '1.1-1.5', 'Excellent'],
    ['89-85', '1.6-2.0', 'Very Good'],
    ['84-80', '2.1-2.5', 'Good'],
    ['79-75', '2.6-3.0', 'Fair'],
    ['74-70', '3.1-3.5', 'Conditional'],
    ['69-55', '3.6-5.0', 'Failed'],
    ['INC',   'INC',     'Incomplete'],
    ['Dr',    'Dr',      'Dropped'],
    ['WP',    'WP',      'Withdrawn w/ permission'],
    ['IP',    'IP',      'In Progress'],
];

$legendStartRow = 6;
foreach ($legend as $li => $lrow) {
    $r        = $legendStartRow + $li;
    $isHeader = ($li === 0);
    $sheet->setCellValue('E' . $r, $lrow[0]);
    $sheet->setCellValue('F' . $r, $lrow[1]);
    $sheet->setCellValue('G' . $r, $lrow[2]);
    $sheet->getStyle("E{$r}:G{$r}")->applyFromArray([
        'font'      => ['bold' => $isHeader, 'size' => 8],
        'fill'      => $isHeader
            ? ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDDDDD']]
            : ['fillType' => Fill::FILL_NONE],
        'borders'   => $allBorderThin['borders'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    ]);
    $sheet->getStyle('G' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
}

// ── TABLE HEADER (row 19) ─────────────────────────────────────────────────────
$tableHeaderRow = 19;

$sheet->setCellValue('A' . $tableHeaderRow, 'NO.');
$sheet->setCellValue('B' . $tableHeaderRow, 'NAME OF STUDENTS');
$sheet->setCellValue('C' . $tableHeaderRow, 'STUDENT NO.');

if ($hascategories) {
    foreach ($catlist as $cat) {
        $col = $dynamicCols[$cat->id];
        $sheet->setCellValue($col . $tableHeaderRow, strtoupper($cat->name) . ' (' . $cat->weight . '%)');
    }
} else {
    $sheet->setCellValue($dynamicCols['midterm'] . $tableHeaderRow, 'MIDTERM');
    $sheet->setCellValue($dynamicCols['finals']  . $tableHeaderRow, 'FINALS');
}

$sheet->setCellValue($colAVERAGE . $tableHeaderRow, 'AVERAGE');
$sheet->setCellValue($colREMARKS . $tableHeaderRow, 'REMARKS');

$sheet->getStyle("A{$tableHeaderRow}:{$lastCol}{$tableHeaderRow}")->applyFromArray([
    'font'      => ['bold' => true, 'size' => 10],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
        'wrapText'   => true,
    ],
    'borders'   => $allBorderThin['borders'],
]);
$sheet->getRowDimension($tableHeaderRow)->setRowHeight(28);

// ── DATA ROWS ─────────────────────────────────────────────────────────────────
$dataRow = $tableHeaderRow + 1;

foreach ($rows as $i => $row) {
    $evenFill  = ($i % 2 === 0) ? 'F5F5F5' : 'FFFFFF';
    $isFailed  = ($row['remarks'] === 'Failed');
    $textColor = $isFailed ? 'CC0000' : '000000';

    $sheet->setCellValue('A' . $dataRow, $i + 1);
    $sheet->setCellValue('B' . $dataRow, $row['name']);
    $sheet->setCellValue('C' . $dataRow, $row['idnumber']);

    if ($hascategories) {
        foreach ($catlist as $cat) {
            $col     = $dynamicCols[$cat->id];
            $catdata = isset($row['cattotals'][$cat->id]) ? $row['cattotals'][$cat->id] : null;
            $catavg  = ($catdata && $catdata['count'] > 0)
                ? round($catdata['total'] / $catdata['count'], 2) : 0;
            $sheet->setCellValue($col . $dataRow, $catavg);
        }
    } else {
        $sheet->setCellValue($dynamicCols['midterm'] . $dataRow, $row['midterm']);
        $sheet->setCellValue($dynamicCols['finals']  . $dataRow, $row['finals']);
    }

    $sheet->setCellValue($colAVERAGE . $dataRow, $row['average']);
    $sheet->setCellValue($colREMARKS . $dataRow, $row['remarks']);

    $sheet->getStyle("A{$dataRow}:{$lastCol}{$dataRow}")->applyFromArray([
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $evenFill]],
        'borders'   => $allBorderThin['borders'],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'font'      => ['color' => ['rgb' => $textColor], 'size' => 10],
    ]);
    $sheet->getStyle('B' . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $sheet->getRowDimension($dataRow)->setRowHeight(16);

    $dataRow++;
}

// Nothing follows row
$sheet->setCellValue('B' . $dataRow, '***Nothing Follows***');
$sheet->getStyle("A{$dataRow}:{$lastCol}{$dataRow}")->applyFromArray([
    'font'    => ['italic' => true, 'size' => 10],
    'borders' => $allBorderThin['borders'],
]);

// ── SIGNATURES ────────────────────────────────────────────────────────────────
$sigRow = $dataRow + 3;

$sheet->setCellValue('A' . $sigRow, 'Certified True & Correct:');
$sheet->setCellValue('E' . $sigRow, 'Checked:');
$sheet->getStyle('A' . $sigRow)->getFont()->setItalic(true);
$sheet->getStyle('E' . $sigRow)->getFont()->setItalic(true);

$sigRow += 3;
$sheet->mergeCells('A' . $sigRow . ':C' . $sigRow);
$sheet->setCellValue('A' . $sigRow, $instructor);
$sheet->getStyle('A' . $sigRow)->applyFromArray([
    'font'      => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->mergeCells('E' . $sigRow . ':G' . $sigRow);
$sheet->setCellValue('E' . $sigRow, $depthead);
$sheet->getStyle('E' . $sigRow)->applyFromArray([
    'font'      => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$sigRow++;
$sheet->mergeCells('A' . $sigRow . ':C' . $sigRow);
$sheet->setCellValue('A' . $sigRow, 'Instructor');
$sheet->getStyle('A' . $sigRow)->applyFromArray([
    'font'      => ['italic' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->mergeCells('E' . $sigRow . ':G' . $sigRow);
$sheet->setCellValue('E' . $sigRow, 'Department Head');
$sheet->getStyle('E' . $sigRow)->applyFromArray([
    'font'      => ['italic' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$sigRow += 4;
$sheet->setCellValue('A' . $sigRow, 'Received:');
$sheet->setCellValue('E' . $sigRow, 'Approved:');
$sheet->getStyle('A' . $sigRow)->getFont()->setItalic(true);
$sheet->getStyle('E' . $sigRow)->getFont()->setItalic(true);

$sigRow += 3;
$sheet->mergeCells('A' . $sigRow . ':C' . $sigRow);
$sheet->setCellValue('A' . $sigRow, $registrar);
$sheet->getStyle('A' . $sigRow)->applyFromArray([
    'font'      => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->mergeCells('E' . $sigRow . ':G' . $sigRow);
$sheet->setCellValue('E' . $sigRow, $collegedean);
$sheet->getStyle('E' . $sigRow)->applyFromArray([
    'font'      => ['bold' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

$sigRow++;
$sheet->mergeCells('A' . $sigRow . ':C' . $sigRow);
$sheet->setCellValue('A' . $sigRow, 'Registrar');
$sheet->getStyle('A' . $sigRow)->applyFromArray([
    'font'      => ['italic' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);
$sheet->mergeCells('E' . $sigRow . ':G' . $sigRow);
$sheet->setCellValue('E' . $sigRow, 'College Dean');
$sheet->getStyle('E' . $sigRow)->applyFromArray([
    'font'      => ['italic' => true],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
]);

// ── FOOTER ────────────────────────────────────────────────────────────────────
$footerRow = $sigRow + 3;
$sheet->setCellValue('A' . $footerRow,       'ESSU-ACAD-712.b  |  Version 5');
$sheet->setCellValue('A' . ($footerRow + 1), 'Effectivity Date: March 15, 2024');
$sheet->getStyle('A' . $footerRow)->getFont()->setSize(7);
$sheet->getStyle('A' . ($footerRow + 1))->getFont()->setSize(7);
$sheet->mergeCells('E' . $footerRow . ':' . $lastCol . $footerRow);
$sheet->setCellValue('E' . $footerRow, 'Page 1 of 1');
$sheet->getStyle('E' . $footerRow)->applyFromArray([
    'font'      => ['size' => 7],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
]);

// ── COLUMN WIDTHS ─────────────────────────────────────────────────────────────
$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(32);
$sheet->getColumnDimension('C')->setWidth(16);

if ($hascategories) {
    foreach ($dynamicCols as $catid => $col) {
        $sheet->getColumnDimension($col)->setWidth(14);
    }
} else {
    $sheet->getColumnDimension($dynamicCols['midterm'])->setWidth(12);
    $sheet->getColumnDimension($dynamicCols['finals'])->setWidth(12);
}

$sheet->getColumnDimension($colAVERAGE)->setWidth(12);
$sheet->getColumnDimension($colREMARKS)->setWidth(12);

// ── PAGE SETUP ────────────────────────────────────────────────────────────────
$orientation = $hascategories && count($catlist) > 3
    ? \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE
    : \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_PORTRAIT;

$sheet->getPageSetup()->setOrientation($orientation);
$sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_LETTER);
$sheet->getPageMargins()->setTop(0.5)->setBottom(0.5)->setLeft(0.5)->setRight(0.5);
$sheet->getPageSetup()->setFitToPage(true);
$sheet->getPageSetup()->setFitToWidth(1);
$sheet->getPageSetup()->setFitToHeight(0);

// ── OUTPUT ────────────────────────────────────────────────────────────────────
$filename = 'ReportOfGrades_' . str_replace(' ', '_', $coursename) . '_' . date('Ymd') . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;