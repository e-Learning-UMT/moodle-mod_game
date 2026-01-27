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
 * Activity custom completion subclass for the game activity.
 *
 * @package   mod_game
 * @copyright 2021 Vasilis Daloukas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace mod_game\completion;

use core_completion\activity_custom_completion;

/**
 * Activity custom completion subclass for the game activity.
 *
 * Class for defining mod_game's custom completion rules and fetching the completion statuses
 * of the custom completion rules for a given game instance and a user.
 *
 * @package   mod_game
 * @copyright 2021 Vasilis Daloukas
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the list of custom completion rules that are being used by this activity module instance.
     *
     * Overridden to get the rules from the game instance settings instead of customdata.
     *
     * @return array
     */
    public function get_available_custom_rules(): array {
        global $DB;

        $game = $DB->get_record('game', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $availablerules = [];

        if (!empty($game->completionpass)) {
            $availablerules[] = 'completionpass';
        }
        if (!empty($game->completionattemptsexhausted)) {
            $availablerules[] = 'completionattemptsexhausted';
        }

        return $availablerules;
    }

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $game = $DB->get_record('game', ['id' => $this->cm->instance], '*', MUST_EXIST);
        $userid = $this->userid;

        if ($rule == 'completionpass') {
            $status = COMPLETION_INCOMPLETE;
            
            if (!empty($game->completionpass)) {
                // Check for passing grade.
                $item = \grade_item::fetch([
                    'courseid' => $this->cm->course,
                    'itemtype' => 'mod',
                    'itemmodule' => 'game',
                    'iteminstance' => $this->cm->instance,
                    'outcomeid' => null
                ]);
                
                if ($item) {
                    $grades = \grade_grade::fetch_users_grades($item, [$userid], false);
                    if (!empty($grades[$userid]) && $grades[$userid]->is_passed($item)) {
                        $status = COMPLETION_COMPLETE;
                    }
                }
            }
            
            return $status;
        }

        if ($rule == 'completionattemptsexhausted') {
            $status = COMPLETION_INCOMPLETE;
            
            if (!empty($game->completionattemptsexhausted)) {
                // Check if user has exhausted all attempts.
                // Get the number of attempts made by the user.
                $attempts = $DB->count_records('game_attempts', [
                    'gameid' => $game->id,
                    'userid' => $userid
                ]);
                
                // If attempts are unlimited (0) or maxattempts not set, this rule doesn't apply.
                if (!empty($game->maxattempts) && $attempts >= $game->maxattempts) {
                    $status = COMPLETION_COMPLETE;
                }
            }
            
            return $status;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            'completionpass',
            'completionattemptsexhausted',
        ];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            'completionpass' => get_string('completiondetail_pass', 'game'),
            'completionattemptsexhausted' => get_string('completiondetail_attemptsexhausted', 'game'),
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionusegrade',
            'completionpass',
            'completionattemptsexhausted',
        ];
    }
}
