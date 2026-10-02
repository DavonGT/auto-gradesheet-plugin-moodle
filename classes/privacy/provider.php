<?php
namespace local_gradesheet\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy API provider for local_gradesheet.
 *
 * The plugin stores one kind of per-user data: the faculty-set academic
 * status override (Incomplete / Dropped / WP / In Progress) per student per
 * course, in local_gradesheet_status. Signatory names typed into course and
 * section settings are free text and are not linked to a Moodle user, so
 * they are declared as metadata only.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_gradesheet_status', [
            'courseid'     => 'privacy:metadata:status:courseid',
            'userid'       => 'privacy:metadata:status:userid',
            'status'       => 'privacy:metadata:status:status',
            'timemodified' => 'privacy:metadata:status:timemodified',
        ], 'privacy:metadata:status');

        $collection->add_database_table('local_gradesheet_config', [
            'instructor'      => 'privacy:metadata:config:instructor',
            'department_head' => 'privacy:metadata:config:department_head',
            'registrar'       => 'privacy:metadata:config:registrar',
            'college_dean'    => 'privacy:metadata:config:college_dean',
        ], 'privacy:metadata:config');

        $collection->add_database_table('local_gradesheet_groupcfg', [
            'instructor' => 'privacy:metadata:groupcfg:instructor',
        ], 'privacy:metadata:groupcfg');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {local_gradesheet_status} s
                  JOIN {context} ctx ON ctx.instanceid = s.courseid AND ctx.contextlevel = :courselevel
                 WHERE s.userid = :userid";
        $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userlist->add_from_sql('userid',
            "SELECT userid FROM {local_gradesheet_status} WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }
            $record = $DB->get_record('local_gradesheet_status', ['courseid' => $context->instanceid, 'userid' => $userid]);
            if (!$record) {
                continue;
            }
            $data = (object)[
                'status'       => $record->status,
                'statuslabel'  => \local_gradesheet\helper::status_label($record->status),
                'timemodified' => transform::datetime($record->timemodified),
            ];
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_gradesheet')], $data);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context instanceof \context_course) {
            $DB->delete_records('local_gradesheet_status', ['courseid' => $context->instanceid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $DB->delete_records('local_gradesheet_status', ['courseid' => $context->instanceid, 'userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }
        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select('local_gradesheet_status', "courseid = :courseid AND userid $insql", $params);
    }
}
