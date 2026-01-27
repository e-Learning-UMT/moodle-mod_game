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
 * Completion lib tests for mod_game.
 *
 * @package    mod_game
 * @category   test
 * @copyright  2026 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_game;

use advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/game/lib.php');

/**
 * Class for unit testing mod_game completion functions in lib.php.
 *
 * @package    mod_game
 * @category   test
 * @copyright  2026 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     ::game_get_completion_state
 */
class completion_lib_test extends advanced_testcase {

    /**
     * Test game_supports() returns correct values for completion features.
     */
    public function test_game_supports_completion_features(): void {
        $this->assertTrue(game_supports(FEATURE_COMPLETION_TRACKS_VIEWS));
        $this->assertTrue(game_supports(FEATURE_COMPLETION_HAS_RULES));
    }

    /**
     * Test game_get_completion_state with completion disabled.
     */
    public function test_game_get_completion_state_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course and user.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        // Create game activity.
        $game = $this->getDataGenerator()->create_module('game', [
            'course' => $course->id,
            'completion' => COMPLETION_DISABLED,
        ]);

        $cm = get_coursemodule_from_instance('game', $game->id);

        // Should return the type parameter when completion is disabled.
        $result = game_get_completion_state($course, $cm, $user->id, COMPLETION_AND);
        $this->assertEquals(COMPLETION_AND, $result);

        $result = game_get_completion_state($course, $cm, $user->id, COMPLETION_OR);
        $this->assertEquals(COMPLETION_OR, $result);
    }

    /**
     * Test game_get_completion_state with passing grade requirement.
     */
    public function test_game_get_completion_state_with_pass_grade(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course and user.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Create game activity with pass grade requirement.
        $game = $this->getDataGenerator()->create_module('game', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionpass' => 1,
            'grade' => 100,
        ]);

        // Update game record to ensure completionpass is set.
        $DB->set_field('game', 'completionpass', 1, ['id' => $game->id]);
        $game = $DB->get_record('game', ['id' => $game->id]);

        $cm = get_coursemodule_from_instance('game', $game->id);

        // Create grade item with pass grade.
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'game',
            'iteminstance' => $game->id,
        ]);

        if (!$gradeitem) {
            $gradeitem = new \grade_item();
            $gradeitem->courseid = $course->id;
            $gradeitem->itemtype = 'mod';
            $gradeitem->itemmodule = 'game';
            $gradeitem->iteminstance = $game->id;
            $gradeitem->itemname = $game->name;
            $gradeitem->gradetype = GRADE_TYPE_VALUE;
            $gradeitem->grademax = 100;
            $gradeitem->grademin = 0;
            $gradeitem->gradepass = 60;
            $gradeitem->insert();
        } else {
            $gradeitem->gradepass = 60;
            $gradeitem->update();
        }

        // Test without grade - should return false.
        $result = game_get_completion_state($course, $cm, $user->id, COMPLETION_AND);
        $this->assertFalse($result);

        // Give user a failing grade.
        $gradegrade = new \grade_grade();
        $gradegrade->itemid = $gradeitem->id;
        $gradegrade->userid = $user->id;
        $gradegrade->rawgrade = 40;
        $gradegrade->finalgrade = 40;
        $gradegrade->insert();

        // Should still return false.
        $result = game_get_completion_state($course, $cm, $user->id, COMPLETION_AND);
        $this->assertFalse($result);

        // Update to passing grade.
        $gradegrade->rawgrade = 80;
        $gradegrade->finalgrade = 80;
        $gradegrade->update();

        // Should now return true.
        $result = game_get_completion_state($course, $cm, $user->id, COMPLETION_AND);
        $this->assertTrue($result);
    }

    /**
     * Test game_get_completion_state with grade item but no pass requirement.
     */
    public function test_game_get_completion_state_with_grade_no_pass(): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create course and user.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Create game activity with grade but no pass requirement.
        $game = $this->getDataGenerator()->create_module('game', [
            'course' => $course->id,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completiongradeitemnumber' => 0,
            'completionpass' => 0,
            'grade' => 100,
        ]);

        $cm = get_coursemodule_from_instance('game', $game->id);
        $cm->completiongradeitemnumber = 0;

        // Create grade item.
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'game',
            'iteminstance' => $game->id,
        ]);

        if (!$gradeitem) {
            $gradeitem = new \grade_item();
            $gradeitem->courseid = $course->id;
            $gradeitem->itemtype = 'mod';
            $gradeitem->itemmodule = 'game';
            $gradeitem->iteminstance = $game->id;
            $gradeitem->itemname = $game->name;
            $gradeitem->gradetype = GRADE_TYPE_VALUE;
            $gradeitem->grademax = 100;
            $gradeitem->grademin = 0;
            $gradeitem->insert();
        }

        // Test without grade - should return false.
        $result = game_get_completion_state($course, $cm, $user->id, COMPLETION_AND);
        $this->assertFalse($result);

        // Give user any grade.
        $gradegrade = new \grade_grade();
        $gradegrade->itemid = $gradeitem->id;
        $gradegrade->userid = $user->id;
        $gradegrade->rawgrade = 50;
        $gradegrade->finalgrade = 50;
        $gradegrade->insert();

        // Should return true (any grade completes it).
        $result = game_get_completion_state($course, $cm, $user->id, COMPLETION_AND);
        $this->assertTrue($result);
    }
}
