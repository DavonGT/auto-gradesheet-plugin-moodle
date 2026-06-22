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

echo $OUTPUT->header();

// Course selector
$courses = enrol_get_my_courses();

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

// If a course is selected, show students and grades
if ($courseid) {
    $context = context_course::instance($courseid);

    // Check permission
    if (!has_capability('local/gradesheet:manage', $context)) {
        echo $OUTPUT->notification('You do not have permission to view grade sheets for this course.', 'error');
        echo $OUTPUT->footer();
        exit;
    }

    // Get enrolled students
    $students = get_enrolled_users($context, '');

    // Get grade items for this course
    $gradeitems = grade_item::fetch_all(['courseid' => $courseid]);

    echo '<hr>';
    echo '<h4>Students and Grades — Course ID: ' . $courseid . '</h4>';
    echo '<a href="export.php?courseid=' . $courseid . '" class="btn btn-success mb-3">Export PDF</a>';
    echo '<table class="table table-bordered table-striped">';
    echo '<thead><tr>
            <th>Student ID</th>
            <th>Student Name</th>
            <th>Quiz (30%)</th>
            <th>Exam (40%)</th>
            <th>Activity (30%)</th>
            <th>Final Grade</th>
          </tr></thead>';
    echo '<tbody>';

    if (empty($students)) {
        echo '<tr><td colspan="6">No students enrolled in this course.</td></tr>';
    } else {
        foreach ($students as $student) {
            // Get grades from Moodle gradebook
            $grades = grade_get_grades($courseid, null, null, null, $student->id);

            $quizTotal = 0; $quizCount = 0;
            $examTotal = 0; $examCount = 0;
            $actTotal  = 0; $actCount  = 0;

            if (!empty($grades->items)) {
                foreach ($grades->items as $item) {
                    $grade = reset($item->grades);
                    $val = isset($grade->grade) ? floatval($grade->grade) : 0;

                    if (stripos($item->itemname, 'quiz') !== false) {
                        $quizTotal += $val; $quizCount++;
                    } else if (stripos($item->itemname, 'exam') !== false) {
                        $examTotal += $val; $examCount++;
                    } else {
                        $actTotal += $val; $actCount++;
                    }
                }
            }

            $quizAvg = $quizCount > 0 ? $quizTotal / $quizCount : 0;
            $examAvg = $examCount > 0 ? $examTotal / $examCount : 0;
            $actAvg  = $actCount  > 0 ? $actTotal  / $actCount  : 0;

            $final = ($quizAvg * 0.30) + ($examAvg * 0.40) + ($actAvg * 0.30);

            echo "<tr>
                <td>{$student->idnumber}</td>
                <td>{$student->firstname} {$student->lastname}</td>
                <td>" . number_format($quizAvg, 2) . "</td>
                <td>" . number_format($examAvg, 2) . "</td>
                <td>" . number_format($actAvg,  2) . "</td>
                <td><strong>" . number_format($final, 2) . "</strong></td>
              </tr>";
        }
    }

    echo '</tbody></table>';
}

echo '</div>';
echo $OUTPUT->footer();
