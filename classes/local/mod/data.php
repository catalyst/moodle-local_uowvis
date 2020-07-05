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

namespace local_uowvis\local\mod;

use moodle_exception;

defined('MOODLE_INTERNAL') || die();

class data extends module_collection {

    protected function calculate_due_date() {
        global $DB;
        // Already generated, just use it!
        if ($this->duedate != null) {
            return $this->duedate;
        } else {
            if ($duedate = $DB->get_field('data', 'assesstimefinish', array('id' => $this->cm->instance))) {
                $this->duedate = $duedate;
            } else {
                $this->duedate = 0;
            }
            return $this->duedate;
        }
    }
}
