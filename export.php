<?php
require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_item.php');
require_once($CFG->libdir.'/pdflib.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);
require_capability('local/gradesheet:manage', $context);

function get_equivalent_pdf($grade) {
    if ($grade == 100)  return '1.0';
    if ($grade >= 94)   return number_format(1.1 + (99 - $grade) * 0.1, 1);
    if ($grade >= 89)   return number_format(1.6 + (93 - $grade) * 0.1, 1);
    if ($grade >= 84)   return number_format(2.1 + (88 - $grade) * 0.1, 1);
    if ($grade >= 79)   return number_format(2.6 + (83 - $grade) * 0.1, 1);
    if ($grade >= 75)   return number_format(3.1 + (78 - $grade) * 0.1, 1);
    if ($grade >= 69)   return number_format(3.6 + (74 - $grade) * 0.1, 1);
    return '5.0';
}

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
$categories = $DB->get_records('local_gradesheet_categories',
    ['courseid' => $courseid], 'sortorder ASC');
$hascategories = !empty($categories);

// Get students and grade items
$students = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname ASC, u.firstname ASC');
$gitems   = $DB->get_records_select(
    'grade_items',
    'courseid = ? AND itemtype != ? AND itemname IS NOT NULL',
    [$courseid, 'course']
);

// Transmute to equivalent rating
function transmute_equiv($grade) {
	if ($grade == 0)    return '-';
	if ($grade == 100)  return '1.0';
	if ($grade >= 94)   return number_format(1.1 + (99 - $grade) * 0.1, 1);
	if ($grade >= 89)   return number_format(1.6 + (93 - $grade) * 0.1, 1);
	if ($grade >= 84)   return number_format(2.1 + (88 - $grade) * 0.1, 1);
	if ($grade >= 79)   return number_format(2.6 + (83 - $grade) * 0.1, 1);
	if ($grade >= 75)   return number_format(3.1 + (78 - $grade) * 0.1, 1);
	if ($grade >= 69)   return number_format(3.6 + (74 - $grade) * 0.1, 1);
	return '5.0';
}

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

    // Init category totals
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

    // Compute weighted final
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

	

	$midTransmuted = transmute_equiv($midAvg);
	$finTransmuted = transmute_equiv($finAvg);
	$avgTransmuted = transmute_equiv($weightedFinal);

    $remarks = $weightedFinal >= 75 ? 'Passed' : 'Failed';
    if ($remarks === 'Passed') $passcount++; else $failcount++;

    $row = [
        'idnumber'   => $student->idnumber,
        'name'       => $student->lastname . ', ' . $student->firstname,
        'midterm'    => $midTransmuted,
        'finals'     => $finTransmuted,
        'average'    => $avgTransmuted,
        'remarks'    => $remarks,
        'cattotals'  => $cattotals,
    ];
    $rows[] = $row;
}

$total    = $passcount + $failcount;
$passrate = $total > 0 ? round(($passcount / $total) * 100, 1) : 0;

// ── GENERATE PDF ──────────────────────────────────────────────────────────────
$pdf = new pdf('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('ESSU Grade Sheet Plugin');
$pdf->SetTitle('Report of Grades - ' . $coursename);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

$pageW = $pdf->getPageWidth();
$topY  = 10;

// Logos
$essulogo   = $CFG->dirroot . '/local/gradesheet/pix/essu-header.png';
$bagonglogo = $CFG->dirroot . '/local/gradesheet/pix/bagong-pilipinas.png';
if (file_exists($essulogo))   $pdf->Image($essulogo,   12,             $topY, 55, 0);
if (file_exists($bagonglogo)) $pdf->Image($bagonglogo, $pageW - 37,    $topY, 25, 0);

// Title below logos
$pdf->SetY($topY + 33);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 8, 'REPORT OF GRADES', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $semester . '  SY ' . $schoolyear, 0, 1, 'C');
$pdf->Ln(5);

// Course info + legend
$infoY = $pdf->GetY();

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(15); $pdf->Cell(40, 5, 'Subject and Course No. :', 0, 0);
$pdf->SetFont('helvetica', 'B', 9); $pdf->Cell(0, 5, $coursenumber, 0, 1);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(15); $pdf->Cell(40, 5, 'Descriptive Title :', 0, 0);
$pdf->SetFont('helvetica', 'B', 9); $pdf->Cell(0, 5, $descriptive, 0, 1);

$pdf->SetFont('helvetica', '', 9);
$pdf->SetX(15); $pdf->Cell(40, 5, 'Course and Year :', 0, 0);
$pdf->Cell(0, 5, $courseandyear, 0, 1);

$pdf->SetX(15); $pdf->Cell(40, 5, 'Schedule of Classes :', 0, 0);
$pdf->Cell(0, 5, $schedule, 0, 1);

$pdf->SetX(15); $pdf->Cell(40, 5, 'Number of Units :', 0, 0);
$pdf->Cell(0, 5, $units, 0, 1);

// Rating legend
$legendX = 120;
$pdf->SetXY($legendX, $infoY);
$pdf->SetFont('helvetica', 'B', 7);
$pdf->Cell(25, 4, 'Actual Rating',     1, 0, 'C');
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
foreach ($legend as $lrow) {
    $pdf->SetX($legendX);
    $pdf->Cell(25, 3.5, $lrow[0], 1, 0, 'C');
    $pdf->Cell(25, 3.5, $lrow[1], 1, 0, 'C');
    $pdf->Cell(30, 3.5, $lrow[2], 1, 1, 'L');
}

$pdf->Ln(4);

// ── TABLE HEADER ──────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'B', 8);
$pdf->SetTextColor(0, 0, 0);
// Default Midterm/Finals
$col     = [10, 60, 28, 20, 20, 20, 22];
$headers = ['NO.', 'NAME OF STUDENTS', 'STUDENT NO.', 'MIDTERM', 'FINALS', 'AVERAGE', 'REMARKS'];
foreach ($headers as $i => $h) {
    $pdf->Cell($col[$i], 8, $h, 1, 0, 'C');
}
$pdf->Ln();

// ── DATA ROWS ─────────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', '', 8);

foreach ($rows as $i => $row) {
    $fill = ($i % 2 === 0);
    $pdf->SetFillColor(245, 245, 245);

    $isFailed = ($row['remarks'] === 'Failed');
    $pdf->SetTextColor(0, 0, 0);

    if ($isFailed) $pdf->SetTextColor(180, 0, 0);
    $pdf->Cell($col[0], 6, $i + 1,          1, 0, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell($col[1], 6, $row['name'],     1, 0, 'L', $fill);
    $pdf->Cell($col[2], 6, $row['idnumber'], 1, 0, 'C', $fill);
    $pdf->Cell($col[3], 6, $row['midterm'],  1, 0, 'C', $fill);
    $pdf->Cell($col[4], 6, $row['finals'],   1, 0, 'C', $fill);
    $pdf->Cell($col[5], 6, $row['average'],  1, 0, 'C', $fill);
    if ($isFailed) $pdf->SetTextColor(180, 0, 0);
    $pdf->Cell($col[6], 6, $row['remarks'],  1, 1, 'C', $fill);
    $pdf->SetTextColor(0, 0, 0);
}

// Nothing follows row
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell($col[0], 6, '',                     1, 0, 'C');
$pdf->Cell($col[1], 6, '***Nothing Follows***', 1, 0, 'L');
foreach ([2,3,4,5,6] as $ci) $pdf->Cell($col[$ci], 6, '', 1, 0, 'C');
$pdf->Ln();

$pdf->Ln(6);

// ── SIGNATURES ────────────────────────────────────────────────────────────────
$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(90, 5, 'Certified True & Correct:', 0, 0);
$pdf->Cell(0,  5, 'Checked:', 0, 1);
$pdf->Ln(12);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(90, 5, $instructor, 0, 0, 'C');
$pdf->Cell(0,  5, $depthead,   0, 1, 'C');

$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(90, 4, 'Instructor',      0, 0, 'C');
$pdf->Cell(0,  4, 'Department Head', 0, 1, 'C');

$pdf->Ln(8);

$pdf->SetFont('helvetica', 'I', 9);
$pdf->Cell(90, 5, 'Received:', 0, 0);
$pdf->Cell(0,  5, 'Approved:', 0, 1);
$pdf->Ln(12);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(90, 5, $registrar,   0, 0, 'C');
$pdf->Cell(0,  5, $collegedean, 0, 1, 'C');

$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(90, 4, 'Registrar',    0, 0, 'C');
$pdf->Cell(0,  4, 'College Dean', 0, 1, 'C');

// ── FOOTER ────────────────────────────────────────────────────────────────────
$pdf->SetY(-18);
$pdf->SetFont('helvetica', '', 7);
$pdf->Line(15, $pdf->GetY(), $pageW - 15, $pdf->GetY());
$pdf->Ln(1);
$pdf->Cell(0, 4, 'ESSU-ACAD-712.b  |  Version 5', 0, 0, 'L');
$pdf->Cell(0, 4, 'Page 1 of 1', 0, 1, 'R');
$pdf->Cell(0, 4, 'Effectivity Date: March 15, 2024', 0, 0, 'L');

$filename = 'ReportOfGrades_' . str_replace(' ', '_', $coursename) . '_' . date('Ymd') . '.pdf';
$pdf->Output($filename, 'D');
exit;