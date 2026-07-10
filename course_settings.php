<?php
require_once('../../config.php');
require_once($CFG->libdir.'/formslib.php');

require_login();

$courseid = required_param('courseid', PARAM_INT);
$context  = context_course::instance($courseid);
require_capability('local/gradesheet:manage', $context);

$PAGE->set_url('/local/gradesheet/course_settings.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_title('Grade Sheet Settings');
$PAGE->set_heading('Grade Sheet Settings');

$coursename = $DB->get_field('course', 'fullname', ['id' => $courseid]);

$gitems = $DB->get_records_select(
    'grade_items',
    'courseid = ? AND itemtype != ? AND itemname IS NOT NULL',
    [$courseid, 'course']
);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = optional_param('action', '', PARAM_TEXT);

    if ($action === 'saveweights') {
        $mid = required_param('midweight', PARAM_FLOAT);
        $fin = required_param('finweight', PARAM_FLOAT);
        $total = $mid + $fin;
        if (abs($total - 100) > 0.01) {
            $weighterror = "Weights must add up to 100%. Current total: {$total}%";
        } else {
            $existing = $DB->get_record('local_gradesheet_config', ['courseid' => $courseid]);
            if ($existing) {
                $existing->quizweight     = $mid;
                $existing->examweight     = $fin;
                $existing->activityweight = 0;
                $existing->timemodified   = time();
                $DB->update_record('local_gradesheet_config', $existing);
            } else {
                $record = new stdClass();
                $record->courseid        = $courseid;
                $record->quizweight      = $mid;
                $record->examweight      = $fin;
                $record->activityweight  = 0;
                $record->timecreated     = time();
                $record->timemodified    = time();
                $DB->insert_record('local_gradesheet_config', $record);
            }
            $weightsuccess = "Grading weights saved!";
        }
    }

    if ($action === 'savemapping') {
        foreach ($gitems as $gitem) {
            $period   = optional_param('item_' . $gitem->id, 'finals', PARAM_TEXT);
            $period   = in_array($period, ['midterm', 'finals']) ? $period : 'finals';
            $existing = $DB->get_record('local_gradesheet_itemmap', [
                'courseid' => $courseid, 'gradeitemid' => $gitem->id,
            ]);
            if ($existing) {
                $existing->period = $period;
                $DB->update_record('local_gradesheet_itemmap', $existing);
            } else {
                $record = new stdClass();
                $record->courseid    = $courseid;
                $record->gradeitemid = $gitem->id;
                $record->period      = $period;
                $DB->insert_record('local_gradesheet_itemmap', $record);
            }
        }
        $mapsuccess = "Grade item mapping saved!";
    }

    if ($action === 'savedetails') {
        $existing = $DB->get_record('local_gradesheet_config', ['courseid' => $courseid]);

        $details = [
            'semester'       => required_param('semester',       PARAM_TEXT),
            'schoolyear'     => required_param('schoolyear',     PARAM_TEXT),
            'coursenumber'   => required_param('coursenumber',   PARAM_TEXT),
            'descriptive'    => required_param('descriptive',    PARAM_TEXT),
            'courseandyear'  => required_param('courseandyear',  PARAM_TEXT),
            'schedule'       => required_param('schedule',       PARAM_TEXT),
            'units'          => required_param('units',          PARAM_TEXT),
            'instructor'     => required_param('instructor',     PARAM_TEXT),
            'department_head'=> required_param('department_head',PARAM_TEXT),
            'registrar'      => required_param('registrar',      PARAM_TEXT),
            'college_dean'   => required_param('college_dean',   PARAM_TEXT),
        ];

        if ($existing) {
            foreach ($details as $key => $val) {
                $existing->$key = $val;
            }
            $existing->timemodified = time();
            $DB->update_record('local_gradesheet_config', $existing);
        } else {
            $record = new stdClass();
            $record->courseid        = $courseid;
            $record->quizweight      = 50;
            $record->examweight      = 50;
            $record->activityweight  = 0;
            $record->timecreated     = time();
            $record->timemodified    = time();
            foreach ($details as $key => $val) {
                $record->$key = $val;
            }
            $DB->insert_record('local_gradesheet_config', $record);
        }
        $detailsuccess = "Course details saved!";
    }
}

// Load existing config
$config    = $DB->get_record('local_gradesheet_config', ['courseid' => $courseid]);
$midweight = $config ? $config->quizweight : 50;
$finweight = $config ? $config->examweight : 50;

$mappings = [];
$maps = $DB->get_records('local_gradesheet_itemmap', ['courseid' => $courseid]);
foreach ($maps as $map) {
    $mappings[$map->gradeitemid] = $map->period;
}

echo $OUTPUT->header();
?>

<div class="container mt-4">
    <h2>⚙ Grade Sheet Settings</h2>
    <h5 class="text-muted">Course: <?php echo format_string($coursename); ?></h5>
    <a href="index.php?courseid=<?php echo $courseid; ?>" class="btn btn-secondary btn-sm mb-3">← Back to Grade Sheet</a>
    <hr>

    <!-- SECTION 1: Course Details -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <strong>📝 Course Details & Signatories</strong>
        </div>
        <div class="card-body">
            <?php if (!empty($detailsuccess)): ?>
                <div class="alert alert-success"><?php echo $detailsuccess; ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="action" value="savedetails">

                <h6 class="text-muted mb-3">— Report Header —</h6>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Semester</strong></label>
                    <div class="col-sm-6">
                        <select name="semester" class="form-control">
                            <option value="First Semester"  <?php echo ($config && $config->semester === 'First Semester')  ? 'selected' : ''; ?>>First Semester</option>
                            <option value="Second Semester" <?php echo (!$config || $config->semester === 'Second Semester') ? 'selected' : ''; ?>>Second Semester</option>
                            <option value="Summer"          <?php echo ($config && $config->semester === 'Summer')          ? 'selected' : ''; ?>>Summer</option>
                        </select>
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>School Year</strong></label>
                    <div class="col-sm-4">
                        <input type="text" name="schoolyear" class="form-control"
                               value="<?php echo $config ? s($config->schoolyear) : '2025-2026'; ?>"
                               placeholder="e.g. 2025-2026">
                    </div>
                </div>

                <hr>
                <h6 class="text-muted mb-3">— Course Information —</h6>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Subject and Course No.</strong></label>
                    <div class="col-sm-6">
                        <input type="text" name="coursenumber" class="form-control"
                               value="<?php echo $config ? s($config->coursenumber) : ''; ?>"
                               placeholder="e.g. CS 101">
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Descriptive Title</strong></label>
                    <div class="col-sm-6">
                        <input type="text" name="descriptive" class="form-control"
                               value="<?php echo $config ? s($config->descriptive) : $coursename; ?>"
                               placeholder="e.g. Computer Programming 1">
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Course and Year</strong></label>
                    <div class="col-sm-6">
                        <input type="text" name="courseandyear" class="form-control"
                               value="<?php echo $config ? s($config->courseandyear) : ''; ?>"
                               placeholder="e.g. BSCS 2A">
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Schedule of Classes</strong></label>
                    <div class="col-sm-6">
                        <input type="text" name="schedule" class="form-control"
                               value="<?php echo $config ? s($config->schedule) : ''; ?>"
                               placeholder="e.g. MWF 8:00-9:00 AM">
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Number of Units</strong></label>
                    <div class="col-sm-3">
                        <input type="text" name="units" class="form-control"
                               value="<?php echo $config ? s($config->units) : '3'; ?>"
                               placeholder="e.g. 3">
                    </div>
                </div>

                <hr>
                <h6 class="text-muted mb-3">— Signatories —</h6>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Instructor</strong></label>
                    <div class="col-sm-6">
                        <input type="text" name="instructor" class="form-control"
                               value="<?php echo $config ? s($config->instructor) : ''; ?>"
                               placeholder="e.g. JUAN DELA CRUZ">
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Department Head</strong></label>
                    <div class="col-sm-6">
                        <input type="text" name="department_head" class="form-control"
                               value="<?php echo $config ? s($config->department_head) : ''; ?>"
                               placeholder="e.g. JESUS M. MENESES, III, MATCS">
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Registrar</strong></label>
                    <div class="col-sm-6">
                        <input type="text" name="registrar" class="form-control"
                               value="<?php echo $config ? s($config->registrar) : ''; ?>"
                               placeholder="e.g. MRS. LIEZL L. DOCENA">
                    </div>
                </div>

                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>College Dean</strong></label>
                    <div class="col-sm-6">
                        <input type="text" name="college_dean" class="form-control"
                               value="<?php echo $config ? s($config->college_dean) : ''; ?>"
                               placeholder="e.g. DR. JEFFREY A. CO">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">💾 Save Course Details</button>
            </form>
        </div>
    </div>

    <!-- SECTION 2: Grading Weights -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <strong>📊 Grading Weights</strong>
        </div>
        <div class="card-body">
            <?php if (!empty($weighterror)): ?>
                <div class="alert alert-danger"><?php echo $weighterror; ?></div>
            <?php endif; ?>
            <?php if (!empty($weightsuccess)): ?>
                <div class="alert alert-success"><?php echo $weightsuccess; ?></div>
            <?php endif; ?>
            <p class="text-muted">Must total 100%.</p>
            <form method="post">
                <input type="hidden" name="action" value="saveweights">
                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Midterm Weight (%)</strong></label>
                    <div class="col-sm-3">
                        <input type="number" name="midweight" class="form-control"
                               value="<?php echo $midweight; ?>" min="0" max="100" step="0.01" id="midweight">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Finals Weight (%)</strong></label>
                    <div class="col-sm-3">
                        <input type="number" name="finweight" class="form-control"
                               value="<?php echo $finweight; ?>" min="0" max="100" step="0.01" id="finweight">
                    </div>
                </div>
                <div class="form-group row mb-3">
                    <label class="col-sm-4 col-form-label"><strong>Total</strong></label>
                    <div class="col-sm-3">
                        <input type="text" id="total_display" class="form-control" readonly
                               value="<?php echo ($midweight + $finweight); ?>%">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Save Weights</button>
            </form>
        </div>
    </div>

    <!-- SECTION 3: Grade Item Mapping -->
    <div class="card mb-4">
        <div class="card-header bg-dark text-white">
            <strong>🗂 Grade Item Mapping</strong>
        </div>
        <div class="card-body">
            <?php if (!empty($mapsuccess)): ?>
                <div class="alert alert-success"><?php echo $mapsuccess; ?></div>
            <?php endif; ?>
            <p class="text-muted">Assign each grade item to <strong>Midterm</strong> or <strong>Finals</strong>.</p>
            <?php if (empty($gitems)): ?>
                <div class="alert alert-warning">No grade items found.</div>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="action" value="savemapping">
                    <table class="table table-bordered table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th>Grade Item Name</th>
                                <th>Max Grade</th>
                                <th>Assign to Period</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gitems as $gitem): ?>
                            <?php $current = isset($mappings[$gitem->id]) ? $mappings[$gitem->id] : 'finals'; ?>
                            <tr>
                                <td><strong><?php echo format_string($gitem->itemname); ?></strong></td>
                                <td><?php echo number_format($gitem->grademax, 0); ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <input type="radio" name="item_<?php echo $gitem->id; ?>"
                                               id="mid_<?php echo $gitem->id; ?>" value="midterm"
                                               <?php echo ($current === 'midterm') ? 'checked' : ''; ?>>
                                        <label for="mid_<?php echo $gitem->id; ?>"
                                               class="btn <?php echo ($current === 'midterm') ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                            Midterm
                                        </label>
                                        <input type="radio" name="item_<?php echo $gitem->id; ?>"
                                               id="fin_<?php echo $gitem->id; ?>" value="finals"
                                               <?php echo ($current === 'finals') ? 'checked' : ''; ?>>
                                        <label for="fin_<?php echo $gitem->id; ?>"
                                               class="btn <?php echo ($current === 'finals') ? 'btn-success' : 'btn-outline-success'; ?>">
                                            Finals
                                        </label>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="submit" class="btn btn-primary">Save Mapping</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('midweight').addEventListener('input', updateTotal);
document.getElementById('finweight').addEventListener('input', updateTotal);
function updateTotal() {
    const mid = parseFloat(document.getElementById('midweight').value) || 0;
    const fin = parseFloat(document.getElementById('finweight').value) || 0;
    const total = mid + fin;
    const display = document.getElementById('total_display');
    display.value = total.toFixed(2) + '%';
    display.style.color = Math.abs(total - 100) < 0.01 ? 'green' : 'red';
}
</script>

<?php echo $OUTPUT->footer(); ?>