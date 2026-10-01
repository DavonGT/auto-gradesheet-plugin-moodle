<?php
defined('MOODLE_INTERNAL') || die();

function local_gradesheet_extend_navigation_course($navigation, $course, $context) {
    $canmanage = has_capability('local/gradesheet:manage', $context);
    $isteacher = $canmanage || has_capability('moodle/grade:viewall', $context);

    // SEC-03: Suppress navigation node for students when course-level grades are hidden.
    if (!$isteacher && empty($course->showgrades)) {
        return;
    }

    if (has_capability('local/gradesheet:view', $context) || $canmanage) {
        $url  = new moodle_url('/local/gradesheet/index.php', ['courseid' => $course->id]);
        $node = navigation_node::create(
            'Grade Sheet',
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_gradesheet',
            new pix_icon('i/grades', '')
        );
        $node->showinflatnavigation = true;
        $navigation->add_node($node);
    }
}

function local_gradesheet_extend_navigation(global_navigation $nav) {
    if (isloggedin() && !isguestuser()) {
        $url  = new moodle_url('/local/gradesheet/index.php');
        $node = $nav->add(
            'Grade Sheet',
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'local_gradesheet'
        );
        $node->showinflatnavigation = true;
    }
}

function local_gradesheet_extend_settings_navigation($settingsnav, $context) {
    if (!$context || !($context instanceof context_course)) {
        return;
    }

    $canmanage = has_capability('local/gradesheet:manage', $context);
    $isteacher = $canmanage || has_capability('moodle/grade:viewall', $context);

    $course = get_course($context->instanceid);
    if (!$isteacher && empty($course->showgrades)) {
        return;
    }

    if (has_capability('local/gradesheet:view', $context) || $canmanage) {
        $coursenode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE);
        if ($coursenode) {
            $url  = new moodle_url('/local/gradesheet/index.php', ['courseid' => $context->instanceid]);
            $node = navigation_node::create(
                'Grade Sheet',
                $url,
                navigation_node::TYPE_SETTING,
                null,
                'local_gradesheet_settings',
                new pix_icon('i/grades', '')
            );
            $coursenode->add_node($node);
        }
    }
}
