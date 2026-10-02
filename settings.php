<?php
defined('MOODLE_INTERNAL') || die();

// Site administration > Plugins > Local plugins > Grade Sheet Generator.
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_gradesheet', get_string('pluginname', 'local_gradesheet'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_heading(
        'local_gradesheet/signatoryroles',
        get_string('signatoryroles', 'local_gradesheet'),
        get_string('signatoryroles_desc', 'local_gradesheet')
    ));

    $settings->add(new admin_setting_configtext(
        'local_gradesheet/role_departmenthead',
        get_string('role_departmenthead', 'local_gradesheet'),
        get_string('role_departmenthead_desc', 'local_gradesheet'),
        'departmenthead', PARAM_ALPHANUMEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_gradesheet/role_collegedean',
        get_string('role_collegedean', 'local_gradesheet'),
        get_string('role_collegedean_desc', 'local_gradesheet'),
        'collegedean', PARAM_ALPHANUMEXT
    ));
    $settings->add(new admin_setting_configtext(
        'local_gradesheet/role_registrar',
        get_string('role_registrar', 'local_gradesheet'),
        get_string('role_registrar_desc', 'local_gradesheet'),
        'registrar', PARAM_ALPHANUMEXT
    ));
}
