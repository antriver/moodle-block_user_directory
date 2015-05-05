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
    public $roleid;
    public $courseid;

    public $display = null;

    private $course = null;
    private $context = null;

    function __construct()
    {
        global $CFG;

        $this->display = new DisplayManager($this);

        $this->loadUrlParams();
        $this->loadCourse();

        require_capability('moodle/course:viewparticipants', $this->context);

        require_once $CFG->dirroot . '/cohort/lib.php';
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
        $this->page = optional_param('page', 0, \PARAM_INT);

        // How many per page
        $this->perpage = optional_param('perpage', $this->perpage, \PARAM_INT);

        // Use the MODE_ constants.
        $this->mode = optional_param('mode', null, \PARAM_INT);

        // Filter by last access. -1 = never.
        $this->accesssince = optional_param('accesssince', 0, \PARAM_INT);

        // Make sure it is processed with p() or s() when sending to output!
        $this->search = optional_param('search', '', \PARAM_RAW);
        $this->searchin = optional_param('searchin', '', \PARAM_RAW);

        // Optional roleid, 0 means all enrolled users (or all on the frontpage).
        $this->roleid = optional_param('roleid', 0, \PARAM_INT);

        // One of this or.
        //$this->contextid = optional_param('contextid', 0, \PARAM_INT);

        // This are required.
        $this->courseid = optional_param('courseid', $this->getConfig('courseid'), PARAM_INT);
    }

    public function getUrlParams()
    {
        return array(
            'courseid' => $this->courseid,
            'roleid' => $this->roleid,
            'search' => $this->search,
            'perpage' => $this->perpage,

            'page' => $this->page,
        );
    }

    public function getBaseUrl()
    {
        return new moodle_url('/blocks/user_directory/', array(
            'courseid' => $this->courseid,
            'roleid' => $this->roleid,
            'search' => s($this->search),
            'perpage' => $this->perpage,
        ));
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
        global $DB;

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
        if ($this->roleid) {
            $contextlist = implode(',', $contextlist);
            $wheres[] = "u.id IN (SELECT userid FROM {role_assignments} WHERE roleid = :roleid AND contextid IN ($contextlist))";
            $params['roleid'] = $this->roleid;
        }

        $from = implode("\n", $joins);
        if ($wheres) {
            $where = "WHERE " . implode(" AND ", $wheres);
        } else {
            $where = "";
        }

        //$totalcount is the total of everybody in this section
        //$matchcount (below) is the total of users in this section who match the search
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

}
