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
 * cheat tracknig reprot to track cheating during online quizzes.
 * The teacher can configure the tracking criteria
 * This uses two methods to track cheatings
 * 1. The candidates who finished the quiz within a time period less than the specified threshold
 * are considered as cheated
 * 2. Ip based tracking. To ckeck two have logged from the same machine simultaneously
 * 3. send messasges to the users
 *
 * @package    quiz
 * @subpackage cheattracking
 * @copyright  2012 Sachintha Rajith
 */
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/quiz/report/attemptsreport.php');
require_once($CFG->dirroot.'/mod/quiz/report/cheattracking/cheattracking_table.php');
require_once($CFG->dirroot . '/mod/quiz/report/cheattracking/cheattrackingsettings_form.php');
require_once($CFG->dirroot . '/message/lib.php');

class quiz_cheattracking_report extends quiz_attempt_report {
    //  const DEFAULT_PAGE_SIZE = 5;
    // const DEFAULT_ORDER = 'random';pp
    protected $users;
    protected $cm;
 

    public function display($quiz, $cm, $course) {
        //define global variables
        global $CFG, $DB, $OUTPUT, $PAGE,$USER, $COURSE,$IP;
        $this->quiz = $quiz;
        $this->cm = $cm;
        $this->course = $course;

        $this->context = get_context_instance(CONTEXT_MODULE, $cm->id);
        //$this->currentgroup = get_current_group($course);
        $download = optional_param('download', '', PARAM_ALPHA);
        //url parameters to pass to quiz url
        $pageoptions = array();
        $pageoptions['id'] = $cm->id;
        $pageoptions['mode'] = 'cheattracking';
        $pageoptions['log_id'] = $USER->id;

        $qmsubselect = quiz_report_qm_filter_select($quiz);

        //quiz url
        $reporturl = new moodle_url('/mod/quiz/report.php', $pageoptions);

        //loading all students
        list($currentgroup, $students, $groupstudents, $allowed) =
                $this->load_relevant_students($cm);
        //   $this->print_header_and_tabs($cm, $course, $quiz, 'cheattracking');

        //GUI object
        $mform = new mod_quiz_report_cheattracking_settings($reporturl,
                array('quiz' => $quiz));


        //get data from gui and store in database table as preferences
        if($fromform = $mform->get_data()) {
            // $threshold_time = $fromfrom
            set_user_preference('threshold time', $fromform->timethreshold);
            set_user_preference('pagesize', $fromform->pagesize);
            set_user_preference('ipcheck', $fromform->ipcheck);
            set_user_preference('timecheck', $fromform->timecheck);


            $pagesize = $fromform->pagesize;

            $threshold_time = $fromform->timethreshold;
            $ipcheck = $fromform->ipcheck;
            $timecheck = $fromform->timecheck;
            // echo'hi';
        }
        else {
            $pagesize = get_user_preferences('pagesize',5);
            $threshold_time = get_user_preferences('threshold time',0);
            $ipcheck = get_user_preferences('ipcheck',1);
            $timecheck = get_user_preferences('timecheck',1);

            $defaults = array('timethreshold'=>$threshold_time,'pagesize'=>$pagesize,'ipcheck'=>$ipcheck,'timecheck'=>$timecheck);

            $mform->set_data($defaults);
        }

        if($pagesize==0) {
            $pagesize += $pagesize+200;

        }
        $displayoptions = array();
        $displayoptions['timethreshold'] = $threshold_time;
        $displayoptions['pagesize'] = $pagesize;
        $displayoptions['ipcheck'] = $ipcheck;
        $displayoptions['timecheck'] = $timecheck;


        $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);
        $courseshortname = format_string($course->shortname, true,
                array('context' => $coursecontext));
        // We only want to show the checkbox to delete attempts
        // if the user has permissions and if the report mode is showing attempts.
        $includecheckboxes = has_capability('mod/quiz:deleteattempts', $this->context);
        //the delete capability
        if (empty($currentgroup) || $groupstudents) {
            if (optional_param('delete', 0, PARAM_BOOL) && confirm_sesskey()) {
                if ($attemptids = optional_param_array('attemptid', array(), PARAM_INT)) {
                    require_capability('mod/quiz:deleteattempts', $this->context);

                    //send messages to each students whose marks got deleted
                    foreach($attemptids as $attemptid) {
                        $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
                        $user->id = $attempt->userid;
                        $this->message_sending($USER,$user,$courseshortname,$quiz->name);

                    }
                    $this->delete_selected_attempts($quiz, $cm, $attemptids, $allowed);

                    redirect($reporturl->out(false, $displayoptions));
                }
            }
        }


        $displaycoursecontext = get_context_instance(CONTEXT_COURSE, $COURSE->id);
        $displaycourseshortname = format_string($COURSE->shortname, true,
                array('context' => $displaycoursecontext));

        //table object
        $table = new quiz_report_cheattracking_table($quiz, $this->context, $qmsubselect,
                $groupstudents, $students, null, $includecheckboxes, $reporturl, $displayoptions);

        $table->define_baseurl($reporturl->out(true, $displayoptions));

        $filename = quiz_report_download_filename("cheattracking",
                $courseshortname, $quiz->name);
        $table->is_downloading($download, $filename,
                $displaycourseshortname . ' ' . format_string($quiz->name, true));



        if ($table->is_downloading()) {
            raise_memory_limit(MEMORY_EXTRA);
        }

        if (!$table->is_downloading()) {
            // Only print headers if not asked to download data
            $this->print_header_and_tabs($cm, $course, $quiz, 'responses');
        }
        //   $qmsubselect = quiz_report_qm_filter_select($quiz);
        $attemptsmode = QUIZ_REPORT_ATTEMPTS_STUDENTS_WITH;


        //displays the preferences GUI
        $mform->display();

        if (!$table->is_downloading()) { //do not print notices when downloading
            if ($strattempthighlight = quiz_report_highlighting_grading_method(
            $quiz, $qmsubselect, null)) {
                echo '<div class="quizattemptcounts">' . $strattempthighlight . '</div>';
            }
        }


        list($fields, $from, $where, $params) =
                $this->base_sql($quiz, $qmsubselect, null, $attemptsmode, $allowed);


        //sql query to get data according to specified threshold time
        $where =  $where." AND (quiza.timefinish - quiza.timestart) > 0
            AND quiza.timefinish - quiza.timestart <".$threshold_time;
        $table->set_sql($fields, $from, $where, $params);


        // Define table columns
        $columns = array();
        $headers = array();


        if (!$table->is_downloading() && $includecheckboxes) {
            $columns[] = 'checkbox';
            $headers[] = null;
        }

        //add relavant columns to the table
        $this->add_user_columns($table, $columns, $headers);

        if (!$table->is_downloading()) {
            $this->add_time_columns($columns, $headers);
        }

        $this->add_grade_columns($quiz, $columns, $headers);
      
      
        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->sortable(true, 'uniqueid');

        $this->configure_user_columns($table);

        $table->column_class('sumgrades', 'bold');
//
        $table->set_attribute('id', 'attempts');
//
        $table->collapsible(true);

       
        
        if($displayoptions['timecheck']==1) {
            echo $OUTPUT->heading('Time Violations');
            $table->out($pagesize, true);

        }else {

            echo $OUTPUT->heading('Time Tracking is disabled!');
        }


        if($displayoptions['ipcheck']==1) {
            echo $OUTPUT->heading('IP Violations');
            $this->display_ip_table($quiz,$qmsubselect,$groupstudents, $students,
                     $includecheckboxes,$reporturl, $displayoptions,$pagesize,$attemptsmode);

        }else {

            echo $OUTPUT->heading('IP Tracking is disabled!');
        }




    }

    protected function add_ip_columns($table,&$columns, &$headers) {
        $columns[] = 'ip';
        $headers[] = 'IP Address';

    }


    //add the comumn to display the quiz score of each students in the table
    protected function add_grade_columns($quiz, &$columns, &$headers) {
        if ($this->should_show_grades($quiz)) {
            $columns[] = 'sumgrades';
            $headers[] = get_string('grade', 'quiz') . '/' .
                    quiz_format_grade($quiz, $quiz->grade);
        }

    }

//message sending method
    protected function message_sending($USER,$touser,$coursename,$quizname) {

        $eventdata = new stdClass();
        $eventdata->component         = 'moodle'; //your component name
        $eventdata->name              = 'instantmessage'; //this is the message name from messages.php
        $touser->emailstop=0;
        $eventdata->userfrom          = $USER;
        $eventdata->userto            = $touser;
        $eventdata->subject           = 'Cheatings';
        $eventdata->fullmessage       = 'Your quiz 2 score for Computer Sequrity has
                                         been cancelled due to cheating';
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = 'Your quiz 2 score for Computer Sequrity has
                                         been cancelled due to cheating';
        $eventdata->smallmessage      = 'Your ' .$quizname. ' score for ' .$coursename. ' has been cancelled due to cheating';
        $eventdata->notification      = 1; //this is only set to 0 for personal messages between users

        message_send($eventdata);


    }

   

    protected function get_ip_violations($quiz) {
        global $DB;

       
        try{
          $DB->execute("create view my as
                            select mdl_quiz_attempts.userid,ip,timestart,timefinish,quiz
                            from mdl_quiz_attempts,mdl_log
                            where mdl_log.userid = mdl_quiz_attempts.userid
                            and action = 'attempt'");
        }catch(Exception $ex){
           // echo '';
        }

        $ip_violators = $DB->get_records_sql('SELECT s1.userid AS u1, s1.ip, s2.userid AS u2
        FROM my AS s1, my AS s2
        WHERE s1.timestart < s2.timestart
        AND s1.timefinish > s2.timestart
        AND s1.ip = s2.ip
        AND s1.quiz='.$quiz->id);
        // $ip_violator = array('userid','ip');
        $ip_violator = array("user"=>array(),"ip"=>array());

        if(!$ip_violators) {
            return false;

        }

        foreach( $ip_violators as $v) {
            $user1 = $v->u1;
            $user2 = $v->u2;
            $ip = $v->ip;
            $ip_violator['user'][]=$user1;
            $ip_violator['ip'][]=$ip;
            $ip_violator['user'][]=$user2;
            $ip_violator['ip'][]=$ip;

        }
        return $ip_violator;
        // return echo $ip_violators[1]->userid;
    }

   

    protected function display_ip_table($quiz,$qmsubselect,$groupstudents, $students,
             $includecheckboxes,$reporturl, $displayoptions,$pagesize,$attemptsmode) {

         
        $table = new quiz_report_cheattracking_table($quiz, $this->context, $qmsubselect,
                $groupstudents, $students, null, $includecheckboxes, $reporturl, $displayoptions);

        $table->define_baseurl($reporturl->out(true, $displayoptions));
        $ip_violations = $this -> get_ip_violations($quiz);

        $ip = array();
        $allowed = array();
        for($i=0;isset($ip_violations['user'][$i]);$i++) {
            $allowed[] = $ip_violations['user'][$i];
            $ip[] = $ip_violations['ip'][$i];

        }
        $table->set_ip($ip);

        if(!isset($allowed[0])){
            $allowed = null;
            
        }
        list($fields, $from, $where, $params) =
                $this->base_sql($quiz, $qmsubselect, null, $attemptsmode, $allowed);
        $table->set_sql($fields, $from, $where, $params);

        // Define table columns
        $columns = array();
        $headers = array();


        if (!$table->is_downloading() && $includecheckboxes) {
            $columns[] = 'checkbox';
            $headers[] = null;
        }

        //add relavant columns to the table
        $this->add_user_columns($table, $columns, $headers);

        if (!$table->is_downloading()) {
            $this->add_time_columns($columns, $headers);
        }

        $this->add_grade_columns($quiz, $columns, $headers);
        $this->add_ip_columns($table,$columns,$headers);

        $table->define_columns($columns);
        $table->define_headers($headers);
        $table->sortable(true);

        $this->configure_user_columns($table);

        $table->column_class('sumgrades', 'bold');

        $table->set_attribute('id', 'attempts');

        $table->collapsible(true);



        $table->out($pagesize, true);


    }

   
}
?>
