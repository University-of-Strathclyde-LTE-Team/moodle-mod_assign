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
 * This file contains the forms to create and edit an instance of this module
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');


/** Include formslib.php */
require_once ($CFG->libdir.'/formslib.php');
/** Include locallib.php */
require_once($CFG->dirroot . '/mod/assign/feedback/file/locallib.php');

/**
 * Assignment grading options form
 *
 * @package   mod_assign
 * @copyright 2012 NetSpot {@link http://www.netspot.com.au}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assignfeedback_file_batch_upload_files_form extends moodleform {
    /**
     * Define this form - called by the parent constructor
     */
    function definition() {
        global $COURSE, $USER;

        $mform = $this->_form;
        $params = $this->_customdata;

        $mform->addElement('header', '', get_string('batchuploadfilesforusers', 'assignfeedback_file', count($params['users'])));
        $mform->addElement('static', 'userslist', get_string('selectedusers', 'assignfeedback_file'), $params['usershtml']);

        $data = new stdClass();
        $fileoptions = array('subdirs'=>1,
                                'maxbytes'=>$COURSE->maxbytes,
                                'accepted_types'=>'*',
                                'return_types'=>FILE_INTERNAL);

        $data = file_prepare_standard_filemanager($data, 'files', $fileoptions, $params['context'], 'assignfeedback_file', ASSIGNFEEDBACK_BATCHFILE_FILEAREA, $USER->id);

        $mform->addElement('filemanager', 'files_filemanager', '', null, $fileoptions);

        $this->set_data($data);

        $mform->addElement('hidden', 'id', $params['cm']);
        $mform->addElement('hidden', 'operation', 'plugingradingbatchoperation_file_uploadfiles');
        $mform->addElement('hidden', 'action', 'viewpluginpage');
        $mform->addElement('hidden', 'pluginaction', 'uploadfiles');
        $mform->addElement('hidden', 'plugin', 'file');
        $mform->addElement('hidden', 'pluginsubtype', 'assignfeedback');
        $mform->addElement('hidden', 'selectedusers', implode(',', $params['users']));
        $this->add_action_buttons(true, get_string('uploadfiles', 'assignfeedback_file'));

    }

}
