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
 * @package    block_user_directory
 * @copyright  Anthony Kuske <www.anthonykuske.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_user_directory;

use context_course;
use context_system;
use moodle_url;
use paging_bar;
use single_select;

class DisplayManager {
    private $userdirectory;

    public function __construct(UserDirectory $userdirectory) {
        $this->userdirectory = $userdirectory;
    }

    public function getPageTitle() {
        return $this->userdirectory->getString('page_title');
    }

    public function getSearchForm() {
        $course = $this->userdirectory->getCourse();
        $search = $this->userdirectory->search;
        $searchin = $this->userdirectory->searchin;
        $role = $this->userdirectory->role;

        // !Search box above user list
        $searchBox = '<form action="index.php" class="search-form form form-inline">
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
                <option value="department" ' . ($searchin == 'department' ? 'selected' : '') . '>' . get_string('department') . '</option>
            </select>
            <input type="submit" class="btn btn-default" value="' . get_string('search') . '" />
        </form>';
        echo $searchBox;
    }

    private function getUsersCourses($userId, $roleid = null) {
        global $DB;

        $values = array(
            $userId
        );

        $sql = 'SELECT DISTINCT
            crs.id,
            crs.fullname
        FROM {role_assignments} ra
        JOIN {context} ct ON ct.id = ra.contextid
        JOIN {course} crs ON crs.id = ct.instanceid
        WHERE ra.userid = ?';

        if ($categoryCtx = $this->userdirectory->getCategoryContext()) {
            $path = $categoryCtx->path . '/%';
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

    public function getCourseSelector($selectedcourseid = null) {
        global $OUTPUT, $USER, $DB;

        $courses = $this->getUsersCourses($USER->id);
        $courselist = array();

        foreach ($courses as $mycourse) {
            // Add to the filter by courses dropdown
            $courselist[$mycourse->id] = $mycourse->fullname;
        }

        asort($courselist);

        $directoryCourse = get_course($this->userdirectory->getConfig('courseid'));

        $courselist = array(
                $directoryCourse->id => $directoryCourse->fullname
            ) + $courselist;

        // Create the <select>
        $url = $this->userdirectory->getBaseUrl('courseid');
        $select = new single_select($url, 'courseid', $courselist, $selectedcourseid, null, 'courseform');

        return $OUTPUT->render($select);
    }

    public function getDepartmentSelector($selectedepartment = null) {
        global $OUTPUT, $USER, $DB;

        // Get departments
        $departments = array();
        $rows = $DB->get_records_sql(
            'SELECT UPPER(department) AS department FROM {user} GROUP BY UPPER(department) ORDER BY department');
        foreach ($rows as $row) {
            if ($row->department && preg_match('/^\d+/', $row->department)) {
                $departments[$row->department] = $row->department;
            }
        }

        // Sort by digits at the start of homeroom
        uasort(
            $departments,
            function ($a, $b) {

                $yearA = intval($a);
                $yearB = intval($b);

                $hrA = substr($a, strlen($yearA));
                $hrB = substr($b, strlen($yearB));

                if ($yearA === $yearB && $yearA < 6) {

                    return $hrA > $hrB;
                } elseif ($yearA === $yearB) {

                    $order = array('L', 'E', 'A', 'R', 'N', 'S', 'JS', 'SWA');

                    if (!in_array($hrB, $order)) {
                        return $hrA > $hrB;
                    } else {
                        return array_search($hrA, $order) > array_search($hrB, $order);
                    }
                } else {
                    return $yearA > $yearB;
                }
            });

        $departments = array(
                0 => get_string('alldepartments', 'block_user_directory')
            ) + $departments;

        // Create the <select>
        $url = $this->userdirectory->getBaseUrl('department');
        $select = new single_select($url, 'department', $departments, $selectedepartment, null, 'departmentform');

        return $OUTPUT->render($select);
    }

    /**
     * Returns the HTML to display the role selector
     *
     * We build the list of possible roles
     */
    public function getRoleSelector($selectedRole) {
        global $CFG, $OUTPUT;

        $visibleRoles = $this->userdirectory->getVisibleRoles();

        $selectRoles = [];
        foreach ($visibleRoles as $name => $ids) {
            $selectRoles[$name] = $this->getString("role_{$name}");
        }

        $url = $this->userdirectory->getBaseUrl('role');
        $html = $OUTPUT->single_select($url, 'role', $selectRoles, $selectedRole, null, 'rolesform');

        return $html;
    }

    /**
     * Print group menu selector for course level.
     *
     * @category group
     *
     * @param stdClass $course  course object
     * @param mixed    $urlroot return address. Accepts either a string or a moodle_url
     * @param bool     $return  return as string instead of printing
     *
     * @return mixed void or string depending on $return param
     */
    function getGroupSelector($course) {
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

        $url = $this->userdirectory->getBaseUrl('group');

        if (count($groupsmenu) == 1) {
            $groupname = reset($groupsmenu);
            $output = $groupname;
        } else {
            $select = new single_select($url, 'group', $groupsmenu, $activegroup, null, 'selectgroup');
            $output = $OUTPUT->render($select);
        }

        return $output;
    }

    public function getLetterBar($firstinitial, $lastinitial) {
        $baseurl = $this->userdirectory->getBaseUrl();
        $strall = get_string('all');
        $alpha = explode(',', get_string('alphabet', 'langconfig'));

        $html = '';

        // Bar of first initials
        $html .= '<div class="initialbar firstinitial paging"><span>' . get_string('firstname') . ':</span>';
        //'All' button
        if (!empty($firstinitial)) {
            $html .= '<a class="btn" href="' . $baseurl->out() . '&amp;sifirst=">' . $strall . '</a>';
        } else {
            $html .= '<a class="btn selected active" href="' . $baseurl->out() . '&amp;sifirst=">' . $strall . '</a>';
        }
        //Show each letter
        foreach ($alpha as $letter) {
            if ($letter == $firstinitial) {
                $html .= '<a class="btn selected active" href="' . $baseurl->out() . '&amp;sifirst=' . $letter . '">' . $letter . '</a>';
            } else {
                $html .= '<a class="btn" href="' . $baseurl->out() . '&amp;sifirst=' . $letter . '">' . $letter . '</a>';
            }
        }
        $html .= '</div>';

        // Bar of last initials
        $html .= '<div class="initialbar lastinitial paging"><span>' . get_string('lastname') . ':</span>';
        //'All' button
        if (!empty($lastinitial)) {
            $html .= '<a class="btn" href="' . $baseurl->out() . '&amp;silast=">' . $strall . '</a>';
        } else {
            $html .= '<a class="btn selected active" href="' . $baseurl->out() . '&amp;silast=">' . $strall . '</a>';
        }
        //Show each letter
        foreach ($alpha as $letter) {
            if ($letter == $lastinitial) {
                $html .= '<a class="btn selected active" href="' . $baseurl->out() . '&amp;silast=' . $letter . '">' . $letter . '</a>';
            } else {
                $html .= '<a class="btn" href="' . $baseurl->out() . '&amp;silast=' . $letter . '">' . $letter . '</a>';
            }
        }
        $html .= '</div>';

        return $html;
    }

    public function getPagingBar($matchcount, $pagestart) {
        global $OUTPUT;

        // Bar of page numbers
        $pagingbar = new paging_bar(
            $matchcount,
            intval($pagestart / $this->userdirectory->perpage),
            $this->userdirectory->perpage,
            $this->userdirectory->getBaseUrl());
        $pagingbar->pagevar = 'spage';
        return $OUTPUT->render($pagingbar);
    }

    public function getString($key) {
        return get_string($key, 'block_user_directory');
    }
}
