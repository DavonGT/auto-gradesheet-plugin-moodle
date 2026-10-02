<?php
ob_start();
require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_item.php');

use local_gradesheet\helper;
use local_gradesheet\pdf_builder;

$courseid = required_param('courseid', PARAM_INT);
$groupid  = optional_param('group', 0, PARAM_INT);
$action   = optional_param('action', 'download', PARAM_ALPHA);
$course   = get_course($courseid);
require_login($course);
$context  = context_course::instance($courseid);
require_capability('local/gradesheet:manage', $context);

if ($groupid > 0 && !helper::check_group_access($context, $groupid)) {
    redirect(
        new moodle_url('/local/gradesheet/index.php', ['courseid' => $courseid]),
        'You do not have permission to access the requested group.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$weightvalid = helper::validate_weight_sum($courseid);
if (!$weightvalid['valid']) {
    redirect(
        new moodle_url('/local/gradesheet/index.php', ['courseid' => $courseid]),
        'Cannot export: Category weights must sum to exactly 100% (currently ' . $weightvalid['total'] . '%). Please fix this in Settings.',
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

$built = pdf_builder::build($courseid, $groupid);
if ($built === null) {
    redirect(
        new moodle_url('/local/gradesheet/index.php', ['courseid' => $courseid]),
        $groupid > 0 ? 'Cannot export PDF: this section has no students.' : 'Cannot export PDF: No students are enrolled in this course.',
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

$pdf      = $built['pdf'];
$filename = $built['filename'];
while (ob_get_level()) {
    ob_end_clean();
}
if ($action === 'preview' || $action === 'inline') {
    header('Access-Control-Expose-Headers: Content-Disposition');
    $pdf->Output($filename, 'I');
} else {
    $pdf->Output($filename, 'D');
}
