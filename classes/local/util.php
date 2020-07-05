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
 * Utility class for creating the visualisations on a page
 *
 * @package    local_uowvis
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @copyright  2020 Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_uowvis\local;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

class util {

    public static function create_visualisation($divid, $iseditable) {
        global $PAGE, $USER;
        // Get all the courses the user is enrolled in.
        $allcourses = enrol_get_users_courses($USER->id, $onlyactive = true, null, $sort = 'timecreated DESC');
        $ids = array();
        $allids = array();
        foreach ($allcourses as $course) {
            $allids[] = (int)$course->id;
        }
        if (isset($USER->uowvis_ids)) {
            $ids = $USER->uowvis_ids;
        } else {
            // Get up to 10 courses as the initial display if not set in the user session already.
            $ids = array_slice($allids, 0, 10);
            $USER->uowvis_ids = $ids;
        }
        $allids = $ids;
        $PAGE->requires->js_call_amd('local_uowvis/bubble_vis', 'init', array($divid, $ids, $allids, $iseditable));
    }

    public static function search_or_enrolled_courses($query) {
        global $USER;
        $return = array();
        if ($query == '') {
            $allcourses = \enrol_get_users_courses($USER->id, $onlyactive = true, null, $sort = 'timecreated DESC');
            foreach ($allcourses as $course) {
                $return[] = array(
                    'courseid' => $course->id,
                    'coursename' => $course->fullname,
                );
            }
            return $return;
        } else {
            $searchresults = \core_course_category::search_courses(array('search' => $query), array('limit' => 15));
            foreach ($searchresults as $course) {
                $return[] = array(
                    'courseid' => $course->id,
                    'coursename' => $course->fullname,
                );
            }
            return $return;
        }
    }
}
