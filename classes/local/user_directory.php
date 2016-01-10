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
 * Data access methods for user directory.
 *
 * @package    block_user_directory
 * @copyright  Anthony Kuske <www.anthonykuske.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_user_directory\local;

use core\event\user_list_viewed;
use Exception;
use flexible_table;
use context_course;
use context_coursecat;
use context_helper;
use moodle_url;
use stdClass;

/**
 * Data access methods for user directory.
 *
 * @package    block_user_directory
 * @copyright  Anthony Kuske <www.anthonykuske.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user_directory {

    /**
     * Show the page with minimal details.
     */
    const MODE_BRIEF = 0;

    /**
     * Show the page with full details.
     */
    const MODE_USERDETAILS = 1;

    /**
     * Curent page number
     *
     * @var int
     */
    public $page;

    /**
     * How many users to display per page
     *
     * @var int
     */
    public $perpage = 20;

    /**
     * @var string
     */
    public $mode;

    /**
     * @var int
     */
    public $accesssince;

    /**
     * Search query
     *
     * @var string
     */
    public $search;

    /**
     * Fields to look for search query in
     *
     * @var string
     */
    public $searchin;

    /**
     * Role filter (Everybody/Students/Teachers/Parents)
     *
     * @var string
     */
    public $role;

    /**
     * Role IDs to filter to
     *
     * @var int[]
     */
    public $roleids;

    /**
     * Course ID to display users from
     *
     * @var int
     */
    public $courseid;

    /**
     * @var \stdClass
     */
    private $course;

    /**
     * Context instance (derived from $this->courseid)
     *
     * @var context_course
     */
    private $context;

    /**
     * TODO: Is this still used?
     *
     * @var string
     */
    public $department;

    /**
     * First name letter filter
     *
     * @var string
     */
    public $sifirst;

    /**
     * Last name letter filter
     *
     * @var string
     */
    public $silast;

    /**
     * @var display_manager
     */
    public $display;

    /**
     * @var bool|null
     */
    public $viewinguserisparent = null;

    /**
     * @var bool|null
     */
    public $viewinguserisstudent = null;

    /**
     * @var bool|null
     */
    public $viewinguseristeacher = null;

    /**
     * Constructor.
     *
     * @throws \required_capability_exception
     */
    public function __construct() {
        global $CFG, $USER;

        require_once($CFG->dirroot . '/cohort/lib.php');
        $this->viewinguserisparent = $this->is_parent($USER);
        $this->viewinguserisstudent = $this->is_student($USER);
        $this->viewinguseristeacher = $this->is_teacher($USER);

        $this->display = new display_manager($this);

        $this->load_url_params();
        $this->load_course();

        require_capability('moodle/course:viewparticipants', $this->context);
    }

    /**
     * Shortcut to get a config valid for the plugin.
     *
     * @param string $key
     *
     * @return mixed
     * @throws Exception
     * @throws \dml_exception
     */
    public function get_config($key) {
        return get_config('block_user_directory', $key);
    }

    /**
     * Load the parameters from the query string into the instance variables.
     *
     * @throws \coding_exception
     */
    private function load_url_params() {
        // Page number.
        $this->page = optional_param('page', 0, PARAM_INT);

        // How many per page.
        $this->perpage = optional_param('perpage', $this->perpage, PARAM_INT);

        // Use the MODE_ constants.
        $this->mode = optional_param('mode', null, PARAM_INT);

        // Filter by last access. -1 = never.
        $this->accesssince = optional_param('accesssince', 0, PARAM_INT);

        // Make sure it is processed with p() or s() when sending to output!
        $this->search = optional_param('search', '', PARAM_RAW);
        $this->searchin = optional_param('searchin', '', PARAM_RAW);

        $this->role = optional_param('role', 'all', PARAM_RAW);

        $visibleroles = $this->get_visible_roles();
        if (!isset($visibleroles[$this->role])) {
            $this->role = array_keys($visibleroles)[0];
        }
        $this->roleids = $visibleroles[$this->role];

        $this->courseid = optional_param('courseid', $this->get_config('courseid'), PARAM_INT);

        $this->department = optional_param('department', '', PARAM_RAW);
        if ($this->role !== 'students') {
            $this->department = '';
        }

        $this->sifirst = optional_param('sifirst', '', PARAM_RAW);
        $this->silast = optional_param('silast', '', PARAM_RAW);
    }

    /**
     * Get the URL for the current filter selection, and page.
     *
     * @param  string $skipkey Don't put this key from the params in the url (useful for making a url for a <select>)
     * @param  array  $additionalparams
     *
     * @return moodle_url
     */
    public function get_current_url($skipkey = null, $additionalparams = array()) {
        $params = [
            'courseid'   => $this->courseid,
            'department' => $this->department,
            'page'       => $this->page,
            'role'       => $this->role,
            'search'     => s($this->search),
            'searchin'   => $this->searchin,
            'sifirst'    => $this->sifirst,
            'silast'     => $this->silast,
        ];

        $params = array_merge($params, $additionalparams);

        if ($skipkey) {
            unset($params[$skipkey]);
        }

        return new moodle_url('/blocks/user_directory/', $params);
    }

    /**
     * Log that a user viewed the page.
     *
     * @throws \coding_exception
     * @return null
     */
    public function log_view() {
        $event = user_list_viewed::create(
            array(
                'objectid' => $this->course->id,
                'courseid' => $this->course->id,
                'context'  => $this->context,
                'other'    => array(
                    'courseshortname' => $this->course->shortname,
                    'coursefullname'  => $this->course->fullname
                )
            ));
        $event->trigger();
    }

    /**
     * Load the course and context the directory is displaying into $this->course
     *
     * @return null
     */
    private function load_course() {
        global $DB;
        $this->course = $DB->get_record('course', array('id' => $this->courseid), '*', \MUST_EXIST);
        $this->context = context_course::instance($this->course->id, \MUST_EXIST);
    }

    /**
     * Returns the current selected course
     *
     * @return object|null
     */
    public function get_course() {
        if (!is_null($this->course)) {
            return $this->course;
        }
        return null;
    }

    /**
     * Return the current selected course context
     *
     * @return context_course|null
     */
    public function get_context() {
        if (!is_null($this->context)) {
            return $this->context;
        }
        return null;
    }

    /**
     * Is the current logged in user a teacher?
     *
     * @param stdClass $user
     *
     * @return bool
     * @throws Exception
     * @throws \dml_exception
     */
    public function is_teacher(stdClass $user) {
        $teachercohortid = (int)get_config('block_user_directory', 'teacher_cohort');
        if ($teachercohortid) {
            return cohort_is_member($teachercohortid, $user->id);
        }
        return false;
    }

    /**
     * Is the current logged in user a student?
     *
     * @param stdClass $user
     *
     * @return bool
     * @throws Exception
     * @throws \dml_exception
     */
    public function is_student(stdClass $user) {
        $studentcohortid = (int)get_config('block_user_directory', 'student_cohort');
        if ($studentcohortid) {
            return cohort_is_member($studentcohortid, $user->id);
        }
        return false;
    }

    /**
     * Is the current logged in user a parent?
     *
     * @param stdClass $user
     *
     * @return bool
     * @throws Exception
     * @throws \dml_exception
     */
    public function is_parent(stdClass $user) {
        $parentcohortid = (int)get_config('block_user_directory', 'parent_cohort');
        if ($parentcohortid) {
            return cohort_is_member($parentcohortid, $user->id);
        }
        return false;
    }

    /**
     * Return the users to populate the table.
     *
     * @param flexible_table $table
     * @param int            $currentgroup
     *
     * @return object
     * @throws Exception
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_users(flexible_table $table, $currentgroup) {
        global $DB, $USER;

        // We are looking for all users with this role assigned in this context or higher.
        $contextlist = $this->context->get_parent_context_ids(true);

        list($esql, $params) = get_enrolled_sql($this->context, null, $currentgroup, true);
        $joins = array("FROM {user} u");
        $wheres = array();

        $extrasql = get_extra_user_fields_sql(
            $this->context,
            'u',
            '',
            array(
                'id',
                'username',
                'firstname',
                'lastname',
                'email',
                'city',
                'country',
                'picture',
                'lang',
                'timezone',
                'maildisplay',
                'imagealt',
                'lastaccess',
            ));

        // Course users.
        $select = "SELECT
            u.id,
            u.username,
            u.firstname,
            u.lastname,
            u.email,
            u.city,
            u.country,
            u.department,
            u.picture,
            u.lang,
            u.timezone,
            u.maildisplay,
            u.imagealt,
            u.idnumber,
            u.alternatename,
            u.middlename,
            u.firstnamephonetic,
            u.lastnamephonetic
        ";

        if ($extrasql) {
            $select .= $extrasql;
        }

        // Enrolled users only.
        $joins[] = "JOIN ($esql) e ON e.id = u.id";

        $params['courseid'] = $this->course->id;

        // Performance hacks - we preload user contexts together with accounts.

        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = \CONTEXT_USER;

        $select .= $ccselect;
        $joins[] = $ccjoin;

        // Limit list to users with some role only.

        $contextlist = implode(',', $contextlist);
        $roleids = implode(',', $this->roleids);
        $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid IN({$roleids}) AND contextid IN ($contextlist))";

        if ($this->department) {
            $wheres[] = "u.department = :department";
            $params['department'] = $this->department;
        }

        /**
         * Remove non-visible users
         * For students: Show only students and teachers
         * For parents: Show only teachers
         * For teachers: Show everybody
         */
        $teachercohortid = (int)get_config('block_user_directory', 'teacher_cohort');
        $studentcohortid = (int)get_config('block_user_directory', 'student_cohort');
        $parentcohortid = (int)get_config('block_user_directory', 'parent_cohort');

        $viewinguserisparent = $this->is_parent($USER);
        $viewinguserisstudent = $this->is_student($USER);
        $viewinguseristeacher = $this->is_teacher($USER);

        if ($viewinguserisparent) {

            $wheres[] = "u.id IN (
                SELECT userid FROM {cohort_members}
                WHERE userid = u.id
                AND cohortid = :teachercohortid
                )";
            $params['teachercohortid'] = $teachercohortid;
        } else if ($viewinguserisstudent) {

            $wheres[] = "u.id IN (
                SELECT userid
                FROM {cohort_members}
                WHERE userid = u.id
                AND (
                    cohortid = :teachercohortid
                    OR cohortid = :studentcohortid
                    )
                )";
            $params['teachercohortid'] = $teachercohortid;
            $params['studentcohortid'] = $studentcohortid;
        }

        $from = implode("\n", $joins);
        if ($wheres) {
            $where = "WHERE " . implode(" AND ", $wheres);
        } else {
            $where = "";
        }

        /**
         * $totalcount is the total of everybody in this section
         * $matchcount (below) is the total of users in this section who match the search
         */
        $totalcount = $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);

        // Perform a search.
        if (!empty($this->search)) {

            $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');

            // What columns to search in.
            switch ($this->searchin) {
                case 'name':
                    // Name is a special case because it's firstname and lastname together.
                    $wheres[] = $DB->sql_like($fullname, ':search', false, false);
                    $params['search'] = "%{$this->search}%";
                    break;

                case 'email':
                case 'department':
                    $wheres[] = $DB->sql_like($this->searchin, ':search', false, false);
                    $params['search'] = "%{$this->search}%";
                    break;

                default:
                    // Default is to search in all 3.
                    $wheres[] = "(" . $DB->sql_like($fullname, ':searchname', false, false) .
                        " OR " . $DB->sql_like('email', ':searchemail', false, false) .
                        " OR " . $DB->sql_like('department', ':searchdepartment', false, false) . ") ";
                    $params['searchname'] = "%{$this->search}%";
                    $params['searchemail'] = "%{$this->search}%";
                    $params['searchdepartment'] = "%{$this->search}%";
                    break;
            }
        }

        list($twhere, $tparams) = $table->get_sql_where();
        if ($twhere) {
            $wheres[] = $twhere;
            $params = array_merge($params, $tparams);
        }

        $from = implode("\n", $joins);
        if ($wheres) {
            $where = "WHERE " . implode(" AND ", $wheres);
        } else {
            $where = "";
        }

        if ($table->get_sql_sort()) {

            /** @var string $sortsql */
            $sortsql = $table->get_sql_sort();

            $sort = ' ORDER BY ' . $sortsql;
        } else {
            $sort = '';
        }

        $matchcount = $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);

        // List of users at the current visible page - paging makes it relatively short.
        $userlist = $DB->get_recordset_sql(
            "$select $from $where $sort",
            $params,
            $table->get_page_start(),
            $table->get_page_size());

        return (object)array(
            'matchcount' => $matchcount,
            'totalcount' => $totalcount,
            'userlist'   => $userlist
        );
    }

    /**
     * Return the category ID that the directory is set to work from
     *
     * @return int
     */
    public function get_category_id() {
        return (int)get_config('block_user_directory', 'course_category');
    }

    /**
     * Return the context for the category that the directory is set to work from
     *
     * @return context_coursecat|null
     */
    public function get_category_context() {
        $categoryid = $this->get_category_id();
        if (!$categoryid) {
            return null;
        }
        return context_coursecat::instance($categoryid);
    }

    /**
     * Returns the list of roles a user is allowed to see in the directory.
     * This is build by getting all the roles defined in the site and filtering it
     * by the viewing user's cohort.
     *
     * @return array[] An array of arrays of roleids
     */
    public function get_visible_roles() {
        // Note: The role with the shortname of 'teacher' is non-editing tacher.
        // The Teacher role has the 'editingteacher' shortname.
        $siteroles = [];
        foreach (get_all_roles() as $role) {
            $siteroles[$role->shortname] = $role->id;
        }

        if ($this->viewinguserisstudent) {

            return [
                'all'     => [
                    $siteroles['editingteacher'],
                    $siteroles['teacher'],
                    $siteroles['student'],
                ],
                'teacher' => [
                    $siteroles['editingteacher'],
                    $siteroles['teacher']
                ],
                'student' => [
                    $siteroles['student']
                ],
            ];
        } else if ($this->viewinguserisparent) {

            return [
                'teacher' => [
                    $siteroles['editingteacher'],
                    $siteroles['teacher']
                ],
            ];
        } else {

            return [
                'all'     => [
                    $siteroles['editingteacher'],
                    $siteroles['teacher'],
                    $siteroles['student'],
                    $siteroles['parent']
                ],
                'teacher' => [
                    $siteroles['editingteacher'],
                    $siteroles['teacher']
                ],
                'student' => [
                    $siteroles['student']
                ],
                'parent'  => [
                    $siteroles['parent']
                ]
            ];
        }
    }

}
