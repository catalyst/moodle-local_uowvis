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
 * Language strings for the assessment visualisation plugin
 *
 * @package    local_uowvis
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @copyright  2020 Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Assessment Map';
$string['courselabel'] = 'Courses';
$string['timelabel'] = 'Assessment due date';
$string['pagedesc'] = "This is a visual map of upcoming and past course modules in a set of courses, with time along the horizontal axis. Using this visualisation you can see at a glance where student time should be spent, and how different courses might overlap with module due dates.

The size of the bubble indicates how much of the overall course grade the module is worth and the colouring indicates what type of module it is. Hovering over a module with your mouse will show a tooltip with the name of the module and the exact grade of the module";
$string['tooltip'] = 'Course: {$a->course} <br> Module: {$a->name}<br> Due: {$a->date}<br> Percent of grade: {$a->grade_percent}%';
$string['selectbox'] = 'Modify shown courses';
$string['selected_explainer'] = 'Add courses by searching in the drop down, and remove them by clicking on their label';
$string['get_visdata_desc'] = 'Get data for courses passed in for the UOW visualisation';
$string['get_coursedata_desc'] = 'Get names and ids of courses from the list of ids passed in, excluding ids from the exclude list';
$string['nocourses'] = 'There are no courses to display, select some';
$string['uowvis:changecourses'] = 'Change the courses shown in the Assessment Map';
$string['searchcourses'] = 'Type course name here';
$string['dots'] = '...';
$string['get_searchdata_desc'] = 'Get courses matching the search query';
$string['privacy:metadata'] = 'The Assessment mapping plugin does not store any personal data, it only redisplays existing data';
