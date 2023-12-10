<?php
// This file is part of Moodle Course Rollover Plugin
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
 * @package     mod_vocabcoach
 * @author      J. Funk
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_vocabcoach;

use dml_exception;
use stdClass;

class vocab_manager {
    private int $userid;

    public function __construct($userid) {
        $this->userid = $userid;
    }

    /**
     * @throws dml_exception
     */
    public function insert_vocab($vocab) : int {
        if ($this->does_vocab_exist($vocab)) {
            return $this->determine_id($vocab);
        } else {
            $vocab->createdby = $this->userid;
            return $this->create_record($vocab);
        }
    }

    private function does_vocab_exist(object $vocab) : bool {
        global $DB;
        $condition1 = $DB->sql_compare_text('front') . '  = ' . $DB->sql_compare_text(':front');
        $condition2 = $DB->sql_compare_text('back') . ' = ' . $DB->sql_compare_text(':back');
        $condition3 = $DB->sql_compare_text('third') . ' = ' . $DB->sql_compare_text(':third');
        $query = "SELECT COUNT(*) FROM {vocabcoach_vocab} WHERE $condition1 AND $condition2 AND $condition3";
        try {
            $count = $DB->count_records_sql($query,
                    ['front' => $vocab->front, 'back' => $vocab->back, 'third' => $vocab->third]);
            return $count > 0;
        } catch (dml_exception $e) {
            return false;
        }
    }

    public function create_record ($vocab) : int {
        global $DB;

        try {
            return $DB->insert_record('vocabcoach_vocab', $vocab);
        } catch (dml_exception $e) {
            return -1;
        }
    }

    /**
     * @throws dml_exception
     */
    private function determine_id($vocab) : int {
        global $DB;

        $condition1 = $DB->sql_compare_text('front') . '  = ' . $DB->sql_compare_text(':front');
        $condition2 = $DB->sql_compare_text('back') . ' = ' . $DB->sql_compare_text(':back');
        $condition3 = $DB->sql_compare_text('third') . ' = ' . $DB->sql_compare_text(':third');

        $query = "SELECT id FROM {vocabcoach_vocab} WHERE $condition1 AND $condition2 AND $condition3";
        $records = $DB->get_records_sql($query, ['front' => $vocab->front, 'back' => $vocab->back, 'third' => $vocab->third], 0, 1);
        return array_values($records)[0]->id;
    }

    /**
     * @throws dml_exception
     */
    public function add_vocab_to_user(int $vocabid, int $cmid) : bool {
        global $DB;

        if ($DB->count_records_select('vocabcoach_vocabdata',
                "vocabid = ? AND userid = ? AND cmid = ?", [$vocabid, $this->userid, $cmid]) > 0) {
            return true;
        } else {
            $newdata = new stdClass();
            $newdata->userid = $this->userid;
            $newdata->vocabid = $vocabid;
            $newdata->cmid = $cmid;
            $newdata->stage = 1;
            $newdata->lastchecked = strtotime('2000-01-01 00:00:00');

            try {
                $DB->insert_record('vocabcoach_vocabdata', $newdata, false);
                return true;
            } catch (dml_exception $e) {
                die($e->getMessage());
            }
        }
    }

    public function add_list(array $listinfo) :int {
        global $DB;

        try {
            return $DB->insert_record('vocabcoach_lists', $listinfo);
        } catch (dml_exception $e) {
            return -1;
        }
    }

    public function add_vocab_to_list (int $vocabid, int $listid) : bool {
        global $DB;
        $conditions = [
            'vocabid' => $vocabid,
            'listid' => $listid,
        ];

        try {
            if ($DB->count_records('vocabcoach_list_contains', $conditions) > 0) {
                return false;
            }
            $DB->insert_record('vocabcoach_list_contains', $conditions);
        } catch (dml_exception $e) {
            return false;
        }
        return true;
    }

    public function remove_vocab_from_list (int $vocabid, int $listid) : bool {
        global $DB;

        try {
            $DB->delete_records('vocabcoach_list_contains', ['vocabid' => $vocabid, 'listid' => $listid]);
        } catch (dml_exception $e) {
            return false;
        }
        return true;
    }

    public function add_list_to_user_database (int $listid, int $cmid) : bool {
        global $DB;

        $time = strtotime('2000-01-01 00:00:00');

        $query = "SELECT id, vocabid FROM {vocabcoach_list_contains} list_contains
                                WHERE list_contains.listid = $listid
                                AND list_contains.vocabid NOT IN
       (SELECT vocabID FROM {vocabcoach_vocabdata} vocabdata WHERE userid = $this->userid AND cmid = $cmid)";

        try {
            $records = $DB->get_records_sql($query);
            $insertarray = [];
            foreach (array_values($records) as $record) {
                $insert = new stdClass();
                $insert->vocabid = $record->vocabid;
                $insert->userid = $this->userid;
                $insert->cmid = $cmid;
                $insert->stage = 1;
                $insert->lastchecked = $time;
                $insertarray[] = $insert;
            }
            $DB->insert_records('vocabcoach_vocabdata', $insertarray);
        } catch (dml_exception $e) {
            return false;
        }

        return true;
    }

    public function edit_list($listid, $vocabarray) :void {
        global $DB;

        foreach ($vocabarray as $vocab) {
            if ($vocab->correct_everywhere) {
                $DB->update_record('vocabcoach_vocab', $vocab);
            } else {
                $this->remove_vocab_from_list($vocab->id, $listid);
                $newid = $this->insert_vocab($vocab);
                $this->add_vocab_to_list($newid, $listid);
            }
        }
    }

    public function user_owns_list ($userid, $listid) : bool {
        global $DB;
        $record = $DB->get_record('vocabcoach_lists', ['id' => $listid], 'createdby');
        return $record->createdby == $userid;
    }
}
