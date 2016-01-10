<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

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
    function return_course_fullname_from_course_object($course) {
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
 * Category to show courses from
 */

// Load all categories to show in the list
require_once $CFG->dirroot . '/course/externallib.php';
$categories = core_course_external::get_categories(array(), false);
$categoryList = array(
    0 => '[All Cateogries]'
);
foreach ($categories as $category) {
    $categoryList[$category['id']] = $category['name'];
}
asort($categoryList);

$settings->add(
    new admin_setting_configselect(
        'block_user_directory/course_category',
        get_string('settings_course_category_name', 'block_user_directory'),
        get_string('settings_course_category_desc', 'block_user_directory'),
        0,
        $categoryList
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
        $cohortList[$cohort->id] .= ' [' . s($cohort->idnumber) . ']';
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
