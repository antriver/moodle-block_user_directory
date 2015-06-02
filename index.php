<?php

/**
 * Main user directory page.
 * Modified from /user/index.php
 *
 * @package   block_user_directory
 * @copyright Anthony Kuske <www.anthonykuske.com> and Adam Morris <www.mistermorris.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once '../../config.php';
require_once $CFG->libdir . '/tablelib.php';
require_once $CFG->libdir . '/filelib.php';


$userDirectory = new \block_user_directory\UserDirectory();
$course = $userDirectory->getCourse();
$context = $userDirectory->getContext();
$systemcontext = context_system::instance();

/**
 * Load permissions
 */
$bulkoperations = has_capability('moodle/course:bulkmessaging', $context);
$viewinguserisparent = $userDirectory->isParent($USER);
$viewinguserisstudent = $userDirectory->isStudent($USER);
$viewinguseristeacher = $userDirectory->isTeacher($USER);
if ($viewinguserisparent) {
    $viewinguserschildren = $userDirectory->getUsersChildren($USER->id);
}

// Check to see if groups are being used in this course
// and if so, set $currentgroup to reflect the current group
$groupmode = groups_get_course_groupmode($course); // Groups are being used
$currentgroup = groups_get_course_group($course, true);
if (!$currentgroup) {
    // To make some other functions work better later
    $currentgroup = null;
}
$isseparategroups = ($course->groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context));



/**
 * Begin output
 */
require_login($course);

$PAGE->set_url('/blocks/user_directory/', $userDirectory->getUrlParams());
$PAGE->set_title($userDirectory->display->getPageTitle());
$PAGE->set_heading($userDirectory->display->getPageTitle());

$PAGE->add_body_class('user-directory');
$PAGE->requires->css('/blocks/user_directory/assets/css/block_user_directory.css');

echo $OUTPUT->header();

if ($isseparategroups and !$currentgroup) {
    // The user is not in the group so show message and exit
    echo $OUTPUT->heading(get_string('notingroup'));
    echo $OUTPUT->footer();
    exit;
}

// Should use this variable so that we don't break stuff every time a variable is added or changed.
$baseurl = $userDirectory->getBaseUrl();



/// setting up tags
if ($course->id == SITEID) {
    $filtertype = 'site';
} else if ($course->id && !$currentgroup) {
    $filtertype = 'course';
    $filterselect = $course->id;
} else {
    $filtertype = 'group';
    $filterselect = $currentgroup;
}



// Get the hidden field list
if (has_capability('moodle/course:viewhiddenuserfields', $context)) {
    $hiddenfields = array();  // teachers and admins are allowed to see everything
} else {
    $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
}

/**
 * Filters
 */
echo '<h2 class="user-directory-title text-center">';

    echo get_string('filter_role', 'block_user_directory');
    echo $userDirectory->display->getRoleSelector($userDirectory->roleid);

    if ($courseSelectorHtml = $userDirectory->display->getCourseSelector($userDirectory->courseid)) {
        echo get_string('filter_role_course_separator', 'block_user_directory');
        echo $courseSelectorHtml;

        if ($groupSelectorHtml = $userDirectory->display->getGroupSelector($course)) {
            echo '(' . $groupSelectorHtml . ')';
        }
    }

echo '</h2>';

echo $userDirectory->display->getSearchForm();

echo '<hr/>';

if ($currentgroup and (!$isseparategroups or has_capability('moodle/site:accessallgroups', $context))) {    /// Display info about the group
    if ($group = groups_get_group($currentgroup)) {
        if (!empty($group->description) or (!empty($group->picture) and empty($group->hidepicture))) {
            $groupinfotable = new html_table();
            $groupinfotable->attributes['class'] = 'groupinfobox';
            $picturecell = new html_table_cell();
            $picturecell->attributes['class'] = 'left side picture';
            $picturecell->text = print_group_picture($group, $course->id, true, true, false);

            $contentcell = new html_table_cell();
            $contentcell->attributes['class'] = 'content';

            $contentheading = $group->name;
            if (has_capability('moodle/course:managegroups', $context)) {
                $aurl = new moodle_url('/group/group.php', array('id' => $group->id, 'courseid' => $group->courseid));
                $contentheading .= '&nbsp;' . $OUTPUT->action_icon($aurl, new pix_icon('t/edit', get_string('editgroupprofile')));
            }

            $group->description = file_rewrite_pluginfile_urls($group->description, 'pluginfile.php', $context->id, 'group', 'description', $group->id);
            if (!isset($group->descriptionformat)) {
                $group->descriptionformat = FORMAT_MOODLE;
            }
            $options = array('overflowdiv'=>true);
            $contentcell->text = $OUTPUT->heading($contentheading, 3) . format_text($group->description, $group->descriptionformat, $options);
            $groupinfotable->data[] = new html_table_row(array($picturecell, $contentcell));
            echo html_writer::table($groupinfotable);
        }
    }
}

$mode = \block_user_directory\UserDirectory::MODE_USERDETAILS;

/// Define a table showing a list of users in the current role selection
$tablecolumns = array();
$tableheaders = array();
if ($bulkoperations && $mode === \block_user_directory\UserDirectory::MODE_BRIEF) {
    $tablecolumns[] = 'select';
    $tableheaders[] = get_string('select');
}
$tablecolumns[] = 'userpic';
$tablecolumns[] = 'fullname';

$extrafields = get_extra_user_fields($context);
$skippedextrafields = array(
    'email',
    'department'
);


$tableheaders[] = get_string('userpic');
$tableheaders[] = get_string('fullnameuser');

if ($mode === \block_user_directory\UserDirectory::MODE_BRIEF) {
    foreach ($extrafields as $field) {
        $tablecolumns[] = $field;
        $tableheaders[] = get_user_field_name($field);
    }
}
if ($mode === \block_user_directory\UserDirectory::MODE_BRIEF && !isset($hiddenfields['city'])) {
    $tablecolumns[] = 'city';
    $tableheaders[] = get_string('city');
}
if ($mode === \block_user_directory\UserDirectory::MODE_BRIEF && !isset($hiddenfields['country'])) {
    $tablecolumns[] = 'country';
    $tableheaders[] = get_string('country');
}
if (!isset($hiddenfields['lastaccess'])) {
    $tablecolumns[] = 'lastaccess';
    $tableheaders[] = get_string('lastaccess');
}

if ($bulkoperations && $mode === \block_user_directory\UserDirectory::MODE_USERDETAILS) {
    $tablecolumns[] = 'select';
    $tableheaders[] = get_string('select');
}

$table = new flexible_table('user-index-participants-'.$course->id);
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($baseurl->out());

if (!isset($hiddenfields['lastaccess'])) {
    $table->sortable(true, 'lastaccess', SORT_DESC);
} else {
    $table->sortable(true, 'firstname', SORT_ASC);
}

$table->no_sorting('roles');
$table->no_sorting('groups');
$table->no_sorting('groupings');
$table->no_sorting('select');

$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'participants');
$table->set_attribute('class', 'generaltable generalbox');

$table->set_control_variables(array(
    TABLE_VAR_SORT => 'ssort',
    TABLE_VAR_HIDE => 'shide',
    TABLE_VAR_SHOW => 'sshow',
    TABLE_VAR_IFIRST => 'sifirst',
    TABLE_VAR_ILAST => 'silast',
    TABLE_VAR_PAGE => 'spage'
));
$table->setup();

if ($bulkoperations) {
    echo '<form action="/user/action_redir.php" method="post" id="participantsform">';
    echo '<div>';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
    echo '<input type="hidden" name="returnto" value="' . s($PAGE->url->out(false)) . '" />';
}

/**
 * Get records
 */
$table->pagesize($userDirectory->perpage, null);

$results = $userDirectory->getUsers($table, $currentgroup);
echo '<div class="text-center">';
$table->initialbars(true);
echo'</div>';
$table->pagesize($userDirectory->perpage, $results->matchcount);


/**
 * List of results
 */

if ($results->totalcount < 1) {

    ?>
    <div class="alert alert-info">
    <?php echo get_string('nothingtodisplay'); ?>
    </div>
    <?php

} else {

    $pagingBars = '';
    if ($results->totalcount > $userDirectory->perpage) {
        $firstinitial = $table->get_initial_first();
        $lastinitial = $table->get_initial_last();
        $pagingBars .= $userDirectory->display->getLetterBar($firstinitial, $lastinitial);
        $pagingBars .= $userDirectory->display->getPagingBar($results->matchcount, $table->get_page_start());
        echo '<div class="text-center">';
        echo $pagingBars;
        echo '</div>';
        echo '<hr/>';
    }

    // If we're showing the users in a specific class, and the viewing user is a teacher, print out the class emails:
    // (Any teacher. Not specifically a teacher of this class)

    if ($currentgroup && $viewinguseristeacher) {
        // FIXME: SSIS
        $groupname = groups_get_group_name($currentgroup);
        echo '<br /><strong><i class="icon-user"></i> Bulk email for all students in this class:</strong><br />';
        $emailaddr = $groupname.'@student.ssis-suzhou.net';
        echo '<a href="mailto:'.$emailaddr.'">'.$emailaddr.'</a><br />';

        echo '<br /><strong><i class="icon-female"></i> Bulk email for all parents who have a child in this class:</strong><br />';
        $emailaddr = $groupname.'PARENTS@student.ssis-suzhou.net';
        echo '<a href="mailto:?bcc='.$emailaddr.'">'.$emailaddr.'</a><br /><br />';
    }


    if ($results->matchcount < 1) {
        echo $OUTPUT->heading(get_string('nothingtodisplay'));
    } else {

        ?>
        <table class="table table-striped user-directory-table">
        <thead>
            <tr>
                <th></th>
                <th><?php echo get_string('name'); ?></th>
                <th><?php echo get_string('email'); ?></th>
                <th><?php echo get_string('department'); ?></th>

                <?php
                foreach ($extrafields as $field) {
                    if (in_array($field, $skippedextrafields)) {
                        continue;
                    }
                    echo '<th>' . get_string($field) . '</th>';
                }
                ?>

                <th><?php echo get_string('actions'); ?></th>

                <?php
                if ($bulkoperations) {
                    echo '<th></th>';
                }
                ?>

            </tr>
        </thead>
        <tbody>
        <?php

        $usersprinted = array();

        foreach ($results->userlist as $user) {

            if (in_array($user->id, $usersprinted)) {
                continue;
            }

            $thisuserisparent = $userDirectory->isParent($user);
            $thisuserisstudent = $userDirectory->isStudent($user);
            $thisuseristeacher = $userDirectory->isTeacher($user);

            // Don't show parents to students or other parents
            if ($thisuserisparent && ($viewinguserisstudent || $viewinguserisparent)) {
                continue;
            }

            echo '<tr>';

                //echo '<td><img class="userpicture" alt="" src="http://placehold.it/60/60" /></td>';
                echo '<td>' . $OUTPUT->user_picture($user, array('size' => 80, 'courseid' => $course->id)) . '</td>';


                echo '<td>' . fullname($user, has_capability('moodle/site:viewfullnames', $context)) . '</td>';

                echo '<td>';
                //Email address field
                 if (
                    (
                        (
                            $user->maildisplay == 1
                            or
                            ($user->maildisplay == 2 and ($course->id != SITEID) and !isguestuser())
                            or
                            has_capability('moodle/course:viewhiddenuserfields', $context)
                            or
                            in_array('email', $extrafields)
                        )
                    )
                    or
                    ($user->id == $USER->id)
                ) {

                    echo '<i class="fa fa-envelope"></i> ' . html_writer::link("mailto:$user->email", $user->email);

                    if ($thisuserisstudent) {

                        // FIXME: SSIS
                        // Show their parent's email address
                        $parent_email_address = $user->username . "PARENTS@student.ssis-suzhou.net";
                        echo '<br/><i class="fa fa-male"></i> Parents\' Email: ' . html_writer::link("mailto:$parent_email_address", $parent_email_address);

                        // FIXME: SSIS
                        if ((int)$user->department >= 6) {
                            // Show the address to bulk email all the student's teachers
                            $teachers_email_address = $user->username . "TEACHERS@student.ssis-suzhou.net";
                            echo '<br/><i class="fa fa-magic"></i> All Teachers\' Email: ' . html_writer::link("mailto:$teachers_email_address", $teachers_email_address);

                            // Show their homeroom teacher's email address
                            $hr_email_address = $user->username . "HR@student.ssis-suzhou.net";
                            echo '<br/><i class="fa fa-heart"></i> Homeroom Teacher\'s Email: ' . html_writer::link("mailto:$hr_email_address", $hr_email_address);
                        }
                    }

                }
                echo '</td>';

                echo '<td>' . $user->department . '</td>';

                foreach ($extrafields as $field) {
                    if (in_array($field, $skippedextrafields)) {
                        continue;
                    }
                    echo '<td>'.s($user->{$field}).'</td>';
                }

                echo '<td>';

                    if ($thisuserisstudent) {
                        // OLP Link
                        echo html_writer::link(new moodle_url('/dragonnet/olp.php?userid='. $user->id), 'Online Portfolio', array('class' => 'btn btn-block btn-default'));
                    }

                    // Button to view notes about a user
                    if (!empty($CFG->enablenotes) and (has_capability('moodle/notes:manage', $context) || has_capability('moodle/notes:view', $context))) {
                        echo html_writer::link(new moodle_url('/notes/index.php?course=' . $course->id. '&user='.$user->id), get_string('notes','notes'), array('class' => 'btn btn-block btn-default'));
                    }

                    // Button to "Login As" user
                    if ($USER->id != $user->id && has_capability('moodle/user:loginas', $context) && !is_siteadmin($user->id)) {
                        echo html_writer::link(new moodle_url('/course/loginas.php?id='. $course->id .'&user='. $user->id .'&sesskey='. sesskey()), get_string('loginas'), array('class' => 'btn btn-block btn-default'));
                    }

                    // Button to view user's full profile
                    echo html_writer::link(new moodle_url('/user/view.php?id='. $user->id .'&course='. $course->id), get_string('fullprofile'), array('class' => 'btn btn-block btn-default'));


                echo '</td>';

                if ($bulkoperations) {
                    echo '<td><input type="checkbox" class="usercheckbox" name="user' . $user->id . '" /></td>';
                }

            echo '</tr>';

            // Remember that we've shown this user
            $usersprinted[] = $user->id;

        } // end foreach user

        echo '</tbody>';
        echo '</table>';

    } //end of if matchcount > 0

}  //end of user details view



if ($bulkoperations) {
    echo '<div class="buttons well text-center">';
    echo '<input type="button" id="checkall" value="'.get_string('selectall').'" /> ';
    echo '<input type="button" id="checknone" value="'.get_string('deselectall').'" /> ';
    $displaylist = array();
    $displaylist['messageselect.php'] = get_string('messageselectadd');
    if (!empty($CFG->enablenotes) && has_capability('moodle/notes:manage', $context) && $context->id != $frontpagecontext->id) {
        $displaylist['addnote.php'] = get_string('addnewnote', 'notes');
        $displaylist['groupaddnote.php'] = get_string('groupaddnewnote', 'notes');
    }

    echo $OUTPUT->help_icon('withselectedusers');
    echo html_writer::tag('label', get_string("withselectedusers"), array('for'=>'formactionid'));
    echo html_writer::select($displaylist, 'formaction', '', array(''=>'choosedots'), array('id'=>'formactionid'));

    echo '<input type="hidden" name="id" value="'.$course->id.'" />';
    echo '<noscript style="display:inline">';
    echo '<div><input type="submit" value="'.get_string('ok').'" /></div>';
    echo '</noscript>';
    echo '</div></div>';
    echo '</form>';

    $module = array('name'=>'core_user', 'fullpath'=>'/user/module.js');
    $PAGE->requires->js_init_call('M.core_user.init_participation', null, false, $module);
}



if ($results->totalcount > $userDirectory->perpage) {
    echo '<div class="text-center">';
    echo $pagingBars;
    echo '</div>';
}

echo $OUTPUT->footer();

if ($results->userlist) {
    $results->userlist->close();
}
