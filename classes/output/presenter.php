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

namespace local_roleexplainer\output;

use context;
use core_user;
use local_roleexplainer\permission_analyser;

/**
 * Converts analysis arrays into Mustache-friendly data without being a Moodle renderable.
 *
 * @package local_roleexplainer
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class presenter {
    /**
     * Prepare template data.
     *
     * @param array $analysis Deterministic analysis.
     * @param array|null $ai AI explanation.
     * @param string|null $aierror AI error message.
     * @param array $links Native Moodle links.
     * @return array
     */
    public function prepare(array $analysis, ?array $ai, ?string $aierror, array $links): array {
        $user = core_user::get_user($analysis['userid'], '*', MUST_EXIST);
        $targetcontext = context::instance_by_id($analysis['contextid'], MUST_EXIST);

        $contexts = [];
        foreach ($analysis['contexts'] as $item) {
            $contexts[] = [
                'id' => $item['id'],
                'level' => $this->context_level_name($item['contextlevel']),
                'name' => $item['name'],
                'instanceid' => $item['instanceid'],
                'istarget' => $item['id'] === $analysis['contextid'],
            ];
        }

        $sourcesbyrole = [];
        foreach ($analysis['rolesources'] as $source) {
            $labels = [];
            foreach ($source['sources'] as $item) {
                $label = $this->role_source_name($item['type']);
                if ($item['contextid']) {
                    $sourcecontext = context::instance_by_id((int)$item['contextid'], IGNORE_MISSING);
                    if ($sourcecontext) {
                        $label .= ': ' . $sourcecontext->get_context_name() . ' (#' . $item['contextid'] . ')';
                    } else {
                        $label .= ' #' . $item['contextid'];
                    }
                }
                $labels[] = $label;
            }
            $sourcesbyrole[$source['roleid']] = implode('; ', $labels);
        }

        $roles = [];
        foreach ($analysis['roles'] as $role) {
            $rules = [];
            foreach ($role['rules'] as $rule) {
                $rules[] = [
                    'contextid' => $rule['contextid'],
                    'contextname' => $rule['contextname'],
                    'permission' => $this->permission_name($rule['permission']),
                    'source' => $this->rule_source_name($rule['source']),
                    'stored' => $rule['stored'],
                    'effective' => $rule['effective_for_role'],
                    'prohibit' => $rule['prohibit'],
                    'rowclass' => $rule['prohibit'] ? 'table-danger' : ($rule['effective_for_role'] ? 'table-active' : ''),
                ];
            }

            $rolename = trim($role['name']) !== '' ? format_string($role['name']) : $role['shortname'];
            if ($rolename === '') {
                $rolename = '#' . $role['roleid'];
            }
            $roles[] = [
                'roleid' => $role['roleid'],
                'rolename' => $rolename,
                'shortname' => $role['shortname'],
                'archetype' => $role['archetype'] !== '' ? $role['archetype'] : '-',
                'sources' => $sourcesbyrole[$role['roleid']] ?? '',
                'effectivepermission' => $this->permission_name($role['effectivepermission']),
                'hasprohibit' => $role['hasprohibit'],
                'allows' => $role['allows'],
                'rules' => $rules,
            ];
        }

        $aidata = null;
        if ($ai !== null) {
            $aidata = [
                'summary' => format_text($ai['summary'], FORMAT_PLAIN),
                'explanation' => format_text($ai['explanation'], FORMAT_PLAIN),
                'decisive_rules' => array_map(static fn(string $item): array => ['text' => $item], $ai['decisive_rules']),
                'suggested_checks' => array_map(static fn(string $item): array => ['text' => $item], $ai['suggested_checks']),
                'hasdecisive' => !empty($ai['decisive_rules']),
                'haschecks' => !empty($ai['suggested_checks']),
            ];
        }

        return [
            'allowed' => $analysis['officialresult'],
            'resultlabel' => get_string($analysis['officialresult'] ? 'allowed' : 'denied', 'local_roleexplainer'),
            'resultclass' => $analysis['officialresult'] ? 'success' : 'danger',
            'userdisplay' => fullname($user) . ' (#' . $user->id . ')',
            'capability' => $analysis['capability'],
            'contextdisplay' => $targetcontext->get_context_name() . ' (#' . $targetcontext->id . ')',
            'doanything' => get_string($analysis['doanything'] ? 'yes' : 'no', 'local_roleexplainer'),
            'siteadmin' => $analysis['siteadmin'],
            'withoutdoanything' => get_string($analysis['withoutdoanything'] ? 'allowed' : 'denied', 'local_roleexplainer'),
            'showwithoutdoanything' => $analysis['siteadmin'],
            'reason' => $this->reason_name($analysis['reason']),
            'capabilitymissing' => !$analysis['capabilityexists'],
            'contexts' => $contexts,
            'roles' => $roles,
            'hasroles' => !empty($roles),
            'ai' => $aidata,
            'aierror' => $aierror ? get_string('aiunavailable', 'local_roleexplainer', $aierror) : null,
            'links' => $links,
            'haslinks' => !empty($links),
        ];
    }

    /**
     * Permission label.
     *
     * @param int|null $permission Permission constant.
     * @return string
     */
    private function permission_name(?int $permission): string {
        return get_string(permission_analyser::permission_key($permission), 'local_roleexplainer');
    }

    /**
     * Context level label.
     *
     * @param int $level Context level.
     * @return string
     */
    private function context_level_name(int $level): string {
        $map = [
            CONTEXT_SYSTEM => 'system',
            CONTEXT_COURSECAT => 'coursecategory',
            CONTEXT_COURSE => 'course',
            CONTEXT_MODULE => 'module',
            CONTEXT_BLOCK => 'block',
            CONTEXT_USER => 'usercontext',
        ];
        return get_string($map[$level] ?? 'othercontext', 'local_roleexplainer');
    }

    /**
     * Role source label.
     *
     * @param string $source Source key.
     * @return string
     */
    private function role_source_name(string $source): string {
        $map = [
            'explicit' => 'explicitassignment',
            'defaultuser' => 'defaultuserrole',
            'frontpage' => 'frontpagerole',
            'runtime' => 'runtimeassignment',
            'switched' => 'switchedrole',
            'guest' => 'guestrole',
        ];
        $key = $map[$source] ?? 'runtimeassignment';
        return get_string($key, 'local_roleexplainer');
    }

    /**
     * Rule source label.
     *
     * @param string $source Source key.
     * @return string
     */
    private function rule_source_name(string $source): string {
        if ($source === 'definition') {
            return get_string('definition', 'local_roleexplainer');
        }
        if ($source === 'override') {
            return get_string('override', 'local_roleexplainer');
        }
        return get_string('norule', 'local_roleexplainer');
    }

    /**
     * Deterministic reason label.
     *
     * @param string $reason Reason key.
     * @return string
     */
    private function reason_name(string $reason): string {
        $map = [
            'missing_capability' => 'capabilitymissingreason',
            'guest_risky' => 'guestrisky',
            'context_locked' => 'contextlocked',
            'invalid_context_path' => 'invalidcontext',
            'loginas_restriction' => 'loginasrestricted',
            'site_admin' => 'adminshortcut',
            'prohibit' => 'prohibitdecisive',
            'role_allow' => 'roleaggregatedallow',
            'role_deny' => 'roleaggregateddeny',
        ];
        return get_string($map[$reason] ?? 'roleaggregateddeny', 'local_roleexplainer');
    }
}
