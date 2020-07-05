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
 * Datasource for the Assessment visualisation autocompleter
 *
 *
 * @package    tool_uowvis
 * @copyright  2017 Jun Pataleta
 * @copyright  2020 Catalyst IT Ltd
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax'], function($, ajax) {

    return /** @alias module:local_uowvis/bubble_vis_datasource */ {
        /**
         * List filter options.
         *
         * @param {String} selector The select element selector.
         * @param {String} query The query string.
         * @return {Promise}
         */
        list: function(selector, query) {
            var filteredOptions = [];

            var el = $(selector);
            var originalOptions = $(selector).data('originaloptionsjson');
            var selectedFilters = el.val();
            $.each(originalOptions, function(index, option) {
                // Skip option if it does not contain the query string.
                if ($.trim(query) !== '' && option.label.toLocaleLowerCase().indexOf(query.toLocaleLowerCase()) === -1) {
                    return true;
                }
                // Skip filters that have already been selected.
                if ($.inArray(option.value, selectedFilters) > -1) {
                    return true;
                }

                filteredOptions.push(option);
                return true;
            });

            var deferred = new $.Deferred();
            deferred.resolve(filteredOptions);

            return deferred.promise();
        },

        /**
         * Process the results for auto complete elements.
         *
         * @param {String} selector The selector of the auto complete element.
         * @param {Array} results An array or results.
         * @return {Array} New array of results.
         */
        processResults: function(selector, results) {
            var options = [];
            $.each(results, function(index, value) {
                options.push({value:value.courseid, label: value.coursename});
            });
            return options;
        },

        /**
         * Source of data for AJAX element.
         *
         * @param {String} selector The selector of the auto complete element.
         * @param {String} query The query string.
         */
        transport: function(selector, query, success, failure) {
            // Parse some data-attributes from the form element.

            // Build the query.
            var promises = null;

            if (typeof query === "undefined") {
                query = '';
            }

            var searchargs = {
                query: query,
            };

            var calls = [{
                methodname: 'local_uowvis_get_searchdata', args: searchargs
            }];

            // Go go go!
            promises = ajax.call(calls);
            $.when.apply($.when, promises).done(function(data) {
                success(data);
            }).fail(failure);
        }
    };
});
