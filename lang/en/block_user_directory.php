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
 * English language strings for user directory
 *
 * @package   block_user_directory
 * @copyright Anthony Kuske <www.anthonykuske.com> and Adam Morris <www.mistermorris.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'User Directory';
$string['page_title'] = 'Directory';

// Admin settings.
$string['settings_courseid_name'] = 'Directory Course';
$string['settings_courseid_desc'] = 'Select a course that will be used for the directory.
Enrol users into this course to display them in the directory.';

$string['settings_course_category_name'] = 'Course Category';
$string['settings_course_category_desc'] = 'Only show courses from this category in the directory.';

// Capabilities.
$string['user_directory:addinstance'] = 'Add a user directory block';
$string['user_directory:myaddinstance'] = 'Add a user directory block to "my moodle"';

$string['andor'] = 'and/or';

$string['allcourses'] = '[All Courses]';
$string['allgroups'] = '[Any Group]';
$string['alldepartments'] = '[Any Homeroom]';

$string['filter_before_role'] = 'Show ';
$string['filter_before_department'] = ' from ';
$string['filter_before_course'] = ' in ';

$string['search_for'] = 'Search for ';
$string['search_in'] = ' in ';

$string['settings_student_cohort_name'] = 'Student Cohort';
$string['settings_student_cohort_desc'] = 'Users in this cohort will have be considered students in the directory';

$string['settings_teacher_cohort_name'] = 'Teacher Cohort';
$string['settings_teacher_cohort_desc'] = 'Users in this cohort will have be considered teachers in the directory';

$string['settings_parent_cohort_name'] = 'Parent Cohort';
$string['settings_parent_cohort_desc'] = 'Users in this cohort will have be considered parents in the directory';

$string['role_all'] = 'Everybody';
$string['role_teacher'] = 'Teachers';
$string['role_parent'] = 'Parents';
$string['role_student'] = 'Students';
