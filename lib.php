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

defined('MOODLE_INTERNAL') || die;

function report_exportlist_html($id, $group, $completed, $cmid, $listtitle, $columntitles, $userlines) {
    global $COURSE, $OUTPUT, $PAGE;
    echo $OUTPUT->header();
    ?>
    <table>
        <tr>
            <?php
            echo '<td>'.report_exportlist_select_group($id, $group, $PAGE->url).'</td>';
            if ($COURSE->enablecompletion) {
                echo '<td>'.report_exportlist_select_state($completed, $PAGE->url).'</td>';
                if ($completed) {
                    echo '<td>'.report_exportlist_select_mod($id, $cmid, $PAGE->url).'</td>';
                }
            }
            ?>
        </tr>
    </table>
    <br>
    <?php
    //Title and column titles
    echo '<h1>'.$listtitle.'</h1>';
    echo "<table id='nbactions'>";
    echo "<tr>";
    foreach ($columntitles as $columntitle) {
        echo "<th>$columntitle</th>";
    }
    echo "</tr>";

    //For each student
    foreach ($userlines as $userline) {
        echo '<tr>';
        foreach ($userline as $userdata) {
            echo "<td>$userdata</td>";
        }
        echo '</tr>';
    }
    echo "</table>";
    ?>
    <br>
    <p style="text-align: center;">
        <a href='<?php echo "index.php?id=$COURSE->id&export=1"; ?>'>
            <button>
                <?php echo get_string('csvexport', 'report_exportlist'); ?>
            </button>
        </a>
    </p>
    <?php
    echo $OUTPUT->footer();
}

function report_exportlist_user_groups($userid, $courseid) {
    global $DB;
    $sql = "SELECT g.name "
             . "FROM {groups} g, {groups_members} gm "
             . "WHERE gm.userid = $userid AND gm.groupid = g.id AND g.courseid = $courseid";
    $usergroups = $DB->get_records_sql($sql);
    $usergroupstext = '';
    foreach($usergroups as $usergroup) {
        $usergroupstext .= "$usergroup->name, ";
    }
    if (substr($usergroupstext, -2) == ', ') {
        $usergroupstext = substr($usergroupstext, 0, -2);
    }
    return $usergroupstext;
}

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

function report_exportlist_utf8($array) {
    $decoded_array = array();
    foreach ($array as $cell) {
        $decoded_array[] = utf8_decode($cell);
    }
    return $decoded_array;
}

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

function report_exportlist_select_state($completed, $url) {
    global $OUTPUT;
    $options = array(1 => get_string('completers', 'report_exportlist'),
                     2 => get_string('noncompleters', 'report_exportlist'));
    $stateurl = clone $url;
    $select = new single_select($stateurl, 'completed', $options, $completed, array('' => get_string('nomodfilter', 'report_exportlist')));
    $select->label = get_string('filter').'&nbsp;';
    $html = html_writer::start_tag('div');
    $html .= $OUTPUT->render($select);
    $html .= html_writer::end_tag('div');
    return $html;
}

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

function report_exportlist_csv($listtitle, $course, $columntitles, $userlines) {
    global $CFG;
    require_once($CFG->libdir . '/csvlib.class.php');
    $csvexporter = new csv_export_writer('semicolon');
    $csvexporter->set_filename($listtitle.' '.$course->shortname);
    $title = array(utf8_decode($listtitle.' '.$course->fullname));
    $csvexporter->add_data($title);
    $columntitles = report_exportlist_utf8($columntitles);
    $csvexporter->add_data($columntitles);
    foreach ($userlines as $userline) {
        $userline = report_exportlist_utf8($userline);
        $csvexporter->add_data($userline);
    }
    $csvexporter->download_file();
    exit;
}
