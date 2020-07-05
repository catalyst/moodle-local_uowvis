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
 * uowvis helper class for generating the appropriate visual data
 *
 * @package    local_uowvis
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @copyright  2020 Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_uowvis\local;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');
class data_collection
{

    private $collecteddata = null;
    private $supportedmodules = null;
    private $collectedcourses = null;
    private $mindate = 0;
    private $maxdate = 0;
    private $coursenames = null;
    private $collectedmoduledata = null;


    public function __construct() {
        $this->supportedmodules = array('assign', 'forum', 'data', 'quiz');
        $this->collecteddata = array();
        $this->coursenames = array();
        $this->collectedcourses = array();
        $this->collectedmoduledata = array();
    }

    public function collect_course($courseid) {
        global $DB;
        if (in_array($courseid, $this->collectedcourses)) {
            throw new moodle_exception('UoW datavis collecting course already collected');
        }
        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
        $courseinfo = \get_fast_modinfo($course);
        $coursemodules = \grade_get_gradable_activities($course->id);
        // Generate the mapping of module -> percentage of course grade.
        $reversegradetree = $this->generate_reverse_grade_tree($course);

        foreach ($coursemodules as $cm) {
            // Collection only of courses with a relevant collector class.
            $class = "local_uowvis\local\mod\\{$cm->modname}";
            if (class_exists($class)) {
                $modulecollector = new $class($cm, $course, $courseinfo, $reversegradetree);
                if ($modulecollector->is_eligible()) {
                    $data = $modulecollector->get_module_data();
                    // Lets check if the module ranges are greater than our current min/max.
                    $this->check_duedate_ranges($data);
                    // Push onto the collected array.
                    $this->collecteddata[] = $data;
                    if (!array_key_exists($data->modulename, $this->collectedmoduledata)) {
                        $metadata = $modulecollector->get_metadata();
                        $this->collectedmoduledata[$data->modulename] = $metadata;
                    }
                }
            }
        }
        $this->collectedcourses[] = $courseid;
        $this->coursenames[$courseid] = $course->fullname;
        return true;
    }

    /**
     * We need to calculate how much, as a weight of 100% of the overall course grade each individual module
     * actually is worth, the moodle grade tree works in reverse of this, where each individual
     * module is worth 100 and when calculating parents it flows the users grade upwards
     *
     * So we have to walk down the tree, calculating out a mulitiplier fraction of the overall course grade per
     * grade item id, as an array of id -> weight multiplier,
     *
     * Currently we only support GRADE_AGGREGATE_WEIGHTED_MEAN and GRADE_AGGREGATE_SUM (weighted mean and Natural)
     */
    private function generate_reverse_grade_tree($course) {
        $this->gradeitem = \grade_item::fetch_course_item($course->id);
        $this->coursegradecategory = $this->gradeitem->get_parent_category();
        $final = $this->calculate_weightings($this->gradeitem);
        return $final;
    }

    /**
     * Recursively calculate the individual weightings of each grade item for a module
     * this handles categories by recursing into them, passing in their weight multiplier
     * which is a percentage of the overall weight of the next level up
     * @param mixed $gradeitem
     * @param int $childmultiplier
     * @return (int|float)[]|mixed
     */
    private function calculate_weightings($gradeitem, $childmultiplier=1) {
        $category = $gradeitem->get_parent_category();
        $children = $category->get_children();
        // Calculate total sum of weights for the children, passing in aggregation type.
        $totalweight = $this->get_total_weight($category->aggregation, $children);
        $final = array();
        foreach ($children as $child) {
            $haschildren = false;
            if ($child['type'] == 'item') {
                $cgradeitem = $child['object'];
            } else if ($child['type'] == 'category') {
                $haschildren = true;
                $cgradeitem = $child['object']->load_grade_item();
            }
            if ($cgradeitem->gradetype == GRADE_TYPE_NONE) {
                continue; // Skip no grade item.
            }

            // For natural weighting the weight is basically already defined, we just need to calculate.
            // If there's any child multipliers involved.
            if ($category->aggregation == GRADE_AGGREGATE_SUM) {
                $cweight = $cgradeitem->aggregationcoef2 * $childmultiplier;
            } else if ($category->aggregation == GRADE_AGGREGATE_WEIGHTED_MEAN) {
                // For aggregated mean we calculate it's percentage of the total, multiplied by any child weight.
                if ($cgradeitem->aggregationcoef == 0) {
                    $cweight = 0;
                } else {
                    $cweight = ( $cgradeitem->aggregationcoef / $totalweight ) * $childmultiplier;
                }
            } else {
                $cweight = 0; // We can't calculate something valid as it's an unsupported type of grade.
            }
            $final[$cgradeitem->id] = $cweight;
            if ($haschildren) {
                // IF it was a category, calculate the weightings of the children.
                // Passing in our multiplier of weighting (so they are divided further).
                $childrenweights = $this->calculate_weightings($cgradeitem, $cweight);
                $final = $final + $childrenweights;
            }

        }
        return $final;
    }

    /**
     * Get the total weight of children grade weightings
     * @param mixed $aggregation
     * @param mixed $children
     * @return int|mixed
     */
    private function get_total_weight($aggregation, $children) {
        if ($aggregation === GRADE_AGGREGATE_SUM) {
            // Always automatically adds up to 100 weight.
            return 100;
        } else if ($aggregation == GRADE_AGGREGATE_WEIGHTED_MEAN) {
            $total = 0;
            foreach ($children as $child) {
                if ($child['type'] == 'item') {
                    $cgradeitem = $child['object'];
                } else if ($child['type'] == 'category') {
                    $cgradeitem = $child['object']->load_grade_item();
                }
                if ($cgradeitem->gradetype == GRADE_TYPE_NONE) {
                    continue; // Skip no grade item.
                }
                $total += $cgradeitem->aggregationcoef;
            }
            return $total;
        }
        // Unsupported grading type.
        return 0;
    }

    /**
     * Check if the passed in assessment module data date is later than the
     * current latest duedate reported, or earlier than the current
     * earliest due date.
     */
    private function check_duedate_ranges($data) {
        if (($data->duedate < $this->mindate) || $this->mindate == 0) {
            $this->mindate = $data->duedate;
        }
        if ($data->duedate > $this->maxdate || $this->maxdate == 0) {
            $this->maxdate = $data->duedate;
        }
    }

    /**
     * Generate an array of data that is acceptable to the local_uowvis webservices return type
     */
    public function output_to_webservices() {
        $final = new \stdClass();
        $final->assessment_data = $this->collecteddata;
        $final->courseids = $this->collectedcourses;
        $final->totalcourses = count($this->collectedcourses);
        $final->maxdate = $this->maxdate;
        $final->mindate = $this->mindate;
        $final->modulenames = array_keys($this->collectedmoduledata);
        $final->moduledata = array_values($this->collectedmoduledata);
        return $final;
    }
}
