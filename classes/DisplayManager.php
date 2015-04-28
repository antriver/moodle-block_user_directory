<?php

/**
 * @package    block_user_directory
 * @copyright  Anthony Kuske <www.anthonykuske.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_user_directory;

use context_course;
use moodle_url;
use paging_bar;
use single_select;

class DisplayManager
{
    private $userDirectory;

    public function __construct(UserDirectory $userDirectory)
    {
        $this->userDirectory = $userDirectory;
    }

    public function getPageTitle()
    {
        return $this->userDirectory->getString('page_title');
    }

    public function getSearchForm()
    {
        $course = $this->userDirectory->getCourse();
        $search = $this->userDirectory->search;
        $searchin = $this->userDirectory->searchin;
        $roleid = $this->userDirectory->roleid;

        // !Search box above user list
        $searchBox = '<form action="index.php" class="search-form form form-inline">
            <input type="hidden" name="courseid" value="' . $course->id . '" />
            <input type="hidden" name="roleid" value="' . $roleid . '" />
            <label for="search">' . get_string('search_for', 'block_user_directory'). '</label>
            <input type="text" id="search" name="search" value="' . s($search) . '" /> ' . get_string('search_in', 'block_user_directory') . '
            <select name="searchin">
                <option value="" ' . (!$searchin ? 'selected' : '') . '>' . get_string('name') .', ' . get_string('email') . ', ' . get_string('andor', 'block_user_directory') . ' ' . get_string('department') .'</option>
                <option value="name" ' . ($searchin == 'name' ? 'selected' : '') . '>' . get_string('name') . '</option>
                <option value="email" ' . ($searchin == 'email' ? 'selected' : '') . '>' . get_string('email') . '</option>
                <option value="department" ' . ($searchin == 'department' ? 'selected' : '') . '>' . get_string('department') . '</option>
            </select>
            <input type="submit" class="btn btn-default" value="' . get_string('search') . '" />
        </form>';
        echo $searchBox;
    }

    public function getCourseSelector($selectedCourseId = null)
    {
        global $OUTPUT;

        // TODO: Add setting to limit to courses in a certain category

        //Get the IDs of all coures in the Teachng & Learning category
        // FIXME: NO!
        $teachinglearning = get_teaching_and_learning_ids();

        // Get the courses a user is enroled in
        $mycourses = enrol_get_my_courses();
        if (empty($mycourses)) {
            return false;
        }

        $courselist = array();

        //Only show courses user is enrolled in from the Teaching & Learning menu
        foreach ($mycourses as $mycourse) {

            if (!isset($teachinglearning[$mycourse->id])) {
                //Not a T&L course
                continue;
            }

            // Add to the filter by courses dropdown
            $courselist[$mycourse->id] = $mycourse->fullname;
        }

        asort($courselist);
        $courselist = array($this->userDirectory->getConfig('courseid') => get_string('allcourses', 'block_user_directory')) + $courselist;

        // Create the <select>
        $filter_by_course_url = new moodle_url('/blocks/user_directory/?roleid=' . $this->userDirectory->roleid . '&sifirst=&silast=');
        $select = new single_select($filter_by_course_url, 'courseid', $courselist, $selectedCourseId, null, 'courseform');

        return $OUTPUT->render($select);

    }

    /**
     * Returns the HTML to display the role selector
     */
    public function getRoleSelector($selectedRoleId)
    {
        global $CFG, $OUTPUT;
        $context = $this->userDirectory->getContext();

        $rolenamesurl = new moodle_url('/blocks/user_directory/?courseid=' . $this->userDirectory->courseid . '&sifirst=&silast=');

        $rolenames = role_fix_names(get_profile_roles($context), $context, null, true);

        asort($rolenames);
        $rolenames = array(0 => get_string('allroles', 'block_user_directory')) + $rolenames;

        // Make sure other roles may not be selected by any means.
        if (empty($rolenames[$selectedRoleId])) {
            print_error('noparticipants');
        }

        // No roles to display yet?
        // frontpage course is an exception, on the front page course we should display all users.
        if (empty($rolenames) && !$isfrontpage) {
            if (has_capability('moodle/role:assign', $context)) {
                redirect('/'.$CFG->admin.'/roles/assign.php?contextid='.$context->id);
            } else {
                print_error('noparticipants');
            }
        }

        // If there are multiple roles in the course, then show a drop down menu for switching
        if (count($rolenames) > 1) {

            $html = $OUTPUT->single_select($rolenamesurl, 'roleid', $rolenames, $selectedRoleId, null, 'rolesform');

        } else if (count($rolenames) == 1) {
            // when all users with the same role - print its name
            $rolename = reset($rolenames);
            $html = $rolename;
        }

        return $html;
    }

    /**
     * Print group menu selector for course level.
     *
     * @category group
     * @param stdClass $course course object
     * @param mixed $urlroot return address. Accepts either a string or a moodle_url
     * @param bool $return return as string instead of printing
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

        $url = new moodle_url('/blocks/user_directory/?roleid=' . $this->userDirectory->roleid . '&courseid=' . $this->userDirectory->courseid . '&sifirst=&silast=');

        if (count($groupsmenu) == 1) {
            $groupname = reset($groupsmenu);
            $output = $groupname;
        } else {
            $select = new single_select($url, 'group', $groupsmenu, $activegroup, null, 'selectgroup');
            $output = $OUTPUT->render($select);
        }

        return $output;
    }

    public function getLetterBar($firstinitial, $lastinitial)
    {
        $baseurl = $this->userDirectory->getBaseUrl();
        $strall = get_string('all');
        $alpha  = explode(',', get_string('alphabet', 'langconfig'));

        $html = '';

        // Bar of first initials
        $html .= '<div class="initialbar firstinitial paging"><span>' . get_string('firstname') . ':</span>';
            //'All' button
            if(!empty($firstinitial)) {
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
            if(!empty($lastinitial)) {
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

    public function getPagingBar($matchcount, $pagestart)
    {
        global $OUTPUT;

        // Bar of page numbers
        $pagingbar = new paging_bar($matchcount, intval($pagestart / $this->userDirectory->perpage), $this->userDirectory->perpage, $this->userDirectory->getBaseUrl());
        $pagingbar->pagevar = 'spage';
        return $OUTPUT->render($pagingbar);
    }

}
