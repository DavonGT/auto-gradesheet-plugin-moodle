<?php
/** Stub of lib/gradelib.php: records regrade calls so the harness can assert on them. */
$GLOBALS['MOCK_REGRADE_CALLS'] = $GLOBALS['MOCK_REGRADE_CALLS'] ?? [];
function grade_regrade_final_grades($courseid, $userid = null, $updated_item = null, $progress = null) {
    global $DB;
    $GLOBALS['MOCK_REGRADE_CALLS'][] = (int)$courseid;
    // A real regrade clears needsupdate; mirror that so the cache/once-per-request logic is testable.
    foreach ($DB->tables['grade_items'] as $id => $gi) {
        if ((int)$gi->courseid === (int)$courseid && !empty($gi->needsupdate)) {
            $DB->tables['grade_items'][$id]->needsupdate = 0;
        }
    }
    return true;
}
