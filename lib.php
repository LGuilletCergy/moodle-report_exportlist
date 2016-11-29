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
 * Initially developped for :
 * Université de Cergy-Pontoise
 * 33, boulevard du Port
 * 95011 Cergy-Pontoise cedex
 * FRANCE
 *
 * Displays an exportable list of the course users, that can be filtered by group or activity completion.
 *
 * @package   report_exportlist
 * @copyright 2016 Brice Errandonea <brice.errandonea@u-cergy.fr>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * File : lib.php
 * Functions library.
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Serves the exported CSV file.
 * @global object $CFG
 * @global object $COURSE
 * @param string $listtitle
 * @param array of strings $filtersline
 * @param array of strings $columntitles
 * @param array of array of strings $userlines
 */
function report_exportlist_csv($listtitle, $filtersline, $columntitles, $userlines) {
    global $CFG, $COURSE;
    require_once($CFG->libdir . '/csvlib.class.php');
    $csvexporter = new csv_export_writer('semicolon');
    $csvexporter->set_filename($listtitle.' '.$COURSE->shortname);
    $title = array(utf8_decode($listtitle.' '.$COURSE->fullname));
    $csvexporter->add_data($title);
    $decodedfiltersline = report_exportlist_utf8($filtersline);
    $csvexporter->add_data($decodedfiltersline);
    $decodedcolumntitles = report_exportlist_utf8($columntitles);
    $csvexporter->add_data($decodedcolumntitles);
    foreach ($userlines as $userline) {
        $userline = report_exportlist_utf8($userline);
        $csvexporter->add_data($userline);
    }
    $csvexporter->download_file();
    exit;
}

/**
 * Displays the export button.
 * @global object $COURSE
 * @global object $PAGE
 */
function report_exportlist_exportbutton() {
    global $COURSE, $PAGE;
    $groupid = $PAGE->url->get_param('group');
    $roleid = $PAGE->url->get_param('role');
    $completed = $PAGE->url->get_param('completed');
    $cmid = $PAGE->url->get_param('cmid');
    $buttonstring = get_string('csvexport', 'report_exportlist');
    echo '<br><form style="text-align:center;" method="POST" action="index.php">';
    echo "<input type='hidden' name='id' value='$COURSE->id'>";
    echo "<input type='hidden' name='group' value='$groupid'>";
    echo "<input type='hidden' name='role' value='$roleid'>";
    echo "<input type='hidden' name='completed' value='$completed'>";
    echo "<input type='hidden' name='cmid' value='$cmid'>";
    echo "<input type='hidden' name='export' value='1'>";
    echo "<input type='submit' value='$buttonstring'>";
    echo '</form>';
}

/**
 * Lists the used search filters
 * @global object $DB
 * @global object $PAGE
 * @param object $coursecontext
 * @return string
 */
function report_exportlist_filtersline($coursecontext) {
    global $DB, $PAGE;
    $filtersline = array();
    $filtersline[] = get_string('appliedfilters', 'report_exportlist');
    $groupid = $PAGE->url->get_param('group');
    if ($groupid) {
        $group = $DB->get_record('groups', array('id' => $groupid));
        $filtersline[] = get_string('group').' : '.$group->name;
    }
    $roleid = $PAGE->url->get_param('role');
    if ($roleid) {
        $role = $DB->get_record('role', array('id' => $roleid));
        $rolename = role_get_name($role, $coursecontext);
        $filtersline[] = get_string('role').' : '.$rolename;
    }
    $completed = $PAGE->url->get_param('completed');
    $cmid = $PAGE->url->get_param('cmid');
    if ($completed && $cmid) {
        $cm = $DB->get_record('course_modules', array('id' => $cmid));
        $modulename = $DB->get_field('modules', 'name', array('id' => $cm->module));
        $instancename = $DB->get_field($modulename, 'name', array('id' => $cm->instance));
        if ($completed == 1) {
            $modfilter = get_string('completers', 'report_exportlist');
        } else {
            $modfilter = get_string('noncompleters', 'report_exportlist');
        }
        $modfilter .= ' : '.$instancename;
        $filtersline[] = $modfilter;
    }
    return $filtersline;
}

/**
 * Builds a list of this course's modules names.
 * @global object $COURSE
 * @global object $DB
 * @return array of strings
 */
function report_exportlist_find_mods() {
    global $COURSE, $DB;
    $modoptions = array();
    $sections = $DB->get_records('course_sections', array('course' => $COURSE->id));
    foreach ($sections as $section) {
        $cms = $DB->get_records('course_modules', array('section' => $section->id));
        foreach ($cms as $cm) {
            $modulename = $DB->get_field('modules', 'name', array('id' => $cm->module));
            $module = $DB->get_record($modulename, array('id' => $cm->instance));
            $modoptions[$cm->id] = "$section->name - $module->name";
        }
    }
    return $modoptions;
}

/**
 * Displays the report page (if html output).
 * @global object $OUTPUT
 * @param object $coursecontext
 * @param string $listtitle
 * @param array of strings $columntitles
 * @param array of arrays of strings $userlines
 */
function report_exportlist_html($coursecontext, $listtitle, $columntitles, $userlines) {
    global $OUTPUT;
    echo $OUTPUT->header();
    report_exportlist_selectors($coursecontext);
    echo '<h1>'.$listtitle.'</h1>';
    report_exportlist_maintable($columntitles, $userlines);
    report_exportlist_exportbutton();
    echo $OUTPUT->footer();
}

/**
 * Displays the list of filtered users in this course.
 * @param array $columntitles
 * @param array $userlines
 */
function report_exportlist_maintable($columntitles, $userlines) {
    echo "<table id='nbactions'>";
    echo "<tr>";
    foreach ($columntitles as $columntitle) {
        echo "<th>$columntitle</th>";
    }
    echo "</tr>";
    foreach ($userlines as $userline) {
        echo '<tr>';
        foreach ($userline as $userdata) {
            echo "<td>$userdata</td>";
        }
        echo '</tr>';
    }
    echo "</table>";
}

/**
 * Group selector.
 * @global object $COURSE
 * @global object $OUTPUT
 * @global object $PAGE
 * @return html string
 */
function report_exportlist_select_group() {
    global $COURSE, $OUTPUT, $PAGE;
    $groupsfromdb = groups_get_all_groups($COURSE->id);
    $groups = array();
    foreach ($groupsfromdb as $key => $value) {
        $groups[$key] = $value->name;
    }
    $groupid = $PAGE->url->get_param('group');
    $select = new single_select($PAGE->url, 'group', $groups, $groupid,
            array('' => get_string('allusers', 'report_exportlist')));
    $select->label = get_string('group').'&nbsp;';
    $PAGE->url->remove_params('group');
    $html = html_writer::start_tag('div');
    $html .= $OUTPUT->render($select);
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * To select the module the completion of which will be checked
 * @global object $OUTPUT
 * @global object $PAGE
 * @return html string
 */
function report_exportlist_select_mod() {
    global $OUTPUT, $PAGE;
    $modoptions = report_exportlist_find_mods();
    $cmid = $PAGE->url->get_param('cmid');
    $select = new single_select($PAGE->url, 'cmid', $modoptions, $cmid,
            array('' => get_string('choosemod', 'report_exportlist')));
    $select->label = get_string('mod', 'report_exportlist').'&nbsp;';
    $PAGE->url->remove_params('cmid');
    $html = html_writer::start_tag('div');
    $html .= $OUTPUT->render($select);
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Role selector.
 * @global object $PAGE
 * @global object $OUTPUT
 * @param object $coursecontext
 * @return html string
 */
function report_exportlist_select_role($coursecontext) {
    global $PAGE, $OUTPUT;
    $courseroles = get_roles_used_in_context($coursecontext);
    $options = array();
    foreach ($courseroles as $courserole) {
        $options[$courserole->id] = role_get_name($courserole, $coursecontext);
    }
    $roleid = $PAGE->url->get_param('role');
    $select = new single_select($PAGE->url, 'role', $options, $roleid,
            array('' => get_string('allusers', 'report_exportlist')));
    $select->label = get_string('role').'&nbsp;';
    $PAGE->url->remove_params('role');
    $html = html_writer::start_tag('div');
    $html .= $OUTPUT->render($select);
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Module completion state selector.
 * @global object $OUTPUT
 * @global object $PAGE
 * @param boolean $completed The currently selected completion state.
 * @param object $url
 * @return html string
 */
function report_exportlist_select_state() {
    global $OUTPUT, $PAGE;
    $options = array(1 => get_string('completers', 'report_exportlist'),
                     2 => get_string('noncompleters', 'report_exportlist'));
    $completed = $PAGE->url->get_param('completed');
    $select = new single_select($PAGE->url, 'completed', $options, $completed,
                                array('' => get_string('nomodfilter', 'report_exportlist')));
    $select->label = get_string('filter').'&nbsp;';
    $PAGE->url->remove_params('completed');
    $html = html_writer::start_tag('div');
    $html .= $OUTPUT->render($select);
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Displays the filter selectors above the main table.
 * @global object $COURSE
 * @global object $PAGE
 * @param object $coursecontext
 */
function report_exportlist_selectors($coursecontext) {
    global $COURSE, $PAGE;
    echo '<table><tr>';
    echo '<td>'.report_exportlist_select_group().'</td>';
    echo '<td>'.report_exportlist_select_role($coursecontext).'</td>';
    if ($COURSE->enablecompletion) {
        echo '<td>'.report_exportlist_select_state().'</td>';
        $completed = $PAGE->url->get_param('completed');
        if ($completed) {
            echo '<td>'.report_exportlist_select_mod().'</td>';
        }
    }
    echo '</tr></table><br>';
}

/**
 * List the names of this user's groups in this course.
 * @global object $DB
 * @param int $userid
 * @param int $courseid
 * @return string
 */
function report_exportlist_user_groups($userid, $courseid) {
    global $DB;
    $sql = "SELECT g.name "
             . "FROM {groups} g, {groups_members} gm "
             . "WHERE gm.userid = $userid AND gm.groupid = g.id AND g.courseid = $courseid";
    $usergroups = $DB->get_records_sql($sql);
    $usergroupstext = '';
    foreach ($usergroups as $usergroup) {
        $usergroupstext .= "$usergroup->name, ";
    }
    if (substr($usergroupstext, -2) == ', ') {
        $usergroupstext = substr($usergroupstext, 0, -2);
    }
    return $usergroupstext;
}

/**
 * Returns the user's roles in this context.
 * @param int $userid
 * @param object $coursecontext
 * @return string
 */
function report_exportlist_user_roles($userid, $coursecontext) {
    $userroles = get_user_roles($coursecontext, $userid);
    $userrolestext = '';
    foreach ($userroles as $userrole) {
        $rolename = role_get_name($userrole, $coursecontext);
        $userrolestext .= "$rolename, ";
    }
    if (substr($userrolestext, -2) == ', ') {
        $userrolestext = substr($userrolestext, 0, -2);
    }
    return $userrolestext;
}

/**
 * Prepares a line of data for a given user
 * @global object $COURSE
 * @global object $DB
 * @global object $PAGE
 * @param object $user
 * @param boolean $suspended
 * @param object $coursecontext
 * @return array of strings
 */
function report_exportlist_userline($user, $suspended, $coursecontext) {
    global $COURSE, $DB, $PAGE;
    if (in_array($user->id, $suspended)) {
        return null;
    }
    $roleid = $PAGE->url->get_param('role');
    if ($roleid) {
        $goodrole = $DB->record_exists('role_assignments',
                array('roleid' => $roleid, 'contextid' => $coursecontext->id, 'userid' => $user->id));
        if (!$goodrole) {
            return null;
        }
    }
    $completed = $PAGE->url->get_param('completed');
    $cmid = $PAGE->url->get_param('cmid');
    if ($completed && $cmid) {
        $completion = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid,
                                                                         'userid' => $user->id,
                                                                         'completionstate' => 1));
        if ($completion && ($completed == 2)) {
            return null;
        }
        if ((!$completion) && ($completed == 1)) {
            return null;
        }
    }
    if (!isset($user->idnumber)) {
        $user->idnumber = '';
    }
    $userrolestext = report_exportlist_user_roles($user->id, $coursecontext);
    $usergroupstext = report_exportlist_user_groups($user->id, $COURSE->id);
    $userlastaccess = $DB->get_record('user_lastaccess',
            array('userid' => $user->id, 'courseid' => $COURSE->id));
    if ($userlastaccess) {
        $lastaccesstext = date('Y/m/d H:i:s', $userlastaccess->timeaccess);
    } else {
        $lastaccesstext = get_string('never');
    }
    $userline = array($user->idnumber, $user->lastname, $user->firstname, $user->email,
                      $lastaccesstext, $userrolestext, $usergroupstext);
    return $userline;
}

/**
 * Apply utf8_decode to all the cells of an array.
 * @param array of strings $array
 * @return array of strings
 */
function report_exportlist_utf8($array) {
    $decodedarray = array();
    foreach ($array as $cell) {
        $decodedarray[] = utf8_decode($cell);
    }
    return $decodedarray;
}
