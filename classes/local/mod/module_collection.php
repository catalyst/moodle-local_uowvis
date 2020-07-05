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
 * Base class for generating module data for a module
 *
 * @package    local_uowvis
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @copyright  2020 Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_uowvis\local\mod;
defined('MOODLE_INTERNAL') || die();

class module_collection {

    protected $cm = null;
    protected $grade = null;
    protected $course = null;
    protected $duedate = null;
    protected $data = null;
    protected $metadata = null;
    protected $cminfo = null;
    protected $gradeitem = null;
    protected $coursegradecategory = null;
    protected $gradeitemweightings = null;

    public function __construct($cm, $course, $courseinfo, $gradeitemweightings) {
        $this->cm = $cm;
        $this->cminfo = $courseinfo->get_cm($cm->id);
        $this->gradeitemweightings = $gradeitemweightings;
        $this->grade = $this->get_grade();
        $this->course = $course;
        $this->duedate = $this->calculate_due_date();
        $this->data = $this->get_module_data();
        $this->metadata = $this->get_metadata();
    }

    /*
     * Get the main grade item in the gradebook for this module
     */
    protected function get_grade() {
        if ($this->grade != null) {
            return $this->grade;
        }

        // Fetch the grade items (note we only get the main grade item as main_grade_item is set to true).
        $grade = grade_get_grade_items_for_activity($this->cm, true);
        // We only return the main grade item, so the array is only going to have one item.
        $this->grade = reset($grade);
        return $this->grade;
    }


    /*
     * Is this module eligible for collection
     * We do not check visibility information, as the client
     * wants these to be visible to all students/staff.
     */
    public function is_eligible() {
        return $this->get_module_data()->weight > 0 &&
            $this->calculate_due_date() != null;
    }

    /*
     * Collect the relevant data for this module
     */
    public function get_module_data() {
        if ($this->data != null) {
            return $this->data;
        }

        global $DB;
        $data = new \stdClass();
        $data->duedate = $this->calculate_due_date();
        $data->course = $this->course->fullname;
        if (\core_text::strlen($this->course->fullname) > 17) {
            $data->course = \core_text::substr($this->course->fullname, 0, 17).get_string('dots', 'local_uowvis');
        }
        $data->shortname = $this->course->shortname;
        $data->courseid = $this->course->id;
        $data->modulename = $this->cm->modname;
        $data->cmid = $this->cm->id;
        $data->instanceid = $this->cm->instance;
        $data->name = $this->cm->name;
        $data->weight = round($this->gradeitemweightings[$this->grade->id] * 100, 2);
        $this->data = $data;
        return $this->data;
    }

    public function get_metadata() {
        if ($this->metadata != null) {
            return $this->metadata;
        }
        // Avoid assuming we are calling these in any specific order.
        $data = $this->get_module_data();
        $moduledata = new \stdClass();
        $moduledata->type = $data->modulename;
        $moduledata->langstring = get_string('pluginname', 'mod_'.$data->modulename);
        $this->metadata = $moduledata;
        return $this->metadata;
    }

    protected function calculate_due_date() {
        // Already generated, just use it.
        if ($this->duedate != null) {
            return $this->duedate;
        } else {
            // Default implementation leaves it null, and not eligible for inclusion.
            $this->duedate = null;
            return $this->duedate;
        }
    }
}
