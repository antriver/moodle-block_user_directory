<?php

/**
 * Admin settings for user directory
 *
 * @package   block_user_directory
 * @copyright Anthony Kuske <www.anthonykuske.com> and Adam Morris <www.mistermorris.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course to show enrolments for
 */

// Load all courses to show in the selector
$courses = $DB->get_records('course', null, 'fullname', 'id, fullname');

// Make a list of courseid => name pairings

// Moodle loads this page multiple times for some reason
// Hence the function_eixsts check
if (!function_exists('return_course_fullname_from_course_object')) {
    function return_course_fullname_from_course_object($course)
    {
        return trim($course->fullname);
    }
}

$courseList = array_map('return_course_fullname_from_course_object', $courses);

asort($courseList);


$settings->add(
    new admin_setting_configselect(
        'block_user_directory/courseid',
        get_string('settings_courseid_name', 'block_user_directory'),
        get_string('settings_courseid_desc', 'block_user_directory'),
        0,
        $courseList
    )
);

/**
 * User levels
 */

// Get all system-level cohorts
require_once $CFG->dirroot . '/cohort/lib.php';
$systemCtx = context_system::instance();
$cohorts = cohort_get_cohorts($systemCtx->id, 0, 1000000);
$cohortList = array(
    0 => '[Not Set]'
);
foreach ($cohorts['cohorts'] as $cohort) {
    $cohortList[$cohort->id] = $cohort->name;
    if ($cohort->idnumber) {
        $cohortList[$cohort->id] .= ' ['.s($cohort->idnumber).']';
    }
}

// Student
$settings->add(
    new admin_setting_configselect(
        'block_user_directory/student_cohort',
        get_string('settings_student_cohort_name', 'block_user_directory'),
        get_string('settings_student_cohort_desc', 'block_user_directory'),
        0,
        $cohortList
    )
);

// Teacher
$settings->add(
    new admin_setting_configselect(
        'block_user_directory/teacher_cohort',
        get_string('settings_teacher_cohort_name', 'block_user_directory'),
        get_string('settings_teacher_cohort_desc', 'block_user_directory'),
        0,
        $cohortList
    )
);

// Parent
$settings->add(
    new admin_setting_configselect(
        'block_user_directory/parent_cohort',
        get_string('settings_parent_cohort_name', 'block_user_directory'),
        get_string('settings_parent_cohort_desc', 'block_user_directory'),
        0,
        $cohortList
    )
);
