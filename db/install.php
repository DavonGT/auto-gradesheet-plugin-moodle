<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_gradesheet_install() {
    // Roles used for signatory auto-detection (Department Head, College Dean, Registrar).
    \local_gradesheet\helper::ensure_signatory_roles();
    return true;
}
