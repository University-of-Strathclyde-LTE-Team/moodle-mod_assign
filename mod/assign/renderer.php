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
 * This file contains a renderer for the assignment class
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/** Include locallib.php */
require_once($CFG->dirroot . '/mod/assign/locallib.php');


/**
 * A custom renderer class that extends the plugin_renderer_base and
 * is used by the assign module.
 *
 * @package mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class mod_assign_renderer extends plugin_renderer_base {

    // used to cache grade menus (and other repetitive things)
    private $cache = array();
      
    /**
     * rendering assignment files 
     * 
     * @param object $context
     * @param int $userid
     * @param string $filearea
     * @return string
     */
    public function assign_files($context, $userid, $filearea='submission') {
        return $this->render(new assign_files($context, $userid, $filearea));
    }

    /**
     * rendering assignment files 
     * 
     * @param assign_files $tree
     * @return mixed
     */
    public function render_assign_files(assign_files $tree) {
        $module = array('name'=>'mod_assign_files', 'fullpath'=>'/mod/assign/assign.js', 'requires'=>array('yui2-treeview'));
        $this->htmlid = 'assign_files_tree_'.uniqid();
        $this->page->requires->js_init_call('M.mod_assign.init_tree', array(true, $this->htmlid));
        $html = '<div id="'.$this->htmlid.'">';
        $html .= $this->htmllize_tree($tree, $tree->dir);
        $html .= '</div>';

        if ($tree->portfolioform) {
            $html .= $tree->portfolioform;
        }
        return $html;
    }
    
    /**
     * Utility function to add a row of data to a table with 2 columns. Modified
     * the table param and does not return a value
     * 
     * @param object $t The table to append the row of data to
     * @param string $first The first column text
     * @param string $second The second column text
     * @return None
     */
    private function add_table_row_tuple($t, $first, $second) {
        $row = new html_table_row();
        $cell1 = new html_table_cell($first);
        $cell2 = new html_table_cell($second);
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
    }
    
    /**
     * Render the grading form
     * 
     * @return None
     */
    public function render_grading_form(grading_form $form) {
        $o = '';
        $o .= $this->output->heading(get_string('grade'), 3);
        $o .= $this->output->box_start('boxaligncenter gradingform');
        $o .= $this->moodleform($form->get_form());
        $o .= $this->output->box_end();
        return $o;
    }
    
    /**
     * Render the user summary
     * 
     * @return None
     */
    public function render_user_summary(user_summary $summary) {
        $o = '';

        if (!$summary->get_user()) {
            return;
        }
        $o .= $this->output->container_start('usersummary');
        $o .= $this->output->box_start('boxaligncenter usersummarysection');
        $o .= $this->output->user_picture($summary->get_user());
        $o .= $this->output->spacer(array('width'=>30));
        $o .= $this->output->action_link(new moodle_url('/user/view.php', array('id' => $summary->get_user()->id, 'course'=>$summary->get_assignment()->get_course()->id)), fullname($summary->get_user(), has_capability('moodle/site:viewfullnames', $summary->get_assignment()->get_course_context())));
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();
        
        return $o;
    }

    /**
     * Page is done - render the footer
     * 
     * @return None
     */
    public function render_footer() {
        return $this->output->footer();
    }

    /**
     * render the edit submission form
     *
     * @param edit_submission_form $form
     * @return string
     */
    public function render_edit_submission_form(edit_submission_form $editform) {
        $o = '';
    
        $o .= $this->output->container_start('editsubmission');
        $o .= $this->output->heading(get_string('submission', 'assign'), 3);
        $o .= $this->output->box_start('boxaligncenter editsubmissionform');



        $o .= $this->moodleform($editform->get_form());
        
        $o .= $this->output->box_end();
        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * render the header
     * 
     * @param assignment_header $header
     * @return string
     */
    public function render_assignment_header(assignment_header $header) {
        $o = '';

        if ($header->get_sub_page()) {
            $this->page->navbar->add($header->get_sub_page());
        }

        $this->page->set_title(get_string('pluginname', 'assign'));
        $this->page->set_heading($header->get_assignment()->get_instance()->name);

        $o .= $this->output->header();
        $o .= $this->output->heading($header->get_assignment()->get_instance()->name);

        if ($header->get_show_intro()) {
            if ($header->get_assignment()->get_instance()->alwaysshowdescription || 
                    time() > $header->get_assignment()->get_instance()->allowsubmissionsfromdate) {
                $o .= $this->output->box_start('generalbox boxaligncenter', 'intro');
                $o .= format_module_intro('assign', $header->get_assignment()->get_instance(), $header->get_assignment()->get_course_module()->id);
                $o .= $this->output->box_end();
            }
        }
        return $o;
    }
    
    /**
     * render a table containing the current status of the grading process
     * 
     * @param grading_summary $summary
     * @return string
     */
    public function render_grading_summary(grading_summary $summary) {
        // create a table for the data
        $o = '';
        $o .= $this->output->container_start('gradingsummary');
        $o .= $this->output->heading(get_string('gradingsummary', 'assign'), 3);
        $o .= $this->output->box_start('boxaligncenter gradingsummarytable');
        $t = new html_table();

        // status
        $this->add_table_row_tuple($t, get_string('numberofparticipants', 'assign'), 
                                   $summary->get_assignment()->count_enrolled_users_with_capability('mod/assign:submit'));

        // drafts
        if ($summary->get_assignment()->get_instance()->submissiondrafts) {
            $this->add_table_row_tuple($t, get_string('numberofdraftsubmissions', 'assign'), 
                                       $summary->get_assignment()->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_DRAFT));
       }

        // submitted for grading
        $this->add_table_row_tuple($t, get_string('numberofsubmittedassignments', 'assign'), 
                                       $summary->get_assignment()->count_submissions_with_status(ASSIGN_SUBMISSION_STATUS_SUBMITTED));

        $time = time();
        if ($summary->get_assignment()->get_instance()->duedate) {
            // due date
            // submitted for grading
            $duedate = $summary->get_assignment()->get_instance()->duedate;
            $this->add_table_row_tuple($t, get_string('duedate', 'assign'), 
                                       userdate($duedate));

            // time remaining
            $due = '';
            if ($duedate - $time <= 0) {
                $due = get_string('assignmentisdue', 'assign');
            } else {
                $due = format_time($duedate - $time);
            }
            $this->add_table_row_tuple($t, get_string('timeremaining', 'assign'), $due);
        }

        // all done - write the table
        $o .= html_writer::table($t);
        $o .= $this->output->box_end();
        
        // link to the grading page
        $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php',
            array('id' => $summary->get_assignment()->get_course_module()->id ,'action'=>'grading')), get_string('viewgrading', 'assign'), 'get');

        // close the container and insert a spacer
        $o .= $this->output->container_end();

        return $o;
    }
    
    /**
     *  Return a grade in user-friendly form, whether it's a scale or not
     *
     * @global object $DB
     * @param mixed $grade
     * @return string User-friendly representation of grade
     */
    private function format_grade($grade, $assignment) {
        global $DB;

        static $scalegrades = array();

                                        

        if ($assignment->get_instance()->grade >= 0) {    // Normal number
            if ($grade == -1 || $grade === null) {
                return '-';
            } else {
                return round($grade) .' / '.$assignment->get_instance()->grade;
            }

        } else {                                // Scale
            if (empty($this->cache['scale'])) {
                if ($scale = $DB->get_record('scale', array('id'=>-($assignment->get_instance()->grade)))) {
                    $this->cache['scale'] = make_menu_from_list($scale->scale);
                } else {
                    return '-';
                }
            }
            if (isset($this->cache['scale'][$grade])) {
                return $this->cache['scale'][$grade];
            }
            return '-';
        }
    }
    
    /**
     * render a table containing all the current grades and feedback
     * 
     * @param feedback_status $status
     * @return string
     */
    public function render_feedback_status(feedback_status $status) {
        global $DB, $CFG;
        $o = '';
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->dirroot.'/grade/grading/lib.php');

        $assignment_grade = $status->get_grade();
        if (!$assignment_grade) {
            return '';
        }
        $o .= $this->output->container_start('feedback');
        $o .= $this->output->heading(get_string('feedback', 'assign'), 3);
        $o .= $this->output->box_start('boxaligncenter feedbacktable');
        $t = new html_table();
        
        $grading_info = grade_get_grades($status->get_assignment()->get_course()->id, 
                                        'mod', 
                                        'assign', 
                                        $status->get_assignment()->get_instance()->id, 
                                        $assignment_grade->userid);

        $item = $grading_info->items[0];
        $grade = $item->grades[$assignment_grade->userid];

        if ($grade->hidden or $grade->grade === false) { // hidden or error
            return '';
        }

        if ($grade->grade === null and empty($grade->str_feedback)) {   /// Nothing to show yet
            return '';
        }

        $graded_date = $grade->dategraded;

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('grade', 'assign'));

        $grading_manager = get_grading_manager($status->get_assignment()->get_context(), 'mod_assign', 'submissions');
    
        if ($controller = $grading_manager->get_active_controller()) {
            $controller->set_grade_range(make_grades_menu($status->get_assignment()->get_instance()->grade));
            $cell2 = new html_table_cell($controller->render_grade($this->page, $assignment_grade->id, $item, $grade->str_long_grade, has_capability('mod/assign:grade', $status->get_assignment()->get_context())));
        } else {

            $cell2 = new html_table_cell($this->format_grade($grade->str_long_grade, $status->get_assignment()));
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
        
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradedon', 'assign'));
        $cell2 = new html_table_cell(userdate($graded_date));
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
        
        if ($grader = $DB->get_record('user', array('id'=>$grade->usermodified))) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('gradedby', 'assign'));
            $cell2 = new html_table_cell($this->output->user_picture($grader) . $this->output->spacer(array('width'=>30)) . fullname($grader));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }
    
        foreach ($status->get_assignment()->get_feedback_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $row = new html_table_row();
                $cell1 = new html_table_cell($plugin->get_name());
                $plugin_feedback = new feedback_plugin_feedback($status->get_assignment(), $plugin, $status->get_grade(), feedback_plugin_feedback::SUMMARY);
                $cell2 = new html_table_cell($this->render($plugin_feedback));
                $row->cells = array($cell1, $cell2);
                $t->data[] = $row;
            }
        }
 

        $o .= html_writer::table($t);
        $o .= $this->output->box_end();
        
        $o .= $this->output->container_end();
        return $o;
    }

    /**
     * render a table containing the current status of the submission
     * 
     * @param submission_status $status
     * @return string
     */
    public function render_submission_status(submission_status $status) {
        $o = '';
        $o .= $this->output->container_start('submissionstatus');
        $o .= $this->output->heading(get_string('submissionstatusheading', 'assign'), 3);
        $time = time();
        if ($status->get_view() == $status::STUDENT_VIEW && 
            !$status->get_assignment()->is_any_submission_plugin_enabled()) {

            $o .= $this->output->box_start('generalbox boxaligncenter nosubmissionrequired');
            $o .= get_string('noonlinesubmissions', 'assign');
            $o .= $this->output->box_end();
        }

        if ($status->get_assignment()->get_instance()->allowsubmissionsfromdate &&
                $time <= $status->get_assignment()->get_instance()->allowsubmissionsfromdate) {
            $o .= $this->output->box_start('generalbox boxaligncenter submissionsalloweddates');
            $o .= get_string('allowsubmissionsfromdatesummary', 'assign', userdate($this->instance->allowsubmissionsfromdate));
            $o .= $this->output->box_end();
        } 
        $o .= $this->output->box_start('boxaligncenter submissionsummarytable');

        $t = new html_table();

        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('submissionstatus', 'assign'));
        if ($status->get_submission()) {
            $cell2 = new html_table_cell(get_string('submissionstatus_' . $status->get_submission()->status, 'assign'));
        } else {
            $cell2 = new html_table_cell(get_string('nosubmission', 'assign'));
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;
        
        // status
        if ($status->is_locked()) {
            $row = new html_table_row();
            $cell1 = new html_table_cell();
            $cell2 = new html_table_cell(get_string('submissionslocked', 'assign'));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        }

        // grading status
        $row = new html_table_row();
        $cell1 = new html_table_cell(get_string('gradingstatus', 'assign'));

        if ($status->is_graded()) {
            $cell2 = new html_table_cell(get_string('graded', 'assign'));
        } else {
            $cell2 = new html_table_cell(get_string('notgraded', 'assign'));
        }
        $row->cells = array($cell1, $cell2);
        $t->data[] = $row;

        
        $duedate = $status->get_assignment()->get_instance()->duedate;
        if ($duedate >= 1) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('duedate', 'assign'));
            $cell2 = new html_table_cell(userdate($duedate));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
            
            // time remaining
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('timeremaining', 'assign'));
            if ($duedate - $time <= 0) {
                if (!$status->get_submission() || $status->get_submission()->status != ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
                    $cell2 = new html_table_cell(get_string('overdue', 'assign', format_time($time - $duedate)));
                } else {
                    if ($status->get_submission()->timemodified > $duedate) {
                        $cell2 = new html_table_cell(get_string('submittedlate', 'assign', format_time($status->get_submission()->timemodified - $duedate)), 'latesubmission');
                    } else {
                        $cell2 = new html_table_cell(get_string('submittedearly', 'assign', format_time($status->get_submission()->timemodified - $duedate)), 'submittedearly');
                    }
                }
            } else {
                $cell2 = new html_table_cell(format_time($duedate - $time));
            }
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;
        } 

        // last modified 
        if ($status->get_submission()) {
            $row = new html_table_row();
            $cell1 = new html_table_cell(get_string('timemodified', 'assign'));
            $cell2 = new html_table_cell(userdate($status->get_submission()->timemodified));
            $row->cells = array($cell1, $cell2);
            $t->data[] = $row;

            foreach ($status->get_assignment()->get_submission_plugins() as $plugin) {
                if ($plugin->is_enabled() && $plugin->is_visible()) {
                    $row = new html_table_row();
                    $cell1 = new html_table_cell($plugin->get_name());
                    $plugin_submission = new submission_plugin_submission($status->get_assignment(), $plugin, $status->get_submission(), submission_plugin_submission::SUMMARY);
                    $cell2 = new html_table_cell($this->render($plugin_submission));
                    $row->cells = array($cell1, $cell2);
                    $t->data[] = $row;
                }
            }
        }

        
        $o .= html_writer::table($t);
        $o .= $this->output->box_end();
    
        // links
        if ($status->get_show_edit()) {
            $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php',
                array('id' => $status->get_assignment()->get_course_module()->id, 'action' => 'editsubmission')), get_string('editsubmission', 'assign'), 'get');
        }

        if ($status->get_submission() && $status->get_submission()->status == ASSIGN_SUBMISSION_STATUS_DRAFT) {
            // submission.php test
            $o .= $this->output->single_button(new moodle_url('/mod/assign/view.php',
                    array('id' => $status->get_assignment()->get_course_module()->id, 'action'=>'submit')), get_string('submitassignment', 'assign'), 'get');
            $o .= $this->output->box_start('boxaligncenter submithelp');
            $o .= get_string('submitassignment_help', 'assign');
            $o .= $this->output->box_end();
        }
        
        $o .= $this->output->container_end();
        return $o;
    }
    
    /**
     * render a submission plugin submission
     * 
     * @param submission_plugin_submission $submission_plugin
     * @return string
     */
    public function render_submission_plugin_submission(submission_plugin_submission $submission_plugin) {
        $o = '';

        if ($submission_plugin->get_view() == submission_plugin_submission::SUMMARY) {
            $icon = $this->output->pix_icon('t/preview', get_string('view' . substr($submission_plugin->get_plugin()->get_subtype(), strlen('assign')), 'mod_assign'));
            $link = '';
            if ($submission_plugin->get_plugin()->show_view_link($submission_plugin->get_submission())) {
                $link = $this->output->action_link(
                                new moodle_url('/mod/assign/view.php', 
                                               array('id' => $submission_plugin->get_assignment()->get_course_module()->id, 
                                                     'sid'=>$submission_plugin->get_submission()->id, 
                                                     'plugin'=>$submission_plugin->get_plugin()->get_type(), 
                                                     'action'=>'viewplugin' . $submission_plugin->get_plugin()->get_subtype(), 
                                                     'returnaction'=>$submission_plugin->get_assignment()->get_return_action(), 
                                                     'returnparams'=>http_build_query($submission_plugin->get_assignment()->get_return_params()))), 
                                $icon);
            
                $link .= $this->output->spacer(array('width'=>15));
            }
            
            $o .= $link . $submission_plugin->get_plugin()->view_summary($submission_plugin->get_submission());
        }
        if ($submission_plugin->get_view() == submission_plugin_submission::FULL) {
            $o .= $this->output->box_start('boxaligncenter submissionfull');
            $o .= $submission_plugin->get_plugin()->view($submission_plugin->get_submission());
            $o .= $this->output->box_end();
        }

        return $o;
    }
    
    /**
     * render the grading table
     * 
     * @param grading_table $table
     * @return string
     */
    public function render_grading_table(grading_table $table) {
        $o = '';

        $o .= $this->output->box_start('boxaligncenter gradingtable');
        // need to get from prefs
        $o .= $table->out($table->get_rows_per_page(), true);
        $o .= $this->output->box_end();

        $o .= $this->output->spacer(array('height'=>30));
        $contextname = print_context_name($table->get_assignment()->get_context());

        $o .= $this->output->container_start('gradingnavigation');
        $o .= $this->output->container_start('backlink');
        $o .= $this->output->action_link(new moodle_url('/mod/assign/view.php', array('id' => $table->get_assignment()->get_course_module()->id)), get_string('backto', '', $contextname));
        $o .= $this->output->container_end();
        if (has_capability('gradereport/grader:view', $table->get_assignment()->get_course_context()) && has_capability('moodle/grade:viewall', $table->get_assignment()->get_course_context())) {
            $o .= $this->output->container_start('gradebooklink');
            $o .= $this->output->action_link(new moodle_url('/grade/report/grader/index.php', array('id' => $table->get_assignment()->get_course()->id)), get_string('viewgradebook', 'assign'));
            $o .= $this->output->container_end();
        }
        if ($table->get_assignment()->is_any_submission_plugin_enabled()) {
            $o .= $this->output->container_start('downloadalllink');
            $o .= $this->output->action_link(new moodle_url('/mod/assign/view.php', array('id' => $table->get_assignment()->get_course_module()->id, 'action' => 'downloadall')), get_string('downloadall', 'assign'));
            $o .= $this->output->container_end();
        }

        $o .= $this->output->container_end();

        return $o;
    }

    /**
     * render a feedback plugin feedback
     * 
     * @param feedback_plugin_feedback $feedback_plugin
     * @return string
     */
    public function render_feedback_plugin_feedback(feedback_plugin_feedback $feedback_plugin) {
        $o = '';

        if ($feedback_plugin->get_view() == feedback_plugin_feedback::SUMMARY) {
            $icon = $this->output->pix_icon('t/preview', get_string('view' . substr($feedback_plugin->get_plugin()->get_subtype(), strlen('assign')), 'mod_assign'));
            $link = '';
            if ($feedback_plugin->get_plugin()->show_view_link($feedback_plugin->get_grade())) {
                $link = $this->output->action_link(
                                new moodle_url('/mod/assign/view.php', 
                                               array('id' => $feedback_plugin->get_assignment()->get_course_module()->id, 
                                                     'gid'=>$feedback_plugin->get_grade()->id, 
                                                     'plugin'=>$feedback_plugin->get_plugin()->get_type(), 
                                                     'action'=>'viewplugin' . $feedback_plugin->get_plugin()->get_subtype(), 
                                                     'returnaction'=>$feedback_plugin->get_assignment()->get_return_action(), 
                                                     'returnparams'=>http_build_query($feedback_plugin->get_assignment()->get_return_params()))), 
                                $icon);
                $link .= $this->output->spacer(array('width'=>15));
            }
            
            $o .= $link . $feedback_plugin->get_plugin()->view_summary($feedback_plugin->get_grade());
        }
        if ($feedback_plugin->get_view() == feedback_plugin_feedback::FULL) {
            $o .= $this->output->box_start('boxaligncenter feedbackfull');
            $o .= $feedback_plugin->get_plugin()->view($feedback_plugin->get_grade());
            $o .= $this->output->box_end();
        }

        return $o;
    }
    

        
    /**
     * Internal function - creates htmls structure suitable for YUI tree.
     * 
     * @global object $CFG
     * @param object $tree
     * @param array $dir
     * @return string 
     */
    protected function htmllize_tree($tree, $dir) {
        global $CFG;
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';

        if (empty($dir['subdirs']) and empty($dir['files'])) {
            return '';
        }

        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $this->output->pix_icon("f/folder", $subdir['dirname'], 'moodle', array('class'=>'icon'));
            $result .= '<li yuiConfig=\''.json_encode($yuiconfig).'\'><div>'.$image.' '.s($subdir['dirname']).'</div> '.$this->htmllize_tree($tree, $subdir).'</li>';
        }

        foreach ($dir['files'] as $file) {
            $filename = $file->get_filename();
            $icon = mimeinfo("icon", $filename);
            if ($CFG->enableplagiarism) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $plagiarsmlinks = plagiarism_get_links(array('userid'=>$file->get_userid(), 'file'=>$file, 'cmid'=>$tree->cm->id, 'course'=>$tree->course));
            } else {
                $plagiarsmlinks = '';
            }
            $image = $this->output->pix_icon("f/$icon", $filename, 'moodle', array('class'=>'icon'));
            $result .= '<li yuiConfig=\''.json_encode($yuiconfig).'\'><div>'.$image.' '.$file->fileurl.' '.$plagiarsmlinks.$file->portfoliobutton.'</div></li>';
        }

        $result .= '</ul>';

        return $result;
    }


    /**
     * Helper method dealing with the fact we can not just fetch the output of moodleforms
     *
     * @param moodleform $mform
     * @return string HTML
     */
    protected function moodleform(moodleform $mform) {

        $o = '';
        ob_start();
        $mform->display();
        $o = ob_get_contents();
        ob_end_clean();

        return $o;
    }
    
}
