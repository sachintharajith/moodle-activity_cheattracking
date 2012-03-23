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
 * Description of quiz_report_cheattracking_table
 * define the table to display the results of the report
 * @package    quiz
 * @subpackage cheattracking
 * @copyright  2012 Sachintha Rajith
 */

defined('MOODLE_INTERNAL') || die();

class quiz_report_cheattracking_table extends quiz_attempt_report_table{

    private $iplist;
     private $i = 0;
    public function __construct($quiz, $context, $qmsubselect, $groupstudents,
            $students, $questions, $includecheckboxes, $reporturl, $displayoptions) {
        parent::__construct('mod-quiz-report-chaeattracking-report', $quiz, $context,
                $qmsubselect, $groupstudents, $students, $questions, $includecheckboxes,
                $reporturl, $displayoptions);
    }

    //to create table API is used
    public function build_table() {
        if ($this->rawdata) {
            $this->strtimeformat = str_replace(',', ' ', get_string('strftimedatetime'));
            parent::build_table();
        }
    }


    public function col_sumgrades($attempt) {
        if (!$attempt->timefinish) {
            return '-';
        }

        //rescale grades to 100
        $grade = quiz_rescale_grade($attempt->sumgrades, $this->quiz);
        if ($this->is_downloading()) {
            return $grade;
        }

        //link grade with the overview
        $gradehtml = '<a href="review.php?q=' . $this->quiz->id . '&amp;attempt=' .
                $attempt->attempt . '">' . $grade . '</a>';
        return $gradehtml;
    }
    public function set_ip($ip){
        $this->iplist = $ip;

    }

     public function col_ip() {
         $ip =   $this -> iplist[$this->i];
         $this->i++;
         return $ip;
     }
   
}
?>
