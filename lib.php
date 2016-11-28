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
 * Serves a CSV file with the report's data.
 * @global object $CFG
 * @param string $listtitle
 * @param object $course
 * @param array of strings $columntitles
 * @param array of arrays of strings $userlines
 */
function report_exportlist_csv($listtitle, $course, $columntitles, $userlines) {
    global $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
    $csvexporter = new csv_export_writer('semicolon');
    $csvexporter->set_filename($listtitle.' '.$course->shortname);
    $title = array(utf8_decode($listtitle.' '.$course->fullname));
    $csvexporter->add_data($title);
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
 */
function report_exportlist_exportbutton() {
    global $COURSE;
    ?>
    <br>
    <form style="text-align:center;" method="POST" action="index.php">
    <input type="hidden" name="id" value="<?php echo $COURSE->id; ?>">
    <input type="hidden" name="export" value="1">
    <input type="submit" value="<?php echo get_string('csvexport', 'report_exportlist'); ?>">
    </form>
    <?php
}

/**
 * Builds a list of this course's modules names.
 * @global object $DB
 * @param int $courseid
 * @return array of strings
 */
function report_exportlist_find_mods($courseid) {
    global $DB;
    $modoptions = array();
    $sections = $DB->get_records('course_sections', array('course' => $courseid));
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
 * @param array $params
 * @param string $listtitle
 * @param array of strings $columntitles
 * @param array of arrays of strings $userlines
 */
function report_exportlist_html($coursecontext, $params, $listtitle, $columntitles, $userlines) {
    global $OUTPUT;
    echo $OUTPUT->header();
    report_exportlist_selectors($coursecontext, $params);
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
 * @global object $OUTPUT
 * @param int $id
 * @param int $group id of the currently selected group.
 * @param object $url
 * @return html string
 */
function report_exportlist_select_group($id, $group, $url) {
    global $OUTPUT;
    $groupsfromdb = groups_get_all_groups($id);
    $groups = array();
    foreach ($groupsfromdb as $key => $value) {
        $groups[$key] = $value->name;
    }
    $groupurl = clone $url;
    $select = new single_select($groupurl, 'group', $groups, $group, array('' => get_string('allusers', 'report_exportlist')));
    $select->label = get_string('group').'&nbsp;';
    $html = html_writer::start_tag('div');
    $html .= $OUTPUT->render($select);
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * To select the module the completion of which will be checked
 * @global object $OUTPUT
 * @param int $id course id
 * @param int $cmid id of the currently selected course module
 * @param object $url
 * @return html string
 */
function report_exportlist_select_mod($id, $cmid, $url) {
    global $OUTPUT;
    $modoptions = report_exportlist_find_mods($id);
    $modurl = clone $url;
    $select = new single_select($modurl, 'cmid', $modoptions, $cmid, array('' => get_string('choosemod', 'report_exportlist')));
    $select->label = get_string('mod', 'report_exportlist').'&nbsp;';
    $html = html_writer::start_tag('div');
    $html .= $OUTPUT->render($select);
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Role selector.
 * @global object $OUTPUT
 * @param int $role id of the currently selected role
 * @param object $coursecontext
 * @param object $url
 * @return html string
 */
function report_exportlist_select_role($role, $coursecontext, $url) {
    global $OUTPUT;
    $courseroles = get_roles_used_in_context($coursecontext);
    $options = array();
    foreach ($courseroles as $courserole) {
        $options[$courserole->id] = role_get_name($courserole, $coursecontext);
    }
    $roleurl = clone $url;
    $select = new single_select($roleurl, 'role', $options, $role, array('' => get_string('allusers', 'report_exportlist')));
    $select->label = get_string('role').'&nbsp;';
    $html = html_writer::start_tag('div');
    $html .= $OUTPUT->render($select);
    $html .= html_writer::end_tag('div');
    return $html;
}

/**
 * Module completion state selector.
 * @global object $OUTPUT
 * @param boolean $completed The currently selected completion state.
 * @param object $url
 * @return html string
 */
function report_exportlist_select_state($completed, $url) {
    global $OUTPUT;
    $options = array(1 => get_string('completers', 'report_exportlist'),
                     2 => get_string('noncompleters', 'report_exportlist'));
    $stateurl = clone $url;
    $select = new single_select($stateurl, 'completed', $options, $completed,
                                array('' => get_string('nomodfilter', 'report_exportlist')));
    $select->label = get_string('filter').'&nbsp;';
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
 * @param array of int $params
 */
function report_exportlist_selectors($coursecontext, $params) {
    global $COURSE, $PAGE;
    echo '<table><tr>';
    echo '<td>'.report_exportlist_select_group($params['id'], $params['group'], $PAGE->url).'</td>';
    echo '<td>'.report_exportlist_select_role($params['role'], $coursecontext, $PAGE->url).'</td>';
    if ($COURSE->enablecompletion) {
    echo '<td>'.report_exportlist_select_state($params['completed'], $PAGE->url).'</td>';
        if ($params['completed']) {
            echo '<td>'.report_exportlist_select_mod($params['id'], $params['cmid'], $PAGE->url).'</td>';
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
    foreach($userroles as $userrole) {
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
 * @param object $user
 * @param array of int $params
 * @param boolean $suspended
 * @param object $coursecontext
 * @return array of strings
 */
function report_exportlist_userline($user, $params, $suspended, $coursecontext) {
    global $COURSE, $DB;
    if (in_array($user->id, $suspended)) {
        return null;
    }
    if ($params['role']) {
        $goodrole = $DB->record_exists('role_assignments',
                array('roleid' => $params['role'], 'contextid' => $coursecontext->id, 'userid' => $user->id));
        if (!$goodrole) {
            return null;
        }
    }
    if ($params['completed'] && $params['cmid']) {
        $completion = $DB->get_record('course_modules_completion', array('coursemoduleid' => $params['cmid'],
                                                                         'userid' => $user->id,
                                                                         'completionstate' => 1));
        if ($completion && ($params['completed'] == 2)) {
            return null;
        }
        if ((!$completion) && ($params['completed'] == 1)) {
            return null;
        }
    }
    if (!isset($user->idnumber)) {
        $user->idnumber = '';
    }
    $userrolestext = report_exportlist_user_roles($user->id, $coursecontext);
    $usergroupstext = report_exportlist_user_groups($user->id, $COURSE->id);
    $userlastaccess = $DB->get_record('user_lastaccess', array('userid' => $user->id, 'courseid' => $COURSE->id));
    if ($userlastaccess) {
        $lastaccesstext = date('Y/m/d H:i:s', $userlastaccess->timeaccess);
    } else {
        $lastaccesstext = get_string('never');
    }
    $userline = array($user->idnumber, $user->lastname, $user->firstname, $user->email, $lastaccesstext, $userrolestext, $usergroupstext);
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
