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
 * This file defines the setting form for the quiz cheat tracking report.
 *
 * @package    quiz
 * @subpackage cheattracking
 * @copyright  2012 Sachintha Rajith
    
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
class mod_quiz_report_cheattracking_settings extends moodleform {
    protected function definition() {

        $mform = $this->_form;
        //add elements to disolay components in the GUI
        $mform->addElement('header', 'preferencesuser',
                get_string('preferencesuser', 'quiz_overview'));

       

        $mform->addElement('duration', 'timethreshold',
                'Suspecious Threshold Time');
       
        $mform->setType('timethreshold', PARAM_INT);
        $mform->addElement('text', 'pagesize', get_string('pagesize', 'quiz_overview'));
        $mform->setType('pagesize', PARAM_INT);
        // $mform->setDefault('pagesize',1);
        //$mform->addRule('pagesize', 'Invalid Data','positive', null, 'server');

         $mform->addElement('selectyesno', 'timecheck',
                'Enable Time Check');
        
        $mform->addElement('selectyesno', 'ipcheck',
                'Enable IP Check');
       
        $mform->addElement('submit', 'submitbutton',
                get_string('preferencessave', 'quiz_overview'));
    }
}



?>
