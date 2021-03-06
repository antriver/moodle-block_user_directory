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
 * @var stdClass        $CFG
 * @var moodle_database $DB
 *
 * @package   block_user_directory
 * @copyright Anthony Kuske <www.anthonykuske.com> and Adam Morris <www.mistermorris.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Course to show enrolments for
 */

// Load all courses to show in the selector.
$courses = $DB->get_records('course', null, 'fullname', 'id, fullname');

$courselist = array_map(
    function ($course) {
        return trim($course->fullname);
    },
    $courses
);

$settings->add(
    new admin_setting_configselect(
        'block_user_directory/courseid',
        get_string('settings_courseid_name', 'block_user_directory'),
        get_string('settings_courseid_desc', 'block_user_directory'),
        0,
        $courselist
    )
);

/**
 * Category to show courses from
 */

// Load all categories to show in the list.
$categories = $DB->get_records('course_categories', null, 'name', 'id, name');
$categorylist = array(
    0 => '[All Categories]'
);
foreach ($categories as $category) {
    $categorylist[$category->id] = $category->name;
}

$settings->add(
    new admin_setting_configselect(
        'block_user_directory/course_category',
        get_string('settings_course_category_name', 'block_user_directory'),
        get_string('settings_course_category_desc', 'block_user_directory'),
        0,
        $categorylist
    )
);

/**
 * User levels
 */

// Get all system-level cohorts.
$cohorts = $DB->get_records('cohort', ['contextid' => context_system::instance()->id], 'name', 'id, name, idnumber');
$cohortlist = array(
    0 => '[Not Set]'
);
foreach ($cohorts as $cohort) {
    $cohortlist[$cohort->id] = $cohort->name;
    if ($cohort->idnumber) {
        $cohortlist[$cohort->id] .= ' (' . s($cohort->idnumber) . ')';
    }
}

$settings->add(
    new admin_setting_configselect(
        'block_user_directory/student_cohort',
        get_string('settings_student_cohort_name', 'block_user_directory'),
        get_string('settings_student_cohort_desc', 'block_user_directory'),
        0,
        $cohortlist
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_user_directory/teacher_cohort',
        get_string('settings_teacher_cohort_name', 'block_user_directory'),
        get_string('settings_teacher_cohort_desc', 'block_user_directory'),
        0,
        $cohortlist
    )
);

$settings->add(
    new admin_setting_configselect(
        'block_user_directory/parent_cohort',
        get_string('settings_parent_cohort_name', 'block_user_directory'),
        get_string('settings_parent_cohort_desc', 'block_user_directory'),
        0,
        $cohortlist
    )
);
