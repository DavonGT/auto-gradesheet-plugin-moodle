<?php
namespace local_gradesheet;

defined('MOODLE_INTERNAL') || die();

class gradesheet_service {

    public static function compute_all_grades(int $courseid, int $groupid = 0): array {
        global $DB, $USER;

        $cfg    = helper::load_course_config($courseid);
        $ctx    = \context_course::instance($courseid);
        $students = helper::get_non_teaching_students($ctx, $groupid);
        $statusmap = helper::get_status_map($courseid);

        if ($groupid > 0) {
            $group = $DB->get_record('groups', ['id' => $groupid]);
            if ($group && !empty($group->name)) {
                $cfg['courseandyear'] = $group->name;
            }
            // Per-section overrides saved in Settings win over the course-wide config.
            $override = helper::get_group_overrides($courseid, $groupid);
            if ($override) {
                foreach (['courseandyear', 'schedule'] as $f) {
                    if (!empty($override->$f)) {
                        $cfg[$f] = $override->$f;
                    }
                }
            }
        }

        // Signatories: typed names or, when blank, auto-detected from Moodle
        // roles. One precedence rule shared with the dashboard (helper::resolve_signatories).
        $signatories = helper::resolve_signatories($cfg, $courseid, $groupid);
        $cfg['instructor']  = $signatories['instructor']['name'];
        $cfg['depthead']    = $signatories['department_head']['name'];
        $cfg['registrar']   = $signatories['registrar']['name'];
        $cfg['collegedean'] = $signatories['college_dean']['name'];
        $cfg['signatories'] = $signatories;

        $categories = $DB->get_records('local_gradesheet_categories',
            ['courseid' => $courseid], 'sortorder ASC');

        $rows      = [];
        $passcount = 0;
        $failcount = 0;
        $othercount = 0; // Dropped / Incomplete / WP / In Progress — excluded from pass/fail rate.

        foreach ($students as $student) {
            $status = $statusmap[$student->id] ?? '';

            if ($status !== '') {
                // Faculty-set override: report shows dashes and the status label,
                // regardless of any numeric grades recorded so far.
                $othercount++;
                $rows[] = [
                    'idnumber'  => $student->idnumber,
                    'name'      => $student->lastname . ', ' . $student->firstname,
                    'midterm'   => '-',
                    'finals'    => '-',
                    'average'   => '-',
                    'remarks'   => helper::status_label($status),
                    'cattotals' => [],
                    'status'    => $status,
                    'graded'    => 0,
                    'mapped'    => 0,
                    'missing'   => 0,
                ];
                continue;
            }

            $g = helper::compute_student_grades($courseid, $student->id);

            // Students with no mapped/computed data yet show dashes and are
            // excluded from the pass/fail rate.
            if ($g['remarks'] === 'PASSED') {
                $remarks   = 'Passed';
                $passcount++;
            } else if ($g['remarks'] === 'FAILED') {
                $remarks   = 'Failed';
                $failcount++;
            } else {
                $remarks = '-';
            }

            $rows[] = [
                'idnumber'  => $student->idnumber,
                'name'      => $student->lastname . ', ' . $student->firstname,
                'midterm'   => helper::transmute_equiv($g['midterm'], $courseid),
                'finals'    => helper::transmute_equiv($g['finals'], $courseid),
                'average'   => $g['transmuted'],
                'remarks'   => $remarks,
                'cattotals' => $g['cattotals'],
                'status'    => '',
                'graded'    => $g['graded'],
                'mapped'    => $g['mapped'],
                'missing'   => $g['missing'],
            ];
        }

        $total    = $passcount + $failcount; // Pass rate is computed over graded students only.
        $passrate = $total > 0 ? round(($passcount / $total) * 100, 1) : 0;

        return array_merge($cfg, [
            'context'     => $ctx,
            'categories'  => $categories,
            'rows'        => $rows,
            'passcount'   => $passcount,
            'failcount'   => $failcount,
            'othercount'  => $othercount,
            'total'       => $total,
            'passrate'    => $passrate,
        ]);
    }
}