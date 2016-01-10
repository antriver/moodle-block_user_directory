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
 * Helpers for displaying data in the directory.
 *
 * @package    block_user_directory
 * @copyright  Anthony Kuske <www.anthonykuske.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_user_directory\local;

use context_course;
use paging_bar;
use single_select;
use stdClass;

/**
 * Helpers for displaying data in the directory.
 *
 * @package    block_user_directory
 * @copyright  Anthony Kuske <www.anthonykuske.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class display_manager {

    /**
     * @var user_directory
     */
    private $userdirectory;

    /**
     * Constructor.
     *
     * @param user_directory $userdirectory
     */
    public function __construct(user_directory $userdirectory) {
        $this->userdirectory = $userdirectory;
    }

    /**
     * Retun the page title.
     *
     * @return string
     */
    public function get_page_title() {
        return $this->get_string('page_title');
    }

    /**
     * Returns the HTML to display the search box.
     *
     * @throws \coding_exception
     * @return string
     */
    public function get_search_form() {
        $course = $this->userdirectory->get_course();
        $search = $this->userdirectory->search;
        $searchin = $this->userdirectory->searchin;
        $role = $this->userdirectory->role;

        return '<form action="index.php" class="search-form form form-inline">
            <input type="hidden" name="courseid" value="' . $course->id . '" />
            <input type="hidden" name="role" value="' . $role . '" />
            <label for="search">' . get_string('search_for', 'block_user_directory') . '</label>
            <input type="text" id="search" name="search" value="' . s($search) . '" /> ' . get_string(
            'search_in',
            'block_user_directory') . '
            <select name="searchin">
                <option value="" ' . (!$searchin ? 'selected' : '') . '>' . get_string('name') . ', ' . get_string(
            'email') . ', ' . get_string('andor', 'block_user_directory') . ' ' . get_string('department') . '</option>
                <option value="name" ' . ($searchin == 'name' ? 'selected' : '') . '>' . get_string('name') . '</option>
                <option value="email" ' . ($searchin == 'email' ? 'selected' : '') . '>' . get_string('email') . '</option>
                <option value="department" ' . ($searchin == 'department' ? 'selected' : '') . '>'
        . get_string('department')
        . '</option>
            </select>
            <input type="submit" class="btn btn-default" value="' . get_string('search') . '" />
        </form>';
    }

    /**
     * Returns a list of users enroled courses.
     *
     * @param int  $userid
     * @param null $roleid
     *
     * @return array
     */
    private function get_users_courses($userid, $roleid = null) {
        global $DB;

        $values = array(
            $userid
        );

        $sql = 'SELECT DISTINCT
            crs.id,
            crs.fullname
        FROM {role_assignments} ra
        JOIN {context} ct ON ct.id = ra.contextid
        JOIN {course} crs ON crs.id = ct.instanceid
        WHERE ra.userid = ?';

        if ($categorycontext = $this->userdirectory->get_category_context()) {
            $path = $categorycontext->path . '/%';
            $sql .= " AND ct.path LIKE ?";
            $values[] = $path;
        }

        if (!is_null($roleid)) {
            $sql .= ' AND ra.roleid = ? ';
            $values[] = $roleid;
        }

        $sql .= 'ORDER BY crs.fullname';

        return $DB->get_records_sql($sql, $values);
    }

    /**
     * Returns the course selector dropdown HTML.
     *
     * @param int|null $selectedcourseid
     *
     * @return string
     */
    public function get_course_selector($selectedcourseid = null) {
        global $OUTPUT, $USER;

        $courses = $this->get_users_courses($USER->id);
        $courselist = array();

        foreach ($courses as $mycourse) {
            // Add to the filter by courses dropdown.
            $courselist[$mycourse->id] = $mycourse->fullname;
        }

        asort($courselist);

        $directorycourse = get_course($this->userdirectory->get_config('courseid'));

        $courselist = array(
                $directorycourse->id => $directorycourse->fullname
            ) + $courselist;

        // Create the <select>.
        $url = $this->userdirectory->get_current_url('courseid');
        $select = new single_select($url, 'courseid', $courselist, $selectedcourseid, null, 'courseform');

        return $OUTPUT->render($select);
    }

    /**
     * Returns the department (homeroom) selector dropdown HTML.
     *
     * @param int|null $selectedepartment
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_department_selector($selectedepartment = null) {
        global $OUTPUT, $DB;

        // Get departments.
        $departments = array();
        $rows = $DB->get_records_sql(
            'SELECT UPPER(department) AS department FROM {user} GROUP BY UPPER(department) ORDER BY department');
        foreach ($rows as $row) {
            if ($row->department && preg_match('/^\d+/', $row->department)) {
                $departments[$row->department] = $row->department;
            }
        }

        // Sort by digits at the start of homeroom.
        uasort(
            $departments,
            function ($a, $b) {

                $yeara = intval($a);
                $yearb = intval($b);

                $homerooma = substr($a, strlen($yeara));
                $homeroomb = substr($b, strlen($yearb));

                if ($yeara === $yearb && $yeara < 6) {

                    return $homerooma > $homeroomb;
                } else if ($yeara === $yearb) {

                    $order = array('L', 'E', 'A', 'R', 'N', 'S', 'JS', 'SWA');

                    if (!in_array($homeroomb, $order)) {
                        return $homerooma > $homeroomb;
                    } else {
                        return array_search($homerooma, $order) > array_search($homeroomb, $order);
                    }
                } else {
                    return $yeara > $yearb;
                }
            });

        $departments = array(
                0 => get_string('alldepartments', 'block_user_directory')
            ) + $departments;

        // Create the <select>.
        $url = $this->userdirectory->get_current_url('department');
        $select = new single_select($url, 'department', $departments, $selectedepartment, null, 'departmentform');

        return $OUTPUT->render($select);
    }

    /**
     * Returns the role selector HTML.
     *
     * @param int $selectedrole
     *
     * @return string
     */
    public function get_role_selector($selectedrole) {
        global $OUTPUT;

        $visibleroles = $this->userdirectory->get_visible_roles();

        $selectroles = [];
        foreach ($visibleroles as $name => $ids) {
            $selectroles[$name] = $this->get_string("role_{$name}");
        }

        $url = $this->userdirectory->get_current_url('role');
        $html = $OUTPUT->single_select($url, 'role', $selectroles, $selectedrole, null, 'rolesform');

        return $html;
    }

    /**
     * Returns the group selector HTML.
     *
     * @param stdClass $course course object
     *
     * @return mixed void or string depending on $return param
     * @throws \coding_exception
     */
    public function get_group_selector($course) {
        global $USER, $OUTPUT;

        if (!$groupmode = $course->groupmode) {
            return '';
        }

        $context = context_course::instance($course->id);
        $aag = has_capability('moodle/site:accessallgroups', $context);

        if ($groupmode == VISIBLEGROUPS or $aag) {
            $allowedgroups = groups_get_all_groups($course->id, 0, $course->defaultgroupingid);
        } else {
            $allowedgroups = groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid);
        }

        $activegroup = groups_get_course_group($course, true, $allowedgroups);

        $groupsmenu = array();
        if (!$allowedgroups or $groupmode == VISIBLEGROUPS or $aag) {
            $groupsmenu[0] = get_string('allgroups', 'block_user_directory');
        }

        if ($allowedgroups) {
            foreach ($allowedgroups as $group) {
                $groupsmenu[$group->id] = format_string($group->name);
            }
        }

        $url = $this->userdirectory->get_current_url('group');

        if (count($groupsmenu) == 1) {
            $groupname = reset($groupsmenu);
            $output = $groupname;
        } else {
            $select = new single_select($url, 'group', $groupsmenu, $activegroup, null, 'selectgroup');
            $output = $OUTPUT->render($select);
        }

        return $output;
    }

    /**
     * Returns the HTML to show the first and last name letter bars.
     *
     * @param string $firstinitial
     * @param string $lastinitial
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_letter_bar($firstinitial, $lastinitial) {

        $baseurl = $this->userdirectory->get_current_url();
        $baseurl->remove_params(['page', 'sifirst']);

        $strall = get_string('all');
        $alpha = explode(',', get_string('alphabet', 'langconfig'));

        $html = '';

        // Bar of first initials.
        $html .= '<div class="initialbar firstinitial paging"><span>' . get_string('firstname') . ':</span>';
        // All button.
        if (!empty($firstinitial)) {
            $html .= '<a class="btn" href="' . $baseurl->out() . '&amp;sifirst=">' . $strall . '</a>';
        } else {
            $html .= '<a class="btn selected active" href="' . $baseurl->out() . '&amp;sifirst=">' . $strall . '</a>';
        }
        // Show each letter.
        foreach ($alpha as $letter) {
            if ($letter == $firstinitial) {
                $html .= '<a class="btn selected active" href="'
                    . $baseurl->out()
                    . '&amp;sifirst=' . $letter . '">' . $letter . '</a>';
            } else {
                $html .= '<a class="btn" href="' . $baseurl->out() . '&amp;sifirst=' . $letter . '">' . $letter . '</a>';
            }
        }
        $html .= '</div>';

        $baseurl = $this->userdirectory->get_current_url();
        $baseurl->remove_params(['page', 'silast']);

        // Bar of last initials.
        $html .= '<div class="initialbar lastinitial paging"><span>' . get_string('lastname') . ':</span>';
        // All button.
        if (!empty($lastinitial)) {
            $html .= '<a class="btn" href="' . $baseurl->out() . '&amp;silast=">' . $strall . '</a>';
        } else {
            $html .= '<a class="btn selected active" href="' . $baseurl->out() . '&amp;silast=">' . $strall . '</a>';
        }
        // Show each letter.
        foreach ($alpha as $letter) {
            if ($letter == $lastinitial) {
                $html .= '<a class="btn selected active" href="'
                    . $baseurl->out()
                    . '&amp;silast=' . $letter . '">' . $letter . '</a>';
            } else {
                $html .= '<a class="btn" href="' . $baseurl->out() . '&amp;silast=' . $letter . '">' . $letter . '</a>';
            }
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Returns the HTML to show the page number bar.
     *
     * @param int $matchcount
     * @param int $pagestart
     *
     * @return string
     */
    public function get_paging_bar($matchcount, $pagestart) {
        global $OUTPUT;

        $url = $this->userdirectory->get_current_url();
        $url->remove_params(['page']);

        $pagingbar = new paging_bar(
            $matchcount,
            intval($pagestart / $this->userdirectory->perpage),
            $this->userdirectory->perpage,
            $this->userdirectory->get_current_url());
        $pagingbar->pagevar = 'page';
        return $OUTPUT->render($pagingbar);
    }

    /**
     * Shortcut to get_string for the plugin.
     *
     * @param string      $key
     *
     * @param string|null $param
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_string($key, $param = null) {
        return get_string($key, 'block_user_directory', $param);
    }
}
