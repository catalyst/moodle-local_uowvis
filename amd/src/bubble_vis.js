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
 * Javascript for the Assessment workload bubblevis for course assessment dates.
 * this code was heavily informed by the https://www.d3-graph-gallery.com/bubble.html
 * tutorials
 *
 * @package    local_uowvis
 * @author     Francis Devine <francis@catalyst.net.nz>
 * @copyright  2020 Catalyst IT Ltd
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['local_uowvis/d3', 'local_uowvis/d3-simple-slider', 'core/log',
    'jquery', 'core/ajax', 'core/str', 'core/notification', 'core/templates',
    'core/form-autocomplete'],
    function (md3, d3_slider, log, $, ajax, str, notification, templates, autocomplete) {
        function UOWVis() {
            // SVG used to draw the graph in.
            this.vis_canvas = null;
            // The div we are putting the graph + associated control templates in (starts hidden and is faded in when drawn).
            this.current_canvas = null;
            // The div a previous canvas lived in (if one existed), we fade this out, once the redraw is done.
            this.previous_canvas = null;
            // D3 scale for the x axis (courses).
            this.x_axis = null;
            // Config object with some developer set configs.
            this.config = null;
            // D3 scale for y axis(dates).
            this.y_axis = null;
            // Holds strings from moodle.
            this.x_axis_label = null;
            this.y_axis_label = null;
            // Max/min date of data sent in.
            this.maxdate = null;
            this.mindate = null;
            // Total courses being processed here.
            this.totalcourses = null;
            // Generated map of courseid -> name (used in the x_axis scale to get nice readable names).
            this.courseid_to_name_map = null;
            // Options to format the date (all developer set).
            this.date_options = null;
            // Total width of the graph including margins.
            this.totalwidth = null;
            // Height of graph (rowheight config * num courses).
            this.height = null;
            // Total height of graph including margins.
            this.totalheight = null;
            // ID of the div we are drawing on.
            this.drawing_div_id = null;
            // Last fetched data from webservice.
            this.data = null;
            // A div for drawing tooltips.
            this.tooltip = null;
            // Can the user select courses for display.
            this.can_change = null;
            // The svg G drawing attribute containing the data dots.
            this.gData = null;
            // String to encourage users to search in the dropdown box.
            this.searchcourses_label = null;
            // We are currently redrawing, don't allow changes to the courses selected.
            this.redrawing = null;
        }

        UOWVis.prototype.init = function (divid, courseids, all_ids, can_change) {
            log.debug('Creating graph at div with id ' + divid + ' for following courses ' + courseids.join(','));

            log.debug('Calling data endpoints');
            var promises = ajax.call([
                { methodname: 'local_uowvis_get_visdata', args: { courseids: courseids } },
                { methodname: 'local_uowvis_get_coursedata', args: { allcourses: all_ids, selected: courseids } }
            ]);
            log.debug('Fetching relevant strings');
            var strings = str.get_strings([
                { 'key': 'timelabel', component: 'local_uowvis' },
                { 'key': 'courselabel', component: 'local_uowvis' },
                { 'key': 'nocourses', component: 'local_uowvis' },
                { 'key': 'searchcourses', component: 'local_uowvis' },
            ]);
            this.can_change = can_change;
            this.course_ids = courseids;
            this.all_ids = all_ids;
            this.drawing_div_id = divid;

            this.config = {
                // Margin of graph.
                top: 50, right: 120, bottom: 100, left: 135,
                width: 900,
                // Height of a row for each course (should be greater than circle size by a small margin).
                rowheight: 60,
                // Radius of largest circle (scaled by assessessment value compared to course overall).
                min_radius: 2,
                max_radius: 30,
                legend_radius: 8,
                // How far from the "width" of the graph the legend starts.
                legend_start: 40,
                legend_width: 100,
                x_ticks: 10,
                timelineheight: 100,
            };
            this.date_options = {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            };
            $.when(promises[0], promises[1], strings)
                .done(this.bootstrap_visualisation.bind(this))
                .fail(notification.exception);

        };

        UOWVis.prototype.redraw = function () {
            if(this.redrawing) {
                log.debug('already redrawing, refusing request');
                return;
            }
            this.redrawing = true;
            log.debug('Recreating graph at div with id ' + this.drawing_div_id + ' for following courses '
                + this.course_ids.join(','));

            log.debug('Calling data endpoint');
            var promises = ajax.call([
                { methodname: 'local_uowvis_get_visdata', args: { courseids: this.course_ids } },
                { methodname: 'local_uowvis_get_coursedata', args: { allcourses: this.all_ids, selected: this.course_ids } },
            ]);

            // The canvas we actually draw on (so we can swap/clear it when redrawing).
            var canvas = md3.select("#" + this.drawing_div_id).append('div');
            // Swap out canvas, for the post draw fade in/out.
            this.previous_canvas = this.current_canvas;
            this.current_canvas = canvas;
            this.current_canvas.style('visibility', "hidden");
            this.current_canvas.style('opacity', "0");

            $.when(promises[0], promises[1])
                .done(this.draw_visualisation.bind(this, canvas))
                .fail(notification.exception);
        };

        /**
         * This is used only for the first page init, where strings
         * are also fetched, if the vis object already exists, just redraw it
         * @param {webservice data} data
         * @param {strings we need the first time} strings
         */
        UOWVis.prototype.bootstrap_visualisation = function (data, all_courses, strings) {
            // Get some nice label names.
            this.x_axis_label = strings[0];
            this.y_axis_label = strings[1];
            this.nocourse_label = strings[2];
            this.searchcourses_label = strings[3];
            // The canvas we actually draw on (so we can swap/clear it when redrawing).
            var canvas = md3.select("#" + this.drawing_div_id).append('div');
            this.current_canvas = canvas;
            this.draw_visualisation(canvas, data, all_courses);
        };

        /**
         * Draw the actual visualisation after being prepared
         * @param {the data returned from the webservice} data
         */
        UOWVis.prototype.draw_visualisation = function (canvas, data, all_courses) {
            // Store a copy of the data.
            // Convert unix timestamps to date objects.
            var maxdate = new Date(data.maxdate * 1000);
            var mindate = new Date(data.mindate * 1000);

            // Turn string into int.
            this.totalcourses = parseInt(data.totalcourses);

            this.courseid_to_name_map = {};
            // Calculate the course id -> name map for this graph.
            all_courses.forEach(function (element) {
                this.courseid_to_name_map[element.courseid] = element.shortname;
            }.bind(this));

            var config = this.config;
            this.data = data;

            // Calculate total area width including legend.
            this.totalwidth = config.width + config.left + config.right + config.legend_width;

            // Height of entire graph.
            var graphheight = ((config.rowheight * this.totalcourses));
            // Height of the legend for the graph.
            var legendheight = (data.moduledata.length * ((config.legend_radius * 2) + 10));
            this.height = graphheight;

            // If the legend is larger, make room for it instead.
            if (this.height < legendheight) {
                this.height = legendheight;
            }

            this.totalheight = this.height + config.top + config.bottom;

            // Build our axes based on the data.
            this.x_axis = this.setup_x_axis(
                mindate,
                maxdate,
                config.width
            );

            this.y_axis = this.setup_y_axis(
                data.courseids,
                this.height
            );

            this.z_axis = this.setup_z_axis(
                config.min_radius,
                config.max_radius
            );

            this.color_scale = this.setup_color_scale(data.modulenames);

            this.tooltip = this.setup_tooltip();

            log.info('UoW Bubble Vis drawing graph from response data width:' +
                this.totalwidth +
                ' height:' +
                this.totalheight
            );

            if (this.can_change) {
                // Draw the course selection container.
                var selection_container = canvas
                    .append("div")
                    .classed("uowvis-selection-container", true);
                this.draw_course_selection(
                    selection_container,
                    all_courses
                );
            }

            if (this.course_ids.length > 0) {
                var container = canvas
                    .append("div")
                    // Container class to make it responsive.
                    .classed("svg-container", true);

                // Append the svg responsive container to the body of the page.
                this.vis_canvas = container.append("svg")
                    // Responsive SVG needs these 2 attributes and no width and height attr.
                    .attr("preserveAspectRatio", "xMinYMin meet")
                    // Height and width should match child svg content container or it gets clipped.
                    .attr("viewBox", "0 0 " +
                        this.totalwidth +
                        " " +
                        this.totalheight
                    )

                    // Class to make it responsive.
                    .classed("svg-content-responsive", true)
                    // Now the content SVG and it's graphics.
                    .append("svg")
                    .attr("width", this.totalwidth)
                    .attr("height", this.totalheight)
                    .append("g")
                    .attr("transform",
                        "translate(" + config.left + "," + config.top + ")");

                log.debug("Drawing the x_axis");
                this.draw_x_axis(
                    this.vis_canvas,
                    this.x_axis,
                    config.width,
                    this.height,
                    config.x_ticks,
                    this.x_axis_label
                );

                var ytickFormat = function (d) {
                    return this.courseid_to_name_map[d];
                }.bind(this);

                log.debug("Drawing the y axis");
                this.draw_y_axis(
                    this.vis_canvas,
                    this.y_axis,
                    data.courseids,
                    ytickFormat,
                    this.y_axis_label,
                    config.left * -1
                );

                log.debug("Drawing the grid lines");
                this.draw_gridlines(
                    this.vis_canvas,
                    config.width,
                    this.height,
                    this.x_axis,
                    this.y_axis
                );

                log.debug("Drawing the vis data");
                this.gData = this.vis_canvas
                    .append('g')
                    .attr("class", "data");

                this.draw_assessment_data(
                    this.gData,
                    data.assessment_data,
                    this.x_axis,
                    this.y_axis,
                    this.z_axis,
                    this.color_scale
                );

                log.debug("Drawing legend:" +
                    config.legend_start + " " +
                    config.legend_radius + " " +
                    config.width
                );
                this.draw_legend(
                    this.vis_canvas,
                    data.moduledata,
                    config.legend_start,
                    config.legend_radius,
                    config.width,
                    this.color_scale
                );

                log.debug("Drawing timeline range");
                this.draw_timeline(this.vis_canvas, this.totalheight, config, this.x_axis);

            }
            else {
                canvas
                    .append("text")
                    .text(this.nocourse_label);
            }
            if (this.previous_canvas !== null) {
                log.debug("Clearing old canvas as drawing complete");
                this.previous_canvas.remove();
                this.current_canvas.style('visibility', "visible");
                this.current_canvas.transition()
                    .duration(400)
                    .style("opacity", 1);
                this.previous_canvas.transition()
                    .duration(400)
                    .style("opacity", 0)
                    .remove();
            }
            this.redrawing = false;
        };

        UOWVis.prototype.draw_timeline = function (canvas, totalheight, config, date_axis) {
            var domain = date_axis.domain();
            var mindate = domain[0];
            var maxdate = domain[1];

            var sliderTime = d3_slider
                .sliderHorizontal()
                .min(mindate)
                .max(maxdate)
                .width(config.width)
                .displayFormat(md3.timeFormat('%B %d'))
                .default([mindate, maxdate])
                .fill('#2196f3')
                .on('end', function (val) {
                    this.redraw_data(val);
                }.bind(this));

            var gTime = canvas
                .append('g')
                .attr('transform', 'translate(0,' + (totalheight - config.bottom) + ')');

            gTime.call(sliderTime);
        };

        /*
         *  Redraw the data filtering only to the dates provided in the dates value array
         */
        UOWVis.prototype.redraw_data = function (date_values) {
            var min_date = date_values[0];
            var max_date = date_values[1];
            var used_data = this.data.assessment_data.filter(function (d) {
                var date = new Date(d.duedate * 1000);
                return date > min_date && date < max_date;
            });

            var x_axis = this.setup_x_axis(min_date, max_date, this.config.width);
            this.draw_x_axis(
                this.vis_canvas,
                x_axis,
                this.config.width,
                this.height,
                this.config.x_ticks,
                this.x_axis_label
            );
            this.draw_assessment_data(
                this.gData,
                used_data,
                x_axis,
                this.y_axis,
                this.z_axis,
                this.color_scale
            );
            log.debug("Data and X axis redrawn in response to timeline request");
        };

        /*
         * Draws the gridlines into the graph canvas, to help user track the data
         */
        UOWVis.prototype.draw_gridlines = function (canvas, width, height, x_axis, y_axis) {
            canvas.append("g")
                .attr("class", "uowvis_grid")
                .attr("transform", "translate(0, " + height + ")")
                .call(
                    md3.axisBottom(x_axis)
                        .ticks(5)
                        .tickSize(-height)
                        .tickFormat("")
                );
            canvas.append("g")
                .attr("class", "uowvis_grid")
                .call(
                    md3.axisLeft(y_axis)
                        .ticks(5)
                        .tickSize(-width)
                        .tickFormat("")
                );
        };

        UOWVis.prototype.setup_x_axis = function (mindate, maxdate, width) {
            // Configure X axis (time scale based on assessment due date).
            var x_axis = md3.scaleTime()
                .domain([
                    mindate,
                    maxdate,
                ])
                .range([0, width]).nice();
            return x_axis;
        };

        UOWVis.prototype.draw_x_axis = function (canvas, x_axis, width, height, ticks, label) {
            // Delete if it already exists (for redraw).
            canvas.selectAll(".x-axis").remove();
            canvas.selectAll(".x-axis-label").remove();
            // Draw X axis.
            canvas
                .append("g")
                .attr("class", "x-axis")
                .attr("transform", "translate(0," + height + ")")
                .call(md3.axisBottom(x_axis).ticks(ticks));

            // Add X axis label.
            canvas
                .append("text")
                .attr("class", "x-axis-label")
                .attr("text-anchor", "end")
                .attr("x", width / 2)
                .attr("y", height + 40)
                .text(label);
        };

        UOWVis.prototype.setup_y_axis = function (course_ids, height) {
            // Set up the Y axis scale (categorical based on course).
            var y_axis = md3.scalePoint()
                .domain(course_ids)
                .range([0, height])
                .padding(1);
            return y_axis;
        };

        UOWVis.prototype.draw_y_axis = function (canvas, y_axis, axis_data, tickFormat, label, label_start) {
            // Draw Y axis.
            canvas
                .append("g")
                .attr("class", "y-axis")
                .call(
                    md3.axisLeft(y_axis)
                        .ticks(axis_data).tickFormat(tickFormat)
                );

            // Add Y axis label.
            canvas
                .append("text")
                .attr("text-anchor", "end")
                .attr("x", label_start)
                .attr("y", 3)
                .text(label)
                .attr("text-anchor", "start");
        };

        UOWVis.prototype.setup_z_axis = function (min_radius, max_radius) {
            // Add a scale for bubble size.
            var z_axis = md3.scaleLinear()
                .domain([0, 100])
                .range([min_radius, max_radius]);
            return z_axis;
        };

        UOWVis.prototype.setup_color_scale = function (modulenames) {
            // Add a scale for bubble color.
            var bubble_colours = md3.scaleOrdinal()
                .domain(modulenames)
                .range(md3.schemeTableau10);
            return bubble_colours;
        };

        UOWVis.prototype.setup_tooltip = function () {
            // Add a tooltip div that's used for hovering on the dots.
            var tooltip = md3.select('body')
                .append("div")
                .attr("class", "tooltip")
                .style("background-color", "black")
                .style("border-radius", "5px")
                .style("padding", "10px")
                .style("color", "white");
            return tooltip;
        };

        // Tooltip control functions.
        UOWVis.prototype.showTooltip = function (d) {
            var tooltip = this.tooltip;
            var config = this.config;
            // Fetch the tooltip string from moodle, given these parameters.
            var params = {
                course: d.course,
                name: d.name,
                date: new Date(d.duedate * 1000).toLocaleDateString(undefined, this.date_options),
                grade_percent: d.weight,
            };
            var tooltipstring = str.get_string('tooltip', 'local_uowvis', params);
            // As soon as the string is retrieved, i.e. the promise has been fulfilled.
            // Edit the text of a UI element so that it then is the localized string.
            $.when(tooltipstring).done(function (tooltipStr) {
                tooltip.html(tooltipStr);
            }.bind(this));

            tooltip
                .transition()
                .duration(100);
            tooltip
                .style("opacity", 1)
                .style("left", (md3.event.pageX + config.max_radius) + "px")
                .style("top", (md3.event.pageY + config.max_radius) + "px");
        };

        UOWVis.prototype.moveTooltip = function () {
            var tooltip = this.tooltip;
            var config = this.config;
            tooltip
                .style("left", (md3.event.pageX + config.max_radius) + "px")
                .style("top", (md3.event.pageY + config.max_radius) + "px");
        };

        UOWVis.prototype.hideTooltip = function () {
            this.tooltip
                .style("opacity", 0)
                .style("left", "0px")
                .style("top", "0px");
        };

        UOWVis.prototype.highlight = function (d) {
            // Fade opacity of all bubble groups.
            md3.selectAll("#" + this.drawing_div_id + " .bubbles").style("opacity", .05);
            // Except the one that is hovered.
            md3.selectAll("#" + this.drawing_div_id + " ." + d.type).style("opacity", 0.8);
            log.debug("Highlighting: " + d.type);
        };

        UOWVis.prototype.noHighlight = function () {
            md3.selectAll(".bubbles").style("opacity", 0.8);
        };

        UOWVis.prototype.draw_assessment_data = function (gData, data, x, y, z, color_scale) {
            // Add dots according to the data.
            gData
                .selectAll("circle")
                .data(data)
                .join("circle")
                .attr("cx", function (d) { return x(new Date(d.duedate * 1000)); })
                .attr("cy", function (d) { return y(d.courseid); })
                .attr("r", function (d) { return z(d.weight); })
                .style("fill", function (d) { return color_scale(d.modulename); })
                // Class for doing legend.
                .attr("class", function (d) { return "bubbles " + d.modulename; })
                .style("opacity", "0.8")
                .attr("stroke", "black")
                // Add our tooltip controls.
                .on("mouseover", this.showTooltip.bind(this))
                .on("mousemove", this.moveTooltip.bind(this))
                .on("mouseleave", this.hideTooltip.bind(this));
        };

        UOWVis.prototype.draw_legend = function (canvas, data, legend_start, legend_radius, graph_width, color_scale) {
            // Draw the legend groups.
            canvas
                .selectAll("myrect")
                .data(data)
                .enter()
                .append("circle")
                .attr("cx", graph_width + legend_start)
                .attr("cy", function (d, i) { return i * ((legend_radius * 2) + 10); })
                .attr("r", legend_radius)
                .attr("id", function (d) { return d.type; })
                .style("fill", function (d) { return color_scale(d.type); })
                .on("mouseover", this.highlight.bind(this))
                .on("mouseleave", this.noHighlight.bind(this));

            // Add labels beside legend dots.
            canvas
                .selectAll("mylabels")
                .data(data)
                .enter()
                .append("text")
                .attr("x", graph_width + legend_start + (legend_radius * 2))
                .attr("y", function (d, i) { return (i * ((legend_radius * 2) + 10) + (legend_radius / 2)); })
                .style("fill", function (d) { return color_scale(d.type); })
                .text(function (d) { return d.langstring; })
                .attr("id", function (d) { return d.type; })
                .attr("text-anchor", "left")
                .style("alignment-baseline", "middle")
                .on("mouseover", this.highlight.bind(this))
                .on("mouseleave", this.noHighlight.bind(this));
        };

        UOWVis.prototype.draw_course_selection = function (container, all_courses) {
            var context = new Object;
            context.selected_courses = Array();
            context.divid = this.drawing_div_id;
            context.all_courses = all_courses;
            templates.render('local_uowvis/selection_panel', context)
                .then(function (module, html) {
                    container.html(html);
                    $('#uowvis-course-select-' + module.drawing_div_id).change(module.selected_course.bind(module));
                    // Juice selection box to be a super autocompleter.
                    autocomplete.enhance('#uowvis-course-select-' + module.drawing_div_id, false,
                        'local_uowvis/bubble_vis_datasource',
                        module.searchcourses_label, false, true, '');
                }.bind(null, this)).fail(notification.exception);
        };

        UOWVis.prototype.remove_courseid = function (e) {
            e.preventDefault();
            if(this.redrawing) {
                log.debug("Redrawing, refusing to update courses");
                return;
            }
            var courseid = $(e.currentTarget).data('courseid');
            log.debug('Deletion click event on graph at' + this.drawing_div_id);

            this.course_ids = this.course_ids.filter(function (match, value) {
                return match != value;
            }.bind(null, courseid));
            this.redraw();
        };

        UOWVis.prototype.selected_course = function (e) {
            e.preventDefault();
            if(this.redrawing) {
                log.debug("Redrawing, refusing to update courses");
                return;
            }
            var courseids = $(e.currentTarget).val();
            this.course_ids = courseids;
            this.all_ids = courseids;
            this.redraw();
        };

        // Now create the wrapper for the AMD module.
        var visualisation = {
            init: function (divid, courseids, all_ids, can_change) {
                var vis = new UOWVis();
                vis.init(divid, courseids, all_ids, can_change);
            }

        };
        return /** @alias module:local_uowvis/this */ {
            /**
             * Initialise the visualisation for the current page
             *
             * @method  init
             */
            init: visualisation.init,
        };
    });
