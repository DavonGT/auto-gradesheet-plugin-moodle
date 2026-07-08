<?php
require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_item.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);
require_capability('local/gradesheet:manage', $context);

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

// Load data
$course     = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$coursename = format_string($course->fullname);
$config     = $DB->get_record('local_gradesheet_config', ['courseid' => $courseid]);
$midweight  = $config ? floatval($config->quizweight)  / 100 : 0.50;
$finweight  = $config ? floatval($config->examweight)  / 100 : 0.50;
$mpct       = $config ? $config->quizweight  : 50;
$fpct       = $config ? $config->examweight  : 50;

$students = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname ASC, u.firstname ASC');
$gitems   = $DB->get_records_select(
    'grade_items',
    'courseid = ? AND itemtype != ? AND itemname IS NOT NULL',
    [$courseid, 'course']
);

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
$failrate = 100 - $passrate;

$PAGE->set_url('/local/gradesheet/preview.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Report of Grades — ' . $coursename);
$PAGE->set_heading('Report of Grades Preview');

echo $OUTPUT->header();
?>

<style>
/* ── SCREEN TOOLBAR ───────────────────────────── */
.preview-toolbar {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 12px 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* ── GRADE SHEET PAPER ────────────────────────── */
.gradesheet-wrapper {
    background: white;
    border: 1px solid #ccc;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    padding: 30px 35px;
    max-width: 820px;
    margin: 0 auto 40px auto;
    font-family: Arial, sans-serif;
    font-size: 10px;
}

/* Header */
.gs-top {
    display: flex;
    align-items: center;
    margin-bottom: 6px;
}
.gs-logo-box {
    width: 60px; height: 60px;
    border: 1px solid #ccc;
    display: flex; align-items: center;
    justify-content: center;
    font-size: 8px; color: #999;
    flex-shrink: 0;
}
.gs-title-center {
    flex: 1;
    text-align: center;
    padding: 0 10px;
}
.gs-title-center h2 {
    font-size: 15px;
    font-weight: bold;
    margin: 0 0 2px 0;
}
.gs-title-center p {
    font-size: 9px;
    margin: 0;
    color: #555;
}
.gs-main-title {
    text-align: center;
    font-size: 16px;
    font-weight: bold;
    margin: 10px 0 2px 0;
    letter-spacing: 1px;
}
.gs-subtitle {
    text-align: center;
    font-size: 10px;
    margin: 0 0 10px 0;
}

/* Info + Legend row */
.gs-info-legend {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
}
.gs-info {
    flex: 1;
    font-size: 9.5px;
}
.gs-info table td {
    padding: 1px 4px;
    vertical-align: top;
}
.gs-info table td:first-child {
    font-style: italic;
    white-space: nowrap;
    color: #333;
}
.gs-legend {
    font-size: 8px;
}
.gs-legend table {
    border-collapse: collapse;
}
.gs-legend table th, .gs-legend table td {
    border: 1px solid #999;
    padding: 1px 4px;
    text-align: center;
}
.gs-legend table th {
    font-weight: bold;
    background: #f0f0f0;
}

/* Main grade table */
.gs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5px;
    margin-bottom: 16px;
}
.gs-table th {
    border: 1px solid #333;
    padding: 5px 3px;
    text-align: center;
    font-weight: bold;
    background: white;
}
.gs-table td {
    border: 1px solid #555;
    padding: 4px 3px;
    text-align: center;
}
.gs-table td.name-col {
    text-align: left;
    padding-left: 6px;
}
.gs-table tr:nth-child(even) td { background: #f9f9f9; }
.failed-row td { color: #c00; }

/* Signatures */
.gs-signatures {
    margin-top: 16px;
    font-size: 9.5px;
}
.gs-sig-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
}
.gs-sig-block {
    flex: 1;
}
.gs-sig-label {
    font-style: italic;
    margin-bottom: 18px;
}
.gs-sig-name {
    font-weight: bold;
    border-top: 1px solid #333;
    padding-top: 3px;
    text-align: center;
}
.gs-sig-title {
    text-align: center;
    font-style: italic;
    font-size: 8.5px;
}

/* Footer */
.gs-footer {
    border-top: 1px solid #333;
    margin-top: 20px;
    padding-top: 4px;
    display: flex;
    justify-content: space-between;
    font-size: 8px;
    color: #333;
}

/* ── PRINT STYLES ─────────────────────────────── */
@media print {
    body * { visibility: hidden; }
    .gradesheet-wrapper, .gradesheet-wrapper * { visibility: visible; }
    .gradesheet-wrapper {
        position: absolute;
        left: 0; top: 0;
        box-shadow: none;
        border: none;
        padding: 15mm 20mm;
        max-width: 100%;
        width: 100%;
    }
    .gs-table tr:nth-child(even) td { background: white !important; }
}
</style>

<!-- Toolbar (hidden on print) -->
<div class="preview-toolbar">
    <div>
        <strong>📄 Report of Grades Preview</strong>
        <span class="text-muted ml-2">— <?php echo $coursename; ?></span>
    </div>
    <div>
        <a href="index.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary btn-sm">← Back</a>
        <button onclick="window.print()" class="btn btn-primary btn-sm ml-2">🖨️ Print</button>
        <a href="export.php?courseid=<?php echo $courseid; ?>" class="btn btn-success btn-sm ml-2">⬇️ Download PDF</a>
    </div>
</div>

<!-- Grade Sheet -->
<div class="gradesheet-wrapper">

    <!-- Top: Logos + University Name -->
    <div class="gs-top">
        <div class="gs-logo-box">ESSU<br>LOGO</div>
        <div class="gs-title-center">
            <h2>EASTERN SAMAR STATE UNIVERSITY</h2>
            <p>Excellence &bull; Accountability &bull; Service</p>
        </div>
        <div class="gs-logo-box">BAGONG<br>PILIPINAS</div>
    </div>

    <div class="gs-main-title">REPORT OF GRADES</div>
    <div class="gs-subtitle">Second Semester &nbsp; SY 2024-2025</div>

    <!-- Course Info + Rating Legend -->
    <div class="gs-info-legend">
        <div class="gs-info">
            <table>
                <tr>
                    <td>Subject and Course No. :</td>
                    <td><strong><?php echo $coursename; ?></strong></td>
                </tr>
                <tr>
                    <td>Descriptive Title :</td>
                    <td><strong><?php echo $coursename; ?></strong></td>
                </tr>
                <tr>
                    <td>Course and Year :</td>
                    <td></td>
                </tr>
                <tr>
                    <td>Schedule of Classes :</td>
                    <td></td>
                </tr>
                <tr>
                    <td>Number of Units :</td>
                    <td>3</td>
                </tr>
            </table>
        </div>

        <div class="gs-legend">
            <table>
                <thead>
                    <tr>
                        <th>Actual<br>Rating</th>
                        <th>Equivalent<br>Rating</th>
                        <th>Adjectival<br>Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>100</td><td>1.0</td><td>Outstanding</td></tr>
                    <tr><td>94-90</td><td>1.1-1.5</td><td>Excellent</td></tr>
                    <tr><td>89-85</td><td>1.6-2.0</td><td>Very Good</td></tr>
                    <tr><td>84-80</td><td>2.1-2.5</td><td>Good</td></tr>
                    <tr><td>79-75</td><td>2.6-3.0</td><td>Fair</td></tr>
                    <tr><td>74-70</td><td>3.1-3.5</td><td>Conditional</td></tr>
                    <tr><td>69-55</td><td>3.6-5.0</td><td>Failed</td></tr>
                    <tr><td>INC</td><td>INC</td><td>Incomplete</td></tr>
                    <tr><td>Dr</td><td>Dr</td><td>Dropped</td></tr>
                    <tr><td>WP</td><td>WP</td><td>Withdrawn w/ permission</td></tr>
                    <tr><td>IP</td><td>IP</td><td>In Progress</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Grade Table -->
    <table class="gs-table">
        <thead>
            <tr>
                <th style="width:5%">NO.</th>
                <th style="width:30%">NAME OF STUDENTS</th>
                <th style="width:14%">STUDENT NO.</th>
                <th style="width:13%">MIDTERM</th>
                <th style="width:13%">FINALS</th>
                <th style="width:13%">AVERAGE</th>
                <th style="width:12%">REMARKS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $i => $row): ?>
            <tr class="<?php echo $row['remarks'] === 'Failed' ? 'failed-row' : ''; ?>">
                <td><?php echo $i + 1; ?></td>
                <td class="name-col"><?php echo htmlspecialchars($row['name']); ?></td>
                <td><?php echo htmlspecialchars($row['idnumber']); ?></td>
                <td><?php echo $row['midterm']; ?></td>
                <td><?php echo $row['finals']; ?></td>
                <td><?php echo $row['average']; ?></td>
                <td><?php echo $row['remarks']; ?></td>
            </tr>
            <?php endforeach; ?>
            <tr>
                <td></td>
                <td class="name-col"><em>***Nothing Follows***</em></td>
                <td></td><td></td><td></td><td></td><td></td>
            </tr>
        </tbody>
    </table>

    <!-- Signatures -->
    <div class="gs-signatures">
        <div class="gs-sig-row">
            <div class="gs-sig-block">
                <div class="gs-sig-label">Certified True &amp; Correct:</div>
                <div class="gs-sig-name">________________________________</div>
                <div class="gs-sig-title">Instructor</div>
            </div>
            <div class="gs-sig-block">
                <div class="gs-sig-label">Checked:</div>
                <div class="gs-sig-name">________________________________</div>
                <div class="gs-sig-title">Department Head</div>
            </div>
        </div>
        <div class="gs-sig-row">
            <div class="gs-sig-block">
                <div class="gs-sig-label">Received:</div>
                <div class="gs-sig-name">________________________________</div>
                <div class="gs-sig-title">Registrar</div>
            </div>
            <div class="gs-sig-block">
                <div class="gs-sig-label">Approved:</div>
                <div class="gs-sig-name">________________________________</div>
                <div class="gs-sig-title">College Dean</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="gs-footer">
        <span>ESSU-ACAD-712.b &nbsp;|&nbsp; Version 5<br>Effectivity Date: March 15, 2024</span>
        <span>Page 1 of 1</span>
    </div>

</div>

<?php echo $OUTPUT->footer(); ?>