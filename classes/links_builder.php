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

use context;
use moodle_url;

/**
 * Builds links to Moodle's native role administration screens when the viewer is authorised.
 *
 * @package local_roleexplainer
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class links_builder {
    /**
     * Build links for an analysis.
     *
     * @param array $analysis Deterministic analysis.
     * @return array
     */
    public function build(array $analysis): array {
        $context = context::instance_by_id($analysis['contextid'], MUST_EXIST);
        $links = [];

        if (has_capability('moodle/role:review', $context)) {
            $links[] = [
                'label' => get_string('permissionslink', 'local_roleexplainer'),
                'url' => (new moodle_url('/admin/roles/permissions.php', [
                    'contextid' => $context->id,
                    'capability' => $analysis['capability'],
                ]))->out(false),
            ];
        }

        if (has_capability('moodle/role:assign', $context)) {
            $links[] = [
                'label' => get_string('assignroleslink', 'local_roleexplainer'),
                'url' => (new moodle_url('/admin/roles/assign.php', ['contextid' => $context->id]))->out(false),
            ];
        }

        if (has_capability('moodle/role:override', $context) || has_capability('moodle/role:safeoverride', $context)) {
            $overridableroles = get_overridable_roles($context, ROLENAME_BOTH);
            foreach ($analysis['roles'] as $role) {
                if (!isset($overridableroles[$role['roleid']])) {
                    continue;
                }
                $links[] = [
                    'label' => get_string('overridelink', 'local_roleexplainer') . ': ' . s($overridableroles[$role['roleid']]),
                    'url' => (new moodle_url('/admin/roles/override.php', [
                        'contextid' => $context->id,
                        'roleid' => $role['roleid'],
                    ]))->out(false),
                ];
            }
        }

        return $links;
    }
}
