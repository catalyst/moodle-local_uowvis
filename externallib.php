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
 * Assessment Visualisation webservices
 *
 * @package    local_uowvis
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @copyright  2011 Moodle Pty Ltd (http://moodle.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_uowvis;
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . "/externallib.php");

use external_api;
use external_value;
use external_multiple_structure;
use external_function_parameters;
use context_course;
use external_single_structure;

class datavis_api extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_visdata_parameters() {
        return new external_function_parameters(
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, get_string('courseidparam', 'local_uowvis'), true, 0, false)
                )
            )
        );
    }

    /**
     * Generates a json array of objects that can be fed into a d3 bubble visualisation.
     *
     * Returned data is relevant to the courses selected
     * @param string $courseids An array of courseid's
     */
    public static function get_visdata(array $courseids) {
        global $USER;

        // Parameter validation.
        $params = self::validate_parameters(self::get_visdata_parameters(),
                array('courseids' => $courseids));

        $collector = new local\data_collection();
        $courseids = $params['courseids'];
        foreach ($courseids as $courseid) {
            $context = context_course::instance($courseid);
            self::validate_context($context);
            $course = get_course($courseid);
            $collector->collect_course($courseid);
        }
        return $collector->output_to_webservices();
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_visdata_returns() {
        return new external_single_structure(
            array(
                'maxdate' => new external_value(PARAM_INT, 'maximum date (unix timestamp)'),
                'mindate' => new external_value(PARAM_INT, 'minimum date (unix timestamp)'),
                'totalcourses' => new external_value(PARAM_INT, 'max number of courses'),
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Course ID')
                ),
                'modulenames' => new external_multiple_structure(
                    new external_value(PARAM_TEXT, 'Module instance types')
                ),
                'moduledata' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'type' => new external_value(PARAM_TEXT, 'Module instance types'),
                            'langstring' => new external_value(PARAM_TEXT, 'Module plugin name')
                        )
                    )
                ),
                'assessment_data' => new external_multiple_structure (
                        new external_single_structure(
                        array(
                            'duedate' => new external_value(PARAM_INT, 'Date assessment is due'),
                            'course' => new external_value(PARAM_TEXT, 'Name of the course'),
                            'shortname' => new external_value(PARAM_TEXT, 'shortname of the course'),
                            'courseid' => new external_value(PARAM_INT, 'The course id'),
                            'modulename' => new external_value(PARAM_TEXT, 'The course module type name'),
                            'name' => new external_value(PARAM_TEXT, 'The course module name'),
                            'cmid' => new external_value(PARAM_TEXT, 'The course module id'),
                            'instanceid' => new external_value(PARAM_INT, 'The actual id of the instance of the module'),
                            'weight' => new external_value(
                                PARAM_FLOAT,
                                'The weighting of this assessment relative to overall course grade'
                            )
                        )
                    )
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_coursedata_parameters() {
        return new external_function_parameters(
            array(
                'allcourses' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'all courses', true, 0, false)
                ),
                'selected' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'selected courses', true, 0, false)
                )
            )
        );
    }

    /**
     * Generates a json array of objects that can be fed into a d3 bubble visualisation.
     *
     * Returned data is relevant to the courses selected
     * @param string $courseids An array of courseid's
     */
    public static function get_coursedata(array $allcourses, array $selected) {
        global $USER, $DB;

        // Parameter validation.
        $params = self::validate_parameters(
            self::get_coursedata_parameters(),
            array(
                'allcourses' => $allcourses,
                'selected' => $selected,
            )
        );

        $allcourses = $params['allcourses'];
        $selected = $params['selected'];
        $USER->uowvis_ids = $selected;
        $return = array();
        foreach ($allcourses as $courseid) {
            $name = $DB->get_field('course', 'fullname', array('id' => $courseid), IGNORE_MISSING);
            $shortname = $name;
            // Truncate the name of the course if it will be too long for the graph display.
            if (\core_text::strlen($name) > 17) {
                $shortname = \core_text::substr($name, 0, 17).get_string('dots', 'local_uowvis');
            }
            $isselected = in_array($courseid, $selected);
            $return[] = array('courseid' => $courseid, 'coursename' => $name, 'selected' => $isselected, 'shortname' => $shortname);
        }
        return $return;
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_coursedata_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'coursename' => new external_value(PARAM_TEXT, 'The course module type name'),
                    'selected' => new external_value(PARAM_BOOL, 'if the course is selected'),
                    'shortname' => new external_value(PARAM_TEXT, 'Clipped course module name'),
                )
            )
        );
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function get_searchdata_parameters() {
        return new external_function_parameters(
            array(
                'query' => new external_value(PARAM_TEXT, 'The course name the user is searching for'),
            )
        );
    }

    public static function get_searchdata($query) {
        global $USER, $DB;
        // Parameter validation.
        $params = self::validate_parameters(
            self::get_searchdata_parameters(),
            array(
                'query' => $query,
            )
        );
        $query = $params['query'];
        // Get either a search of all courses, if the query is not a blank string, otherwise search all courses.
        return \local_uowvis\local\util::search_or_enrolled_courses($query);
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function get_searchdata_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'courseid' => new external_value(PARAM_INT, 'Course ID'),
                    'coursename' => new external_value(PARAM_TEXT, 'The course module type name'),
                )
            )
        );
    }
}
