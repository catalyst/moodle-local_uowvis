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
 * Page to view the assessment visualisation from
 *
 * @package    local_uowvis
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @copyright  2020 Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$systemcontext = context_system::instance();
$PAGE->set_url('/local/uowvis/index.php');
$PAGE->set_context($systemcontext);

require_login();

$visid = 'myvis';
$iseditable = has_capability('local/uowvis:changecourses', $systemcontext);
// Will add the relevant javascript to the page, along with populating the starting state.
\local_uowvis\local\util::create_visualisation($visid, $iseditable);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_uowvis'));
echo $OUTPUT->box(get_string('pagedesc', 'local_uowvis'));
// You have to provide the relevant div with matching ID.
// Then the visualisation will draw inside that div.
echo $OUTPUT->box('', array(), $visid);
echo $OUTPUT->footer();

