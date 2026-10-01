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

namespace local_roleexplainer;

use advanced_testcase;
use context;
use context_course;
use context_module;
use context_system;

/**
 * Permission analyser tests.
 *
 * Each scenario compares the plugin's official result with Moodle's has_capability().
 *
 * @package local_roleexplainer
 * @covers \local_roleexplainer\permission_analyser
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class permission_analyser_test extends advanced_testcase {
    /** @var permission_analyser */
    private permission_analyser $analyser;

    /**
     * Set up each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->analyser = new permission_analyser();
    }

    /**
     * Create a custom role and assign it to a user.
     *
     * @param int $userid User ID.
     * @param int $contextid Assignment context ID.
     * @param string $suffix Unique suffix.
     * @return int Role ID.
     */
    private function create_assigned_role(int $userid, int $contextid, string $suffix): int {
        $roleid = create_role('Role ' . $suffix, 'role_' . $suffix, 'Role for role explainer tests');
        role_assign($roleid, $userid, $contextid);
        return $roleid;
    }

    /**
     * Assert plugin and Moodle agree.
     *
     * @param int $userid User ID.
     * @param int $contextid Context ID.
     * @param string $capability Capability.
     * @return array Analysis.
     */
    private function assert_matches_core(int $userid, int $contextid, string $capability): array {
        $context = context::instance_by_id($contextid, MUST_EXIST);
        $expected = has_capability($capability, $context, $userid);
        $analysis = $this->analyser->analyse($userid, $contextid, $capability);
        $this->assertSame($expected, $analysis['officialresult']);
        return $analysis;
    }

    /**
     * ALLOW grants the capability.
     */
    public function test_allow_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $roleid = $this->create_assigned_role($user->id, $context->id, 'allow');
        assign_capability('moodle/course:update', CAP_ALLOW, $roleid, $context->id);

        $analysis = $this->assert_matches_core($user->id, $context->id, 'moodle/course:update');
        $this->assertTrue($analysis['officialresult']);
        $this->assertTrue($analysis['roleaggregate']['result']);
    }

    /**
     * A closer PREVENT overrides an inherited ALLOW within the same role.
     */
    public function test_prevent_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $system = context_system::instance();
        $context = context_course::instance($course->id);
        $roleid = $this->create_assigned_role($user->id, $context->id, 'prevent');
        assign_capability('moodle/course:update', CAP_ALLOW, $roleid, $system->id);
        assign_capability('moodle/course:update', CAP_PREVENT, $roleid, $context->id);

        $analysis = $this->assert_matches_core($user->id, $context->id, 'moodle/course:update');
        $this->assertFalse($analysis['officialresult']);
        $this->assertFalse($analysis['roleaggregate']['result']);
    }

    /**
     * PROHIBIT denies even when a lower context contains ALLOW.
     */
    public function test_prohibit_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $system = context_system::instance();
        $context = context_course::instance($course->id);
        $roleid = $this->create_assigned_role($user->id, $context->id, 'prohibit');
        assign_capability('moodle/course:update', CAP_PROHIBIT, $roleid, $system->id);
        assign_capability('moodle/course:update', CAP_ALLOW, $roleid, $context->id);

        $analysis = $this->assert_matches_core($user->id, $context->id, 'moodle/course:update');
        $this->assertFalse($analysis['officialresult']);
        $this->assertTrue($analysis['roleaggregate']['hasprohibit']);
        $this->assertSame('prohibit', $analysis['reason']);
    }

    /**
     * A system-level role definition is inherited by a course/module context.
     */
    public function test_inherited_permission_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $coursecontext = context_course::instance($course->id);
        $modulecontext = context_module::instance($forum->cmid);
        $system = context_system::instance();
        $roleid = $this->create_assigned_role($user->id, $coursecontext->id, 'inherit');
        assign_capability('mod/forum:replypost', CAP_ALLOW, $roleid, $system->id);

        $analysis = $this->assert_matches_core($user->id, $modulecontext->id, 'mod/forum:replypost');
        $this->assertTrue($analysis['officialresult']);
        $this->assertTrue($analysis['roleaggregate']['result']);
    }

    /**
     * PREVENT in one role does not cancel ALLOW in another role.
     */
    public function test_multiple_roles_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);
        $preventrole = $this->create_assigned_role($user->id, $context->id, 'multi_prevent');
        $allowrole = $this->create_assigned_role($user->id, $context->id, 'multi_allow');
        assign_capability('moodle/course:update', CAP_PREVENT, $preventrole, $context->id);
        assign_capability('moodle/course:update', CAP_ALLOW, $allowrole, $context->id);

        $analysis = $this->assert_matches_core($user->id, $context->id, 'moodle/course:update');
        $this->assertTrue($analysis['officialresult']);
        $this->assertContains($allowrole, $analysis['roleaggregate']['allowroles']);
    }

    /**
     * A module override is closer than the inherited role definition.
     */
    public function test_module_override_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $coursecontext = context_course::instance($course->id);
        $modulecontext = context_module::instance($forum->cmid);
        $system = context_system::instance();
        $roleid = $this->create_assigned_role($user->id, $coursecontext->id, 'module_override');
        assign_capability('mod/forum:replypost', CAP_ALLOW, $roleid, $system->id);
        assign_capability('mod/forum:replypost', CAP_PREVENT, $roleid, $modulecontext->id);

        $analysis = $this->assert_matches_core($user->id, $modulecontext->id, 'mod/forum:replypost');
        $this->assertFalse($analysis['officialresult']);
        $this->assertFalse($analysis['roleaggregate']['result']);
    }

    /**
     * A course override affects descendant module contexts.
     */
    public function test_course_override_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id]);
        $coursecontext = context_course::instance($course->id);
        $modulecontext = context_module::instance($forum->cmid);
        $system = context_system::instance();
        $roleid = $this->create_assigned_role($user->id, $coursecontext->id, 'course_override');
        assign_capability('mod/forum:replypost', CAP_ALLOW, $roleid, $system->id);
        assign_capability('mod/forum:replypost', CAP_PREVENT, $roleid, $coursecontext->id);

        $analysis = $this->assert_matches_core($user->id, $modulecontext->id, 'mod/forum:replypost');
        $this->assertFalse($analysis['officialresult']);
    }

    /**
     * Site administrators use Moodle's doanything shortcut unless another earlier gate applies.
     */
    public function test_admin_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        set_config('siteadmins', (string)$user->id);
        accesslib_clear_all_caches(true);
        $context = context_system::instance();

        $analysis = $this->assert_matches_core($user->id, $context->id, 'moodle/site:config');
        $this->assertTrue($analysis['officialresult']);
        $this->assertTrue($analysis['adminshortcut']);
        $this->assertSame('site_admin', $analysis['reason']);
    }

    /**
     * Unknown capabilities return false, matching Moodle core behavior.
     */
    public function test_missing_capability_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();
        $capability = 'local/roleexplainer:doesnotexist';

        $this->expect_debugging('Capability "' . $capability . '" was not found! This has to be fixed in code.');
        $analysis = $this->analyser->analyse($user->id, $context->id, $capability);
        $this->assertFalse($analysis['officialresult']);
        $this->assertFalse($analysis['capabilityexists']);
        $this->assertSame('missing_capability', $analysis['reason']);
    }

    /**
     * A user with no explicit role remains denied for a manager-only plugin capability.
     */
    public function test_user_without_explicit_role_matches_has_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $context = context_system::instance();

        $analysis = $this->assert_matches_core($user->id, $context->id, 'local/roleexplainer:use');
        $this->assertFalse($analysis['officialresult']);
        $this->assertFalse($analysis['roleaggregate']['result']);
    }
}
