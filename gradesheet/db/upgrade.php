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

    return true;
}
