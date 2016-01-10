<?php

/**
 * @package    block_user_directory
 * @copyright  Anthony Kuske <www.anthonykuske.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_user_directory;

use Exception;
use PDO;
use context_course;
use context_coursecat;
use context_helper;
use moodle_url;

class UserDirectory
{
    const USER_SMALL_CLASS = 20;   // Below this is considered small.
    const USER_LARGE_CLASS = 200;  // Above this is considered large.
    const DEFAULT_PAGE_SIZE = 20;
    const SHOW_ALL_PAGE_SIZE = 5000;
    const MODE_BRIEF = 0;
    const MODE_USERDETAILS = 1;

    // URL parameters
    public $page;
    public $perpage = self::DEFAULT_PAGE_SIZE;
    public $mode;
    public $accesssince;
    public $search;
    public $searchin;
    public $role;
    public $roleids;
    public $courseid;
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

    public $display = null;

    private $course = null;
    private $context = null;

    public $viewinguserisparent = null;
    public $viewinguserisstudent = null;
    public $viewinguseristeacher = null;

    function __construct()
    {
        global $CFG, $USER;

        require_once $CFG->dirroot . '/cohort/lib.php';
        $this->viewinguserisparent = $this->isParent($USER);
        $this->viewinguserisstudent = $this->isStudent($USER);
        $this->viewinguseristeacher = $this->isTeacher($USER);

        $this->display = new DisplayManager($this);

        $this->loadUrlParams();
        $this->loadCourse();

        require_capability('moodle/course:viewparticipants', $this->context);
    }

    public function getString($key, $param = null)
    {
        return get_string($key, 'block_user_directory', $param);
    }

    public function getConfig($key)
    {
        return get_config('block_user_directory', $key);
    }

    private function loadUrlParams()
    {
        // Page number
        $this->page = optional_param('page', 0, PARAM_INT);

        // How many per page
        $this->perpage = optional_param('perpage', $this->perpage, PARAM_INT);

        // Use the MODE_ constants.
        $this->mode = optional_param('mode', null, PARAM_INT);

        // Filter by last access. -1 = never.
        $this->accesssince = optional_param('accesssince', 0, PARAM_INT);

        // Make sure it is processed with p() or s() when sending to output!
        $this->search = optional_param('search', '',  PARAM_RAW);
        $this->searchin = optional_param('searchin', '',  PARAM_RAW);

        $this->role = optional_param('role', 'all',  PARAM_RAW);

        $visibleRoles = $this->getVisibleRoles();
        if (!isset($visibleRoles[$this->role])) {
            $this->role = array_keys($visibleRoles)[0];
        }
        $this->roleids = $visibleRoles[$this->role];

        $this->courseid = optional_param('courseid', $this->getConfig('courseid'), PARAM_INT);

        $this->department = optional_param('department', '',  PARAM_RAW);
        if ($this->role !== 'students') {
            $this->department = '';
        }

        $this->sifirst = optional_param('sifirst', '', PARAM_RAW);
        $this->silast = optional_param('silast', '', PARAM_RAW);
    }

    /**
     * Get the URL for the current filter selection. (Resets page and letter filters to the default/all)
     * @param  string $skipkey          Don't put this key from the params in the url (useful for making a url for a <select>)
     * @param  array  $additionalparams
     * @return moodle_url
     */
    public function getBaseUrl($skipkey = null, $additionalparams = array())
    {
        $params = [
            'courseid' => $this->courseid,
            'department' => $this->department,
            'page' => '',
            'role' => $this->role,
            'search' => s($this->search),
            'searchin' => $this->searchin,
            'sifirst' => $this->sifirst,
            'silast' => $this->silast,
        ];

        $params = array_merge($params, $additionalparams);

        if ($skipkey) {
            unset($params[$skipkey]);
        }

        return new moodle_url('/blocks/user_directory/', $params);
    }



    public function logView()
    {
        // Log a view
        $event = \core\event\user_list_viewed::create(array(
            'objectid' => $this->course->id,
            'courseid' => $this->course->id,
            'context' => $this->context,
            'other' => array(
                'courseshortname' => $this->course->shortname,
                'coursefullname' => $this->course->fullname
            )
        ));
        $event->trigger();
    }

    private function loadCourse()
    {
        global $DB;
        $this->course = $DB->get_record('course', array('id' => $this->courseid), '*', \MUST_EXIST);
        $this->context = context_course::instance($this->course->id, \MUST_EXIST);
    }

    /**
     * Returns the current selected course
     */
    public function getCourse()
    {
        if (!is_null($this->course)) {
            return $this->course;
        }
    }

    public function getContext()
    {
        if (!is_null($this->context)) {
            return $this->context;
        }
    }

    public function getDirectoryCourse()
    {
        return $DB->get_record('course', array('id' => $this->getConfig('courseid')), '*', \MUST_EXIST);
    }

    public function getDirectoryCourseContext()
    {
        return $this->context = context_course::instance($this->getConfig('courseid'), \MUST_EXIST);
    }

    public function isTeacher($user)
    {
        $teacherCohortId = (int)get_config('block_user_directory', 'teacher_cohort');
        if ($teacherCohortId) {
            return cohort_is_member($teacherCohortId, $user->id);
        }
    }

    public function isStudent($user)
    {
        $studentCohortId = (int)get_config('block_user_directory', 'student_cohort');
        if ($studentCohortId) {
            return cohort_is_member($studentCohortId, $user->id);
        }
    }

    public function isParent($user)
    {
        $parentCohortId = (int)get_config('block_user_directory', 'parent_cohort');
        if ($parentCohortId) {
            return cohort_is_member($parentCohortId, $user->id);
        }
    }

    public function getUsers($table, $currentgroup)
    {
        global $DB, $USER;

        // We are looking for all users with this role assigned in this context or higher
        $contextlist = $this->context->get_parent_context_ids(true);

        list($esql, $params) = get_enrolled_sql($this->context, null, $currentgroup, true);
        $joins = array("FROM {user} u");
        $wheres = array();

        $extrasql = get_extra_user_fields_sql($this->context, 'u', '', array(
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

        // Course users
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

        $joins[] = "JOIN ($esql) e ON e.id = u.id"; // course enrolled users only

        //$joins[] = "LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid)";

        // not everybody accessed course yet
        $params['courseid'] = $this->course->id;

        // performance hacks - we preload user contexts together with accounts

        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = \CONTEXT_USER;

        $select .= $ccselect;
        $joins[] = $ccjoin;

        // limit list to users with some role only

        $contextlist = implode(',', $contextlist);
        $roleids = implode(',', $this->roleids);
        $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid IN({$roleids}) AND contextid IN ($contextlist))";

        if ($this->department) {
            $wheres[] = "u.department = :department";
            $params['department'] = $this->department;
        }

        // Remove non-visible users
        // For students: Remove everybody but students and teachers
        // For parents: Remove everybody but teachers
        $teacherCohortId = (int)get_config('block_user_directory', 'teacher_cohort');
        $studentCohortId = (int)get_config('block_user_directory', 'student_cohort');
        $parentCohortId = (int)get_config('block_user_directory', 'parent_cohort');

        $viewinguserisparent = $this->isParent($USER);
        $viewinguserisstudent = $this->isStudent($USER);
        $viewinguseristeacher = $this->isTeacher($USER);

        if ($viewinguserisparent) {

            $wheres[] = "u.id IN (SELECT userid FROM {cohort_members} WHERE userid = u.id AND cohortid = :teachercohortid)";
            $params['teachercohortid'] = $teacherCohortId;

        } elseif ($viewinguserisstudent) {

            $wheres[] = "u.id IN (SELECT userid FROM {cohort_members} WHERE userid = u.id AND (cohortid = :teachercohortid OR cohortid = :studentcohortid))";
            $params['teachercohortid'] = $teacherCohortId;
            $params['studentcohortid'] = $studentCohortId;

        }

        $from = implode("\n", $joins);
        if ($wheres) {
            $where = "WHERE " . implode(" AND ", $wheres);
        } else {
            $where = "";
        }

        // $totalcount is the total of everybody in this section
        // $matchcount (below) is the total of users in this section who match the search
        $totalcount = $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);

        // Perform a search.
        if (!empty($this->search)) {

            $fullname = $DB->sql_fullname('u.firstname','u.lastname');

            //What columns to search in...
            switch ($this->searchin) {
                case 'name':
                    //Name is a special case because it's firstname and lastname together
                    $wheres[] = $DB->sql_like($fullname, ':search', false, false);
                    $params['search'] = "%{$this->search}%";
                break;

                case 'email':
                case 'department':
                    //Add 'where' to query
                    $wheres[] = $DB->sql_like( $searchin , ':search' , false, false);
                    $params['search'] = "%{$this->search}%";
                break;

                default:
                    $searchin = false;
                    //Default is to search in all 3
                    $wheres[] = "(". $DB->sql_like($fullname, ':searchname', false, false) .
                        " OR ". $DB->sql_like('email', ':searchemail', false, false) .
                        " OR ". $DB->sql_like('department', ':searchdepartment', false, false) .") ";
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
            $sort = ' ORDER BY '.$table->get_sql_sort();
        } else {
            $sort = '';
        }

        $matchcount = $DB->count_records_sql("SELECT COUNT(u.id) $from $where", $params);

        // list of users at the current visible page - paging makes it relatively short
        $userlist = $DB->get_recordset_sql("$select $from $where $sort", $params, $table->get_page_start(), $table->get_page_size());

        return (object)array(
            'matchcount' => $matchcount,
            'totalcount' => $totalcount,
            'userlist' => $userlist
        );

    }

    /**
     * Return the category ID that the directory is set to work from
     */
    public function getCategoryId()
    {
        return (int)get_config('block_user_directory', 'course_category');
    }

    public function getCategoryContext()
    {
        // Limit to a certain category?
        $categoryId = $this->getCategoryId();
        if (!$categoryId) {
            return null;
        }
        return context_coursecat::instance($categoryId);
    }

    public function getCategoryContextPath()
    {
        if ($categoryContext = $this->getCategoryContext()) {
            return $categoryContext->path;
        }
        return null;
    }


    public function getUsersChildren($userId)
    {
        global $DB;
        $usercontexts = $DB->get_records_sql("SELECT c.instanceid, c.instanceid, u.id AS userid, u.firstname, u.lastname
         FROM {role_assignments} ra, {context} c, {user} u
         WHERE ra.userid = ?
              AND ra.contextid = c.id
              AND c.instanceid = u.id
              AND c.contextlevel = " . \CONTEXT_USER, array($userId));
        return $usercontexts;
    }

    /**
     * Returns the list of roles a user is allowed to see in the directory.
     * This is build by getting all the roles defined in the site and filtering it
     * by the viewing user's cohort.
     *
     * @return array[] An array of arrays of roleids
     */
    public function getVisibleRoles()
    {
        // Note: The role with the shortname of 'teacher' is non-editing tacher
        // The Teacher role has the 'editingteacher' shortname
        $siteRoles = [];
        foreach (get_all_roles() as $role) {
            $siteRoles[$role->shortname] = $role->id;
        }

        if ($this->viewinguserisstudent) {

            return [
                'all' => [
                    $siteRoles['editingteacher'],
                    $siteRoles['teacher'],
                    $siteRoles['student'],
                ],
                'teacher' => [
                    $siteRoles['editingteacher'],
                    $siteRoles['teacher']
                ],
                'student' => [
                    $siteRoles['student']
                ],
            ];

        } elseif ($this->viewinguserisparent) {

            return [
                'teacher' => [
                    $siteRoles['editingteacher'],
                    $siteRoles['teacher']
                ],
            ];

        } else {

            return [
                'all' => [
                    $siteRoles['editingteacher'],
                    $siteRoles['teacher'],
                    $siteRoles['student'],
                    $siteRoles['parent']
                ],
                'teacher' => [
                    $siteRoles['editingteacher'],
                    $siteRoles['teacher']
                ],
                'student' => [
                    $siteRoles['student']
                ],
                'parent' => [
                    $siteRoles['parent']
                ]
            ];

        }

    }

}
