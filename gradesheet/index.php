<?php
require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_item.php');

require_login();

$courseid = optional_param('courseid', 0, PARAM_INT);

$PAGE->set_url('/local/gradesheet/index.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_gradesheet'));
$PAGE->set_heading(get_string('pluginname', 'local_gradesheet'));

// Transmutation function
function transmute($grade) {
    if ($grade >= 97) return '1.00';
    if ($grade >= 94) return '1.25';
    if ($grade >= 91) return '1.50';
    if ($grade >= 88) return '1.75';
    if ($grade >= 85) return '2.00';
    if ($grade >= 82) return '2.25';
    if ($grade >= 79) return '2.50';
    if ($grade >= 76) return '2.75';
    if ($grade >= 75) return '3.00';
    return '5.00';
}

// Helper: compute grades for one student in a course
function compute_student_grades($DB, $courseid, $studentid, $midweight, $finweight) {
    $gitems = $DB->get_records_select(
        'grade_items',
        'courseid = ? AND itemtype != ? AND itemname IS NOT NULL',
        [$courseid, 'course']
    );

    $midTotal = 0; $midCount = 0;
    $finTotal = 0; $finCount = 0;

    foreach ($gitems as $gitem) {
        $ggrade = $DB->get_record('grade_grades', [
            'itemid' => $gitem->id,
            'userid' => $studentid
        ]);

        $val = ($ggrade && $ggrade->finalgrade !== null)
            ? floatval($ggrade->finalgrade) : 0;

        $max = floatval($gitem->grademax);
        if ($max > 0 && $max != 100) {
            $val = ($val / $max) * 100;
        }

        $map    = $DB->get_record('local_gradesheet_itemmap', [
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
    $transmuted = ($average >= 75) ? number_format(3.0 - (($average - 75) / 25) * 2.0, 2) : '5.00';

    return [
        'midterm'   => $midAvg,
        'finals'    => $finAvg,
        'average'   => $average,
        'transmuted'=> $transmuted,
        'remarks'   => $average >= 75 ? 'PASSED' : 'FAILED',
    ];
}

echo $OUTPUT->header();

// ── DETECT ROLE ───────────────────────────────────────────────────────────────
$isadmin   = is_siteadmin();
$isstudent = false;

// Check if user is a student in the selected course
if ($courseid) {
    $ctx   = context_course::instance($courseid);
    $roles = get_user_roles($ctx, $USER->id);
    foreach ($roles as $role) {
        if ($role->shortname === 'student') {
            $isstudent = true;
            break;
        }
    }
}

// ── COURSE SELECTOR ───────────────────────────────────────────────────────────
if ($isadmin) {
    $courses = $DB->get_records('course', null, 'fullname ASC');
    unset($courses[1]);
} else {
    $courses = enrol_get_my_courses();
    // Remove site course
    unset($courses[1]);
}

echo '<div class="container mt-4">';
echo '<h2>Grade Sheet Generator</h2>';
echo '<form method="get" action="">';
echo '<div class="form-group">';
echo '<label for="courseid"><strong>Select Course:</strong></label>';
echo '<select name="courseid" id="courseid" class="form-control" onchange="this.form.submit()">';
echo '<option value="">-- Select a Course --</option>';

foreach ($courses as $course) {
    $selected = ($courseid == $course->id) ? 'selected' : '';
    echo "<option value='{$course->id}' {$selected}>{$course->fullname}</option>";
}

echo '</select>';
echo '</div>';
echo '</form>';

// ── SHOW VIEW BASED ON ROLE ───────────────────────────────────────────────────
if ($courseid) {
    $context    = context_course::instance($courseid);
    $config     = $DB->get_record('local_gradesheet_config', ['courseid' => $courseid]);
    $quizweight = $config ? floatval($config->quizweight)     / 100 : 0.30;
    $examweight = $config ? floatval($config->examweight)     / 100 : 0.40;
    $actweight  = $config ? floatval($config->activityweight) / 100 : 0.30;
    $qpct       = $config ? $config->quizweight     : 30;
    $epct       = $config ? $config->examweight     : 40;
    $apct       = $config ? $config->activityweight : 30;
    $coursename = format_string($DB->get_field('course', 'fullname', ['id' => $courseid]));

    // ── STUDENT VIEW ─────────────────────────────────────────────────────────
    if ($isstudent && !$isadmin) {
        $grades = compute_student_grades($DB, $courseid, $USER->id, $quizweight, $examweight);
        $color  = $grades['remarks'] === 'PASSED' ? '#155724' : '#721c24';
        $bg     = $grades['remarks'] === 'PASSED' ? '#d4edda' : '#f8d7da';
        $icon   = $grades['remarks'] === 'PASSED' ? '✅' : '❌';

        echo '<hr>';
        echo "<h4>My Grades — {$coursename}</h4>";
        echo '
        <style>
            .grade-card { max-width: 500px; margin: 0 auto; }
            .grade-card .card-header {
                background: #1a1a2e; color: white;
                font-size: 16px; font-weight: bold; text-align: center;
            }
            .grade-row { display: flex; justify-content: space-between;
                         padding: 10px 15px; border-bottom: 1px solid #eee; }
            .grade-row:last-child { border-bottom: none; }
            .grade-label { color: #555; font-weight: 500; }
            .grade-value { font-weight: bold; }
            .remarks-box {
                text-align: center; padding: 15px;
                border-radius: 8px; margin-top: 15px;
                font-size: 20px; font-weight: bold;
            }
        </style>
        ';

        echo '<div class="grade-card">';
        echo '<div class="card">';
        echo '<div class="card-header">📋 My Grade Report</div>';
        echo '<div class="card-body p-0">';

        echo '<div class="grade-row">
                <span class="grade-label">Student ID</span>
                <span class="grade-value">' . s($USER->idnumber) . '</span>
              </div>';
        echo '<div class="grade-row">
                <span class="grade-label">Name</span>
                <span class="grade-value">' . fullname($USER) . '</span>
              </div>';
        echo '<div class="grade-row">
                <span class="grade-label">Course</span>
                <span class="grade-value">' . $coursename . '</span>
              </div>';

        echo '<div class="grade-row">
		    <span class="grade-label">Midterm (' . $qpct . '%)</span>
		    <span class="grade-value">' . number_format($grades['midterm'], 2) . '</span>
		  </div>';
		echo '<div class="grade-row">
				<span class="grade-label">Finals (' . $epct . '%)</span>
				<span class="grade-value">' . number_format($grades['finals'], 2) . '</span>
			  </div>';

        echo '<div class="grade-row" style="background:#f8f9fa">
                <span class="grade-label">Final Grade</span>
                <span class="grade-value">' . number_format($grades['final'], 2) . '</span>
              </div>';
        echo '<div class="grade-row" style="background:#f8f9fa">
                <span class="grade-label">Transmuted Grade</span>
                <span class="grade-value" style="font-size:18px">' . $grades['transmuted'] . '</span>
              </div>';

        echo '</div></div>';

        echo '<div class="remarks-box" style="background:' . $bg . '; color:' . $color . '">
                ' . $icon . ' ' . $grades['remarks'] . '
              </div>';

        echo '</div>';

    // ── FACULTY / ADMIN VIEW ──────────────────────────────────────────────────
    } else {
        if (!has_capability('local/gradesheet:manage', $context)) {
            echo $OUTPUT->notification('You do not have permission to view grade sheets for this course.', 'error');
            echo $OUTPUT->footer();
            exit;
        }

        $students = get_enrolled_users($context, '', 0, 'u.*', 'u.lastname ASC, u.firstname ASC');
        $gitems   = $DB->get_records_select(
            'grade_items',
            'courseid = ? AND itemtype != ? AND itemname IS NOT NULL',
            [$courseid, 'course']
        );

        echo '<hr>';
        echo "<h4>Students and Grades — {$coursename}</h4>";
        echo '<a href="preview.php?courseid=' . $courseid . '" class="btn btn-primary mb-3">👁 Preview & Print</a> ';
		echo '<a href="export.php?courseid=' . $courseid . '" class="btn btn-success mb-3">⬇ Download PDF</a> ';
		echo '<a href="export_excel.php?courseid=' . $courseid . '" class="btn btn-warning mb-3">📊 Download Excel</a> ';
		echo '<a href="course_settings.php?courseid=' . $courseid . '" class="btn btn-secondary mb-3">⚙ Formula Settings</a>';

        echo '<table class="table table-bordered table-striped">';
        echo "<thead class='thead-dark'><tr>
		    <th>#</th>
		    <th>Student ID</th>
		    <th>Student Name</th>
		    <th>Midterm ({$qpct}%)</th>
		    <th>Finals ({$epct}%)</th>
		    <th>Average</th>
		    <th>Transmuted</th>
		    <th>Remarks</th>
		  </tr></thead>";
        echo '<tbody>';

        $passcount = 0;
        $failcount = 0;
        $rownum    = 1;

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

            $g = compute_student_grades($DB, $courseid, $student->id, $quizweight, $examweight);
            $badgeclass = $g['remarks'] === 'PASSED' ? 'badge-success' : 'badge-danger';

            if ($g['remarks'] === 'PASSED') $passcount++;
            else $failcount++;

            echo "<tr>
				<td>{$rownum}</td>
				<td>{$student->idnumber}</td>
				<td>{$student->lastname}, {$student->firstname}</td>
				<td>" . number_format($g['midterm'],   2) . "</td>
				<td>" . number_format($g['finals'],    2) . "</td>
				<td>" . number_format($g['average'],   2) . "</td>
				<td><strong>{$g['transmuted']}</strong></td>
				<td><span class='badge {$badgeclass}'>{$g['remarks']}</span></td>
			  </tr>";
            $rownum++;
        }

        echo '</tbody></table>';

        // Summary
        $total    = $passcount + $failcount;
        $passrate = $total > 0 ? round(($passcount / $total) * 100, 1) : 0;
        $failrate = $total > 0 ? round(($failcount / $total) * 100, 1) : 0;

        echo '<div class="card mt-3">';
        echo '<div class="card-header"><strong>📊 Class Summary</strong></div>';
        echo '<div class="card-body">';
        echo '<div class="row text-center">';
        echo "<div class='col-md-3'><h4>{$total}</h4><p class='text-muted'>Total Students</p></div>";
        echo "<div class='col-md-3'><h4 class='text-success'>{$passcount}</h4><p class='text-muted'>Passed ({$passrate}%)</p></div>";
        echo "<div class='col-md-3'><h4 class='text-danger'>{$failcount}</h4><p class='text-muted'>Failed</p></div>";
        echo "<div class='col-md-3'><h4>{$passrate}%</h4><p class='text-muted'>Passing Rate</p></div>";
        echo '</div>';
        echo "<div class='progress mt-2' style='height:25px'>
                <div class='progress-bar bg-success' style='width:{$passrate}%'>{$passrate}% Passed</div>
                <div class='progress-bar bg-danger' style='width:{$failrate}%'>{$failrate}% Failed</div>
              </div>";
        echo '</div></div>';
    }
}

echo '</div>';
echo $OUTPUT->footer();