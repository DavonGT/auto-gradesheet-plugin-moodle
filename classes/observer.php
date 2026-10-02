<?php
namespace local_gradesheet;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_gradesheet plugin.
 */
class observer {

    /**
     * Triggered when a new course is created in Moodle.
     *
     * @param \core\event\course_created $event
     */
    public static function course_created(\core\event\course_created $event) {
        $courseid = $event->objectid;
        if ($courseid > 0) {
            \local_gradesheet\helper::ensure_course_defaults($courseid);
        }
    }

    /**
     * Triggered when a course is deleted in Moodle.
     * Cleans up orphaned records.
     *
     * @param \core\event\course_deleted $event
     */
    public static function course_deleted(\core\event\course_deleted $event) {
        global $DB;
        $courseid = $event->objectid;
        if ($courseid > 0) {
            $DB->delete_records('local_gradesheet_config', ['courseid' => $courseid]);
            $DB->delete_records('local_gradesheet_categories', ['courseid' => $courseid]);
            $DB->delete_records('local_gradesheet_itemmap', ['courseid' => $courseid]);
            $DB->delete_records('local_gradesheet_transmute', ['courseid' => $courseid]);
            $DB->delete_records('local_gradesheet_status', ['courseid' => $courseid]);
            $DB->delete_records('local_gradesheet_groupcfg', ['courseid' => $courseid]);
        }
    }

    /**
     * Triggered when a group is deleted: drop its section overrides.
     *
     * @param \core\event\group_deleted $event
     */
    public static function group_deleted(\core\event\group_deleted $event) {
        global $DB;
        $groupid = $event->objectid;
        if ($groupid > 0) {
            $DB->delete_records('local_gradesheet_groupcfg', ['groupid' => $groupid]);
        }
    }

    /**
     * Triggered when a user is deleted in Moodle.
     * Cleans up orphaned status records.
     *
     * @param \core\event\user_deleted $event
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB;
        $userid = $event->objectid;
        if ($userid > 0) {
            $DB->delete_records('local_gradesheet_status', ['userid' => $userid]);
        }
    }
}
