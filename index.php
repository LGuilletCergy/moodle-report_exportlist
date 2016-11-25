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
 * Displays an exportable list of students
 *
 * @package   report_exportlist
 * @copyright 2016 Brice Errandonea
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib.php');

$id     = required_param('id', PARAM_INT);
$group  = optional_param('group', 0, PARAM_INT);
$completed = optional_param('completed', 0, PARAM_INT);
$cmid  = optional_param('cmid', 0, PARAM_INT);
$export = optional_param('export', 0, PARAM_INT);
$params = array('id' => $id, 'group' => $group, 'completed' => $completed, 'cmid'=> $cmid, 'export' => $export);

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);

// Setup page.
$PAGE->set_url('/report/exportlist/index.php', $params);
$PAGE->set_pagelayout('report');

$returnurl = new moodle_url('/course/view.php', array('id' => $id));

// Check permissions.
$coursecontext = context_course::instance($course->id);
require_capability('report/exportlist:view', $coursecontext);

// Get users.
$userlist = get_enrolled_users($coursecontext, '', $group, user_picture::fields('u', null, 0, 0, true));
$suspended = get_suspended_userids($coursecontext);

// Finish setting up page.
$PAGE->set_title($course->shortname .': '. get_string('exportlist' , 'report_exportlist'));
$PAGE->set_heading($course->fullname);

// Prepare data.
$listtitle = get_string('studentslist', 'report_exportlist');
$columntitles = array(get_string('studentnumber', 'report_exportlist'), get_string('lastname'),
                      get_string('firstname'), get_string('email'), get_string('lastcourseaccess'), get_string('groups'));
$users = Array();
$tableau = Array();
$mini = Array();
$maxi = Array();
$userlines = array();

foreach ($userlist as $user) {
    if (in_array($user->id, $suspended)) {
        continue;
    }
    if ($completed && $cmid) {
        $completion = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cmid,
                                                                         'userid' => $user->id,
                                                                         'completionstate' => 1));
        if ($completion && ($completed == 2)) {
            continue;
        }
        if ((!$completion) && ($completed == 1)) {
            continue;
        }
    }
    if (!isset($user->idnumber)) {
        $user->idnumber = '';
    }
    $usergroupstext = report_exportlist_user_groups($user->id, $course->id);
    $userlastaccess = $DB->get_record('user_lastaccess', array('userid' => $user->id, 'courseid' => $course->id));
    if ($userlastaccess) {
        $lastaccesstext = date('Y/m/d H:i:s', $userlastaccess->timeaccess);
    } else {
        $lastaccesstext = get_string('never');
    }
    $userline = array($user->idnumber, $user->lastname, $user->firstname, $user->email, $lastaccesstext, $usergroupstext);
    $userlines[] = $userline;
}

if ($export) {
    report_exportlist_csv($listtitle, $course, $columntitles, $userlines);
} else {
    report_exportlist_html($id, $group, $completed, $cmid, $listtitle, $columntitles, $userlines);
}
