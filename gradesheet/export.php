<?php
require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_item.php');
require_once($CFG->libdir.'/pdflib.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);
require_capability('local/gradesheet:manage', $context);

// Transmutation — matches ESSU official scale
function transmute_essu($grade) {
    if ($grade == 100)            return '1.0';
    if ($grade >= 94)             return '1.1-1.5';
    if ($grade >= 89)             return '1.6-2.0';
    if ($grade >= 84)             return '2.1-2.5';
    if ($grade >= 79)             return '2.6-3.0';
    if ($grade >= 75)             return '3.1-3.5';
    if ($grade >= 69)             return '3.6-5.0';
    return '5.0';
}

function get_equivalent($grade) {
    if ($grade == 100)  return '1.0';
    if ($grade >= 94)   return number_format(1.1 + (99 - $grade) * 0.1, 1);
    if ($grade >= 89)   return number_format(1.6 + (93 - $grade) * 0.1, 1);
    if ($grade >= 84)   return number_format(2.1 + (88 - $grade) * 0.1, 1);
    if ($grade >= 79)   return number_format(2.6 + (83 - $grade) * 0.1, 1);
    if ($grade >= 75)   return number_format(3.1 + (78 - $grade) * 0.1, 1);
    if ($grade >= 69)   return number_format(3.6 + (74 - $grade) * 0.1, 1);
    return '5.0';
}

function get_remarks_essu($grade) {
    return ($grade >= 75) ? 'Passed' : 'Failed';
}

// Load course and config
$course     = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursename = format_string($course->fullname);
$config     = $DB->get_record('local_gradesheet_config', ['courseid' => $courseid]);
$midweight  = $config ? floatval($config->quizweight)     / 100 : 0.50;
$finweight  = $config ? floatval($config->examweight)     / 100 : 0.50;
$mpct       = $config ? $config->quizweight     : 50;
$fpct       = $config ? $config->examweight     : 50;

// Get students alphabetically
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
    $roles = get_user_roles($context, $student->id);
    $isteacher = false;
    foreach ($roles as $role) {
        if ($role->shortname === 'teacher' || $role->shortname === 'editingteacher') {
            $isteacher = true; break;
        }
    }
    if ($isteacher) continue;

    $midTotal = 0; $midCount = 0;
    $finTotal = 0; $finCount = 0;

    foreach ($gitems as $gitem) {
        $ggrade = $DB->get_record('grade_grades', ['itemid' => $gitem->id, 'userid' => $student->id]);
        $val    = ($ggrade && $ggrade->finalgrade !== null) ? floatval($ggrade->finalgrade) : 0;
        $max    = floatval($gitem->grademax);
        if ($max > 0 && $max != 100) $val = ($val / $max) * 100;

        $map = $DB->get_record('local_gradesheet_itemmap', [
            'courseid'    => $courseid,
            'gradeitemid' => $gitem->id,
        ]);
        $period = $map ? $map->period : 'finals';
        if ($period === 'midterm') {
            $midTotal += $val; $midCount++;
        } else {
            $finTotal += $val; $finCount++;
        }
    }

    $midAvg  = $midCount > 0 ? $midTotal / $midCount : 0;
    $finAvg  = $finCount > 0 ? $finTotal / $finCount : 0;
    $average = ($midAvg * $midweight) + ($finAvg * $finweight);
    $equiv   = get_equivalent($average);
    $remarks = get_remarks_essu($average);

    if ($remarks === 'Passed') $passcount++; else $failcount++;

    $rows[] = [
        'idnumber' => $student->idnumber,
        'name'     => $student->lastname . ', ' . $student->firstname,
        'midterm'  => number_format($midAvg,  2),
        'finals'   => number_format($finAvg,  2),
        'average'  => number_format($average, 2),
        'equiv'    => $equiv,
        'remarks'  => $remarks,
    ];
}

$total    = $passcount + $failcount;
$passrate = $total > 0 ? round(($passcount / $total) * 100, 1) : 0;

// ── GENERATE PDF ─────────────────────────────────────────────────────────────
$pdf = new pdf('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('ESSU Grade Sheet Plugin');
$pdf->SetTitle('Report of Grades - ' . $coursename);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

$pageW = $pdf->getPageWidth() - 30; // usable width

// ── LOGOS + TITLE ─────────────────────────────────────────────────────────────
// Left: ESSU logo placeholder, Right: Bagong Pilipinas placeholder
$pdf->SetFont('helvetica', 'B', 7);
$pdf->SetFillColor(200, 200, 200);
$pdf->Rect(15, 12, 22, 22, 'F');
$pdf->SetXY(15, 20);
$pdf->Cell(22, 6, 'ESSU LOGO', 0, 0, 'C');

$pdf->Rect($pdf->getPageWidth() - 37, 12, 22, 22, 'F');
$pdf->SetXY($pdf->getPageWidth() - 37, 20);
$pdf->Cell(22, 6, 'BAGONG', 0, 0, 'C');

// University name center
$pdf->SetXY(40, 12);
$pdf->SetFont('helvetica', 'B', 13);
$pdf->Cell($pageW - 50, 7, 'EASTERN SAMAR STATE UNIVERSITY', 0, 1, 'C');

$pdf->SetX(40);
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell($pageW - 50, 5, 'Excellence  •  Accountability  •  Service', 0, 1, 'C');

$pdf->Ln(8);

// Title
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'REPORT OF GRADES', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Second Semester  SY 2024-2025', 0, 1, 'C');
$pdf->Ln(3);

// ── COURSE INFO + RATING LEGEND ──────────────────────────────────────────────
$infoY = $pdf->GetY();

// Left block
$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(15);
$pdf->Cell(35, 5, 'Subject and Course No. :', 0, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, $coursename, 0, 1);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(15);
$pdf->Cell(35, 5, 'Descriptive Title :', 0, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 5, $coursename, 0, 1);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(15);
$pdf->Cell(35, 5, 'Course and Year :', 0, 0);
$pdf->Cell(0, 5, '', 0, 1);

$pdf->SetX(15);
$pdf->Cell(35, 5, 'Schedule of Classes :', 0, 0);
$pdf->Cell(0, 5, '', 0, 1);

$pdf->SetX(15);
$pdf->Cell(35, 5, 'Number of Units :', 0, 0);
$pdf->Cell(0, 5, '3', 0, 1);

// Right block — Rating legend
$legendX = 120;
$legendY = $infoY;
$pdf->SetXY($legendX, $legendY);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell(25, 4, 'Actual Rating', 1, 0, 'C');
$pdf->Cell(25, 4, 'Equivalent Rating', 1, 0, 'C');
$pdf->Cell(30, 4, 'Adjectival Rating', 1, 1, 'C');

$legend = [
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

$pdf->SetFont('helvetica', '', 7);
foreach ($legend as $row) {
    $pdf->SetX($legendX);
    $pdf->Cell(25, 3.5, $row[0], 1, 0, 'C');
    $pdf->Cell(25, 3.5, $row[1], 1, 0, 'C');
    $pdf->Cell(30, 3.5, $row[2], 1, 1, 'L');
}

$pdf->Ln(4);

// ── MAIN TABLE ────────────────────────────────────────────────────────────────
// Columns: NO | NAME OF STUDENTS | STUDENT NO. | MIDTERM | FINALS | AVERAGE | REMARKS
$pdf->SetFillColor(255, 255, 255);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetDrawColor(0, 0, 0);

$col = [10, 60, 28, 20, 20, 20, 22]; // widths
$headers = ['NO.', 'NAME OF STUDENTS', 'STUDENT NO.', 'MIDTERM', 'FINALS', 'AVERAGE', 'REMARKS'];

foreach ($headers as $i => $h) {
    $pdf->Cell($col[$i], 8, $h, 1, 0, 'C', false);
}
$pdf->Ln();

// Data rows
$pdf->SetFont('helvetica', '', 8);
foreach ($rows as $i => $row) {
    $fill = ($i % 2 === 0);
    $pdf->SetFillColor(245, 245, 245);

    // Highlight failed in red
    if ($row['remarks'] === 'Failed') {
        $pdf->SetTextColor(180, 0, 0);
    } else {
        $pdf->SetTextColor(0, 0, 0);
    }

    $pdf->Cell($col[0], 6, $i + 1,         1, 0, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($col[1], 6, $row['name'],    1, 0, 'L', $fill);
    $pdf->Cell($col[2], 6, $row['idnumber'],1, 0, 'C', $fill);
    $pdf->Cell($col[3], 6, $row['midterm'], 1, 0, 'C', $fill);
    $pdf->Cell($col[4], 6, $row['finals'],  1, 0, 'C', $fill);
    $pdf->Cell($col[5], 6, $row['average'], 1, 0, 'C', $fill);

    if ($row['remarks'] === 'Failed') $pdf->SetTextColor(180, 0, 0);
    $pdf->Cell($col[6], 6, $row['remarks'], 1, 1, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0);
}

// Nothing follows row
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell($col[0], 6, '',                    1, 0, 'C');
$pdf->Cell($col[1], 6, '***Nothing Follows***',1, 0, 'L');
$pdf->Cell($col[2], 6, '',                    1, 0, 'C');
$pdf->Cell($col[3], 6, '',                    1, 0, 'C');
$pdf->Cell($col[4], 6, '',                    1, 0, 'C');
$pdf->Cell($col[5], 6, '',                    1, 0, 'C');
$pdf->Cell($col[6], 6, '',                    1, 1, 'C');

$pdf->Ln(6);

// ── SIGNATURE BLOCK ───────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(90, 5, 'Certified True & Correct:', 0, 0);
$pdf->Cell(0,  5, 'Checked:', 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 5, '_______________________________', 0, 0);
$pdf->Cell(0,  5, '_______________________________', 0, 1);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(90, 4, 'Instructor', 0, 0, 'C');
$pdf->Cell(0,  4, 'Department Head', 0, 1);

$pdf->Ln(6);

$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(90, 5, 'Received:', 0, 0);
$pdf->Cell(0,  5, 'Approved:', 0, 1);

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(90, 5, '_______________________________', 0, 0);
$pdf->Cell(0,  5, '_______________________________', 0, 1);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(90, 4, 'Registrar', 0, 0, 'C');
$pdf->Cell(0,  4, 'College Dean', 0, 1);

// ── BOTTOM FOOTER ─────────────────────────────────────────────────────────────
$pdf->SetY(-18);
$pdf->SetFont('helvetica', '', 7);
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(15, $pdf->GetY(), $pdf->getPageWidth() - 15, $pdf->GetY());
$pdf->Ln(1);
$pdf->Cell(0, 4, 'ESSU-ACAD-712.b  |  Version 5', 0, 0, 'L');
$pdf->Cell(0, 4, 'Page 1 of 1', 0, 1, 'R');
$pdf->Cell(0, 4, 'Effectivity Date: March 15, 2024', 0, 0, 'L');

// ── OUTPUT ────────────────────────────────────────────────────────────────────
$filename = 'ReportOfGrades_' . str_replace(' ', '_', $coursename) . '_' . date('Ymd') . '.pdf';
$pdf->Output($filename, 'D');
exit;