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
 * Webservices for the visualisation of assessments
 *
 * @package    local_uowvis
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @copyright  2020 Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = array(
    'local_uowvis_get_visdata' => array(
        'classname'   => 'local_uowvis\datavis_api',
        'methodname'  => 'get_visdata',
        'description' => get_string('get_visdata_desc', 'local_uowvis'),
        'type'        => 'read',
        'ajax'        => true,
    ),
    'local_uowvis_get_coursedata' => array(
        'classname'   => 'local_uowvis\datavis_api',
        'methodname'  => 'get_coursedata',
        'description' => get_string('get_coursedata_desc', 'local_uowvis'),
        'type'        => 'read',
        'ajax'        => true,
    ),
    'local_uowvis_get_searchdata' => array(
        'classname'   => 'local_uowvis\datavis_api',
        'methodname'  => 'get_searchdata',
        'description' => get_string('get_searchdata_desc', 'local_uowvis'),
        'type'        => 'read',
        'ajax'        => true,
    ),
);
