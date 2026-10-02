<?php
/**
 * Downloads one "Report of Grades" PDF per section (Moodle group) as a ZIP.
 * Only sections the current user may access are included.
 */
ob_start();
require_once('../../config.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/grade/grade_item.php');

use local_gradesheet\helper;
use local_gradesheet\pdf_builder;

$courseid = required_param('courseid', PARAM_INT);
$course   = get_course($courseid);
require_login($course);
$context  = context_course::instance($courseid);
require_capability('local/gradesheet:manage', $context);

$back = new moodle_url('/local/gradesheet/index.php', ['courseid' => $courseid]);

$weightvalid = helper::validate_weight_sum($courseid);
if (!$weightvalid['valid']) {
    redirect($back, 'Cannot export: Category weights must sum to exactly 100% (currently ' . $weightvalid['total'] . '%). Please fix this in Settings.',
        null, \core\output\notification::NOTIFY_ERROR);
}

$sections = helper::get_accessible_sections($context);
if (empty($sections)) {
    redirect($back, 'This course has no sections (groups). Create one group per section under Participants > Groups, or download the single sheet instead.',
        null, \core\output\notification::NOTIFY_WARNING);
}
if (!class_exists('ZipArchive')) {
    redirect($back, 'The server cannot create ZIP files (PHP zip extension missing). Download each section individually instead.',
        null, \core\output\notification::NOTIFY_ERROR);
}

$tmpdir  = make_request_directory();
$zippath = $tmpdir . '/sections.zip';
$zip = new ZipArchive();
if ($zip->open($zippath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    redirect($back, 'Could not create the ZIP file.', null, \core\output\notification::NOTIFY_ERROR);
}

$added = 0;
$empty = [];
foreach ($sections as $gid => $group) {
    $built = pdf_builder::build($courseid, (int)$gid);
    if ($built === null) {
        $empty[] = format_string($group->name);
        continue;
    }
    $zip->addFromString($built['filename'], $built['pdf']->Output('', 'S'));
    $added++;
}
if (!empty($empty)) {
    $zip->addFromString('README.txt',
        "Sections with no students were skipped: " . implode(', ', $empty) . "\n");
}
$zip->close();

if ($added === 0) {
    redirect($back, 'No section has any students yet; nothing to export.', null, \core\output\notification::NOTIFY_WARNING);
}

$zipname = clean_filename('ReportOfGrades_' . str_replace(' ', '_', format_string($course->fullname)) . '_AllSections_' . date('Ymd') . '.zip');
while (ob_get_level()) {
    ob_end_clean();
}
send_file($zippath, $zipname, 0, 0, false, true, 'application/zip');
