<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once("$CFG->libdir/formslib.php");

/**
 * Form to view a list (and possibly add elements to user box).
 *
 * @package   mod_vocabcoach
 * @copyright 2023 onwards, Johannes Funk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Johannes Funk
 */
class view_list_form extends moodleform {
    /**
     * Defines the form.
     * @return void
     */
    public function definition(): void {

        $mform = $this->_form;
        $vocabarray = json_decode($this->_customdata['vocabdata']);

        $id = $this->_customdata['id'];
        $usesthird = $this->_customdata['third_active'] == 1;

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $listid = $this->_customdata['listid'];
        $mform->addElement('hidden', 'listid', $listid);
        $mform->setType('listid', PARAM_INT);

        $tableheaderhtml = '<table id="table-list" class="table generaltable">
        <tbody>
        <tr>
            <th></th>
            <th>Englisch</th>
            <th>Deutsch</th>'.($usesthird ? '<th>Zusatzinfo</th>' : '').'</tr>';
        $mform->addElement('html', $tableheaderhtml);

        foreach ($vocabarray as $vocab) {
            $vocabrow = [];
            $vocabrow[] =& $mform->createElement('html', '<tr><td>');
            $vocabrow[] =& $mform->createElement('checkbox', 'vocab-'.$vocab->dataid);
            $vocabrow[] =& $mform->createElement('html', '</td>');
            $vocabitemhtml = '<td>'.$vocab->front.'</td><td>'.$vocab->back.'</td>';
            if ($usesthird == true) {
                $vocabitemhtml .= '<td>' . $vocab->third . '</td>';
            }
            $vocabrow[] =& $mform->createElement('html', $vocabitemhtml.'</tr>');
            $mform->addGroup(
                    $vocabrow,
                    'vocabrow',
                    '',
                    '',
                    false
            );
        }

        $mform->addElement('html', '</tbody></table>');

        $this->add_action_buttons(true, "Zum eigenen Vokabelkasten hinzufügen");
    }

    /**
     * Validation of form data.
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        return [];
    }
}
