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
 * Custom completion tests for mod_game.
 *
 * @package    mod_game
 * @category   test
 * @copyright  2026 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_game;

use advanced_testcase;
use cm_info;
use coding_exception;
use mod_game\completion\custom_completion;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/game/lib.php');

/**
 * Class for unit testing mod_game/custom_completion.
 *
 * @package    mod_game
 * @category   test
 * @copyright  2026 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_game\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * Data provider for get_state().
     *
     * @return array[]
     */
    public static function get_state_provider(): array {
        return [
            'Undefined rule' => [
                'completionrule', false, null, \coding_exception::class,
            ],
            'Pass grade rule not satisfied' => [
                'completionpass', false, 0, null, // COMPLETION_INCOMPLETE = 0
            ],
            'Pass grade rule satisfied' => [
                'completionpass', true, 1, null, // COMPLETION_COMPLETE = 1
            ],
            'Attempts exhausted rule not satisfied' => [
                'completionattemptsexhausted', false, 0, null, // COMPLETION_INCOMPLETE = 0
            ],
            'Attempts exhausted rule satisfied' => [
                'completionattemptsexhausted', true, 1, null, // COMPLETION_COMPLETE = 1
            ],
        ];
    }

    /**
     * Test for get_state().
     *
     * @dataProvider get_state_provider
     * @param string $rule The custom completion rule.
     * @param bool $rulemet Whether the rule has been met.
     * @param int|null $expectedstate Expected completion state.
     * @param string|null $exception Expected exception.
     */
    public function test_get_state(string $rule, bool $rulemet, ?int $expectedstate, ?string $exception): void {
        global $DB;

        if (!is_null($exception)) {
            $this->expectException($exception);
        }

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course and user.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Create a game activity with completion enabled.
        $params = [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1,
            'completionattemptsexhausted' => 1,
            'grade' => 100,
            'maxattempts' => 3,
        ];
        $game = $this->getDataGenerator()->create_module('game', $params);

        // Get fresh course module info.
        rebuild_course_cache($course->id, true);
        $cminfo = get_fast_modinfo($course->id)->get_cm(get_coursemodule_from_instance('game', $game->id)->id);
        // Set up grade if needed for pass rule.
        if ($rule == 'completionpass') {
            if ($rulemet) {
                // Give user a passing grade.
                $this->set_user_grade($game, $user, 80);
            } else {
                // Give user a failing grade or no grade.
                $this->set_user_grade($game, $user, 40);
            }
        }

        // Set up attempts if needed for attempts exhausted rule.
        if ($rule == 'completionattemptsexhausted') {
            if ($rulemet) {
                // Create maximum attempts.
                for ($i = 0; $i < 3; $i++) {
                    $attempt = new \stdClass();
                    $attempt->gameid = $game->id;
                    $attempt->userid = $user->id;
                    $attempt->timestart = time() - 3600;
                    $attempt->timefinish = time() - 3000;
                    $attempt->score = 50;
                    $DB->insert_record('game_attempts', $attempt);
                }
            } else {
                // Create fewer attempts than maximum.
                $attempt = new \stdClass();
                $attempt->gameid = $game->id;
                $attempt->userid = $user->id;
                $attempt->timestart = time() - 3600;
                $attempt->timefinish = time() - 3000;
                $attempt->score = 50;
                $DB->insert_record('game_attempts', $attempt);
            }
        }

        $customcompletion = new custom_completion($cminfo, (int)$user->id);
        $this->assertEquals($expectedstate, $customcompletion->get_state($rule));
    }

    /**
     * Test for get_defined_custom_rules().
     */
    public function test_get_defined_custom_rules(): void {
        $rules = custom_completion::get_defined_custom_rules();
        $this->assertCount(2, $rules);
        $this->assertContains('completionpass', $rules);
        $this->assertContains('completionattemptsexhausted', $rules);
    }

    /**
     * Test for get_custom_rule_descriptions().
     */
    public function test_get_custom_rule_descriptions(): void {
        $this->resetAfterTest();

        // Create course.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // Create game activity.
        $params = [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ];
        $game = $this->getDataGenerator()->create_module('game', $params);
        $cm = get_fast_modinfo($course->id)->get_cm(get_coursemodule_from_instance('game', $game->id)->id);

        $customcompletion = new custom_completion($cm, 1);
        $descriptions = $customcompletion->get_custom_rule_descriptions();

        $this->assertCount(2, $descriptions);
        $this->assertArrayHasKey('completionpass', $descriptions);
        $this->assertArrayHasKey('completionattemptsexhausted', $descriptions);
        $this->assertNotEmpty($descriptions['completionpass']);
        $this->assertNotEmpty($descriptions['completionattemptsexhausted']);
    }

    /**
     * Test for get_sort_order().
     */
    public function test_get_sort_order(): void {
        $this->resetAfterTest();

        // Create course.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // Create game activity.
        $params = [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ];
        $game = $this->getDataGenerator()->create_module('game', $params);
        $cm = get_fast_modinfo($course->id)->get_cm(get_coursemodule_from_instance('game', $game->id)->id);

        $customcompletion = new custom_completion($cm, 1);
        $sortorder = $customcompletion->get_sort_order();

        $this->assertIsArray($sortorder);
        $this->assertGreaterThan(0, count($sortorder));
        $this->assertContains('completionview', $sortorder);
        $this->assertContains('completionusegrade', $sortorder);
        $this->assertContains('completionpass', $sortorder);
        $this->assertContains('completionattemptsexhausted', $sortorder);
    }

    /**
     * Helper function to set a grade for a user in a game activity.
     *
     * @param object $game Game instance
     * @param object $user User object
     * @param float $grade Grade value
     */
    private function set_user_grade(object $game, object $user, float $grade): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        // Set grade for user using grade_update - this will create the grade item if needed.
        $grades = new \stdClass();
        $grades->userid = $user->id;
        $grades->rawgrade = $grade;

        $params = [
            'itemname' => $game->name,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => 100,
            'grademin' => 0,
            'gradepass' => 60, // Pass grade is 60%
        ];

        grade_update('mod/game', $game->course, 'mod', 'game', $game->id, 0, $grades, $params);
    }
}
