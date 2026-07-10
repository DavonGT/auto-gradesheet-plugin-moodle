<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_gradesheet_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026031201) {
        $table = new xmldb_table('local_gradesheet_itemmap');
        $table->add_field('id',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('gradeitemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null);
        $table->add_field('period',      XMLDB_TYPE_CHAR,    '10', null, XMLDB_NOTNULL, null, 'finals');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid_itemid', XMLDB_INDEX_UNIQUE, ['courseid', 'gradeitemid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026031201, 'local', 'gradesheet');
    }

    if ($oldversion < 2026031202) {
        $table = new xmldb_table('local_gradesheet_config');

        $fields = [
            new xmldb_field('semester',        XMLDB_TYPE_CHAR, '50',  null, null, null, 'Second Semester'),
            new xmldb_field('schoolyear',      XMLDB_TYPE_CHAR, '20',  null, null, null, '2025-2026'),
            new xmldb_field('coursenumber',    XMLDB_TYPE_CHAR, '50',  null, null, null, ''),
            new xmldb_field('descriptive',     XMLDB_TYPE_CHAR, '100', null, null, null, ''),
            new xmldb_field('courseandyear',   XMLDB_TYPE_CHAR, '50',  null, null, null, ''),
            new xmldb_field('schedule',        XMLDB_TYPE_CHAR, '50',  null, null, null, ''),
            new xmldb_field('units',           XMLDB_TYPE_CHAR, '10',  null, null, null, '3'),
            new xmldb_field('instructor',      XMLDB_TYPE_CHAR, '100', null, null, null, ''),
            new xmldb_field('department_head', XMLDB_TYPE_CHAR, '100', null, null, null, ''),
            new xmldb_field('registrar',       XMLDB_TYPE_CHAR, '100', null, null, null, ''),
            new xmldb_field('college_dean',    XMLDB_TYPE_CHAR, '100', null, null, null, ''),
        ];

        foreach ($fields as $field) {
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        upgrade_plugin_savepoint(true, 2026031202, 'local', 'gradesheet');
    }

    return true;
}