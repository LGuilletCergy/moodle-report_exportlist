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
 * File : index.php
 * Report page.
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once('lib.php');

$id = required_param('id', PARAM_INT);
$role = optional_param('role', 0, PARAM_INT);
$group  = optional_param('group', 0, PARAM_INT);
$completed = optional_param('completed', 0, PARAM_INT);
$cmid  = optional_param('cmid', 0, PARAM_INT);
$export = optional_param('export', 0, PARAM_INT);
$params = array('id' => $id, 'role' => $role, 'group' => $group,
                'completed' => $completed, 'cmid' => $cmid, 'export' => $export);

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
require_login($course);

// GEt enrol method
$enrol = $DB->get_record('enrol', array('courseid' => $id, 'enrol' => 'self'));

// Setup page.
$PAGE->set_url('/report/exportlist/index.php', $params);
$PAGE->set_pagelayout('report');

$returnurl = new moodle_url('/course/view.php', array('id' => $id));

// Check permissions.
$coursecontext = context_course::instance($course->id);
require_capability('report/exportlist:view', $coursecontext);

// Get users.
$userlist = get_enrolled_users($coursecontext, '', $group);
$suspended = get_suspended_userids($coursecontext);

// Finish setting up page.
$title = $course->shortname .': '. get_string('exportlist' , 'report_exportlist');
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

// Prepare data.
$listtitle = get_string('studentslist', 'report_exportlist');
$columntitles = array(get_string('studentnumber', 'report_exportlist'), get_string('lastname'),
                      get_string('firstname'), get_string('email'), get_string('lastcourseaccess'),
                      get_string('roles'), get_string('groups'));
$userlines = array();

foreach ($userlist as $user) {

    $userenrolment = $DB->get_record('user_enrolments', array('userid' => $user->id, 'enrolid' => $enrol->id));

    if (!$userenrolment) {
        continue;
    }

    // LAURENTHACKED. Utilisation du timestamp de config.php.

    if ($userenrolment->timecreated < $CFG->currenttermregistrationstart) {
        continue;
    }
    $userline = report_exportlist_userline($user, $suspended, $coursecontext);
    if ($userline) {
        $userlines[] = $userline;
    }
}

// Output.
if ($export) {
    $filtersline = report_exportlist_filtersline($coursecontext);
    report_exportlist_csv($listtitle, $filtersline, $columntitles, $userlines);
} else {
    report_exportlist_html($coursecontext, $listtitle, $columntitles, $userlines);
}
