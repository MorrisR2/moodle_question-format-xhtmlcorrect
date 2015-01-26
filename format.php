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
 * XHTML question exporter.
 *
 * @package    qformatcorrect
 * @subpackage xhtmlcorrect
 * @copyright  2005 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * XHTML question exporter.
 *
 * Exports questions as static HTML.
 *
 * @copyright  2005 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qformat_xhtmlcorrect extends qformat_default {

    public function provide_export() {
        return true;
    }

    protected function repchar($text) {
        return $text;
    }

    protected function writequestion($question) {
        global $OUTPUT;
        // turns question into string
        // question reflects database fields for general question and specific to type

        // if a category switch, just ignore
        if ($question->qtype=='category') {
            return '';
        }

        // initial string;
        $expout = "";
        $id = $question->id;

        $ishidden = '';
        if ($question->hidden) {
            $ishidden = 'hiddenquestion';
        }
        // add comment and div tags
        $expout .= "<div class=\"question $ishidden\">\n";

        // add header
        $expout .= "<h3>$question->name</h3>\n";
        $defaultmark = preg_replace('/[0|\.]*$/', '', 'Default value: ' . $question->defaultmark);
        $expout .= '<p class="">'.$defaultmark.'</p>';
        // Format and add the question text
        $expout .= '<p class="questiontext">' . format_text($question->questiontext,
                $question->questiontextformat) . "</p>\n";

        // selection depends on question type
        switch($question->qtype) {
        case TRUEFALSE:
        case 'truefalse':
            $expout .= "<ul class=\"truefalse\">\n";
            foreach($question->options->answers as $answer) {
                $ans_text = $this->repchar( $answer->answer );
                if ($answer->fraction == 1) {
                    $correctnessclass = 'correctness_correct';
                } elseif ($answer->fraction > 0) {
                    $correctnessclass = 'correctness_partial';
                } else {
                    $correctnessclass = 'correctness_incorrect';
                }
                $expout .= "  <li class=\"$correctnessclass\">$ans_text</li>\n";
            }
            $expout .= "</ul>\n";
            break;
        case MULTICHOICE:
        case 'multichoice':
            $expout .= "<ul class=\"multichoice\">\n";
            foreach($question->options->answers as $answer) {
                $ans_text = $this->repchar( $answer->answer );
                if ($answer->fraction == 1) {
                    $correctnessclass = 'correctness_correct';
                } elseif ($answer->fraction > 0) {
                    $correctnessclass = 'correctness_partial';
                } else {
                    $correctnessclass = 'correctness_incorrect';
                }
                $expout .= "  <li class=\"$correctnessclass\">$ans_text</li>\n";
            }
            $expout .= "</ul>\n";
            break;
        case SHORTANSWER:
        case 'shortanswer':
            $expout .= "<ul class=\"shortanswer\">\n";
            $expout .= "  <li><input name=\"quest_$id\" type=\"text\" /></li>\n";
            $expout .= "</ul>\n";
            break;
        case NUMERICAL:
        case 'numerical':
            $expout .= "<ul class=\"numerical\">\n";
            $expout .= "  <li><input name=\"quest_$id\" type=\"text\" /></li>\n";
            $expout .= "</ul>\n";
            break;
        case MATCH:
        case 'match':
            $expout .= "<ul class=\"match\">\n";

            // build answer list
            $ans_list = array();
            foreach($question->options->subquestions as $subquestion) {
               $ans_list[] = $this->repchar( $subquestion->answertext );
            }
            shuffle( $ans_list ); // random display order

            // build drop down for answers
            $dropdown = "<select name=\"quest_$id\">\n";
            foreach($ans_list as $ans) {
                $dropdown .= "<option value=\"" . s($ans) . "\">" . s($ans) . "</option>\n";
            }
            $dropdown .= "</select>\n";

            // finally display
            foreach($question->options->subquestions as $subquestion) {
              $quest_text = $this->repchar( $subquestion->questiontext );
              $expout .= "  <li>$quest_text</li>\n";
              $expout .= $dropdown;
            }
            $expout .= "</ul>\n";
            break;
        case DESCRIPTION:
        case 'description':
            break;
        case MULTIANSWER:
        case 'multianswer':
            $expout .= "<!-- CLOZE type is not supported  -->\n";
            break;
        default:
            echo $OUTPUT->notification("No handler for qtype $question->qtype" );
        }
        // close off div
        $expout .= "</div>\n\n\n";
        return $expout;
    }


    protected function presave_process($content) {
        // override method to allow us to add xhtml headers and footers

        global $CFG;

        // get css bit
        $css_lines = file( "$CFG->dirroot/question/format/xhtmlcorrect/xhtml.css" );
        $css = implode( ' ',$css_lines );

        $xp =  "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"\n";
        $xp .= "  \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">\n";
        $xp .= "<html xmlns=\"http://www.w3.org/1999/xhtml\">\n";
        $xp .= "<head>\n";
        $xp .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=UTF-8\" />\n";
        $xp .= "<title>Moodle Quiz XHTML Export</title>\n";
        $xp .= $css;
        $xp .= "</head>\n";
        $xp .= "<body>\n";
        $xp .= $this->get_custom_headers();
        $xp .= $content;
        $xp .= "</body>\n";
        $xp .= "</html>\n";

        return $xp;
    }

    protected function get_custom_headers() {
        global $OUTPUT;
        global $DB;

        // $pagevars['cat'] = optional_param('cat', 0, PARAM_SEQUENCE);
        /*
        list($catid, $catcontext) = explode(',', required_param('cat', PARAM_STRING));
        list($catid, $catcontext) = explode(',', $pagevars['cat']);
        echo "list($catid, $catcontext)\n";
        $category = $DB->get_record('question_categories', array("id" => $catid, 'contextid' => $catcontext), '*', MUST_EXIST);
        */
 
        $xp = $OUTPUT->heading($this->category->name, 3, 'catname');
        $xp .= date(DATE_RFC850);
        return $xp;
    }

    public function export_file_extension() {
        return '.html';
    }
}

