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
use context_course;
use context_system;
use core_user;
use moodle_exception;
use stdClass;

/**
 * Builds a deterministic trace of Moodle capability evaluation.
 *
 * The authoritative answer is always returned by has_capability(). The trace mirrors the
 * role aggregation rules so administrators can see the stored evidence that contributed
 * to that answer, without asking an LLM to calculate permissions.
 *
 * @package   local_roleexplainer
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_analyser {
    /**
     * Analyse a capability for a user and context.
     *
     * @param int $userid Target user ID.
     * @param int $contextid Context ID.
     * @param string $capability Capability name.
     * @param bool $doanything Whether the normal site-admin shortcut is enabled.
     * @return array
     */
    public function analyse(int $userid, int $contextid, string $capability, bool $doanything = true): array {
        global $CFG, $USER;

        $user = core_user::get_user($userid, 'id, username, firstname, lastname, deleted', IGNORE_MISSING);
        if (!$user || $user->deleted) {
            throw new moodle_exception('invaliduser', 'local_roleexplainer');
        }

        $context = context::instance_by_id($contextid, IGNORE_MISSING);
        if (!$context) {
            throw new moodle_exception('invalidcontext', 'local_roleexplainer');
        }

        $capinfo = get_capability_info($capability);
        $capabilityexists = (bool)$capinfo;

        // This call is the authoritative result. For an unknown capability Moodle returns false.
        $officialresult = has_capability($capability, $context, $userid, $doanything);
        $siteadmin = is_siteadmin($userid);
        $withoutdoanything = $siteadmin && $capabilityexists
            ? has_capability($capability, $context, $userid, false)
            : $officialresult;

        $paths = $this->get_paths_bottom_up($context);
        $contexts = $this->get_context_chain($paths);

        $gates = [];
        $decisivegate = null;
        if (!$capabilityexists) {
            $decisivegate = 'missing_capability';
            $gates[] = ['key' => 'missing_capability', 'triggered' => true, 'decisive' => true];
        } else {
            $riskyguest = (($capinfo->captype === 'write') ||
                    ((int)$capinfo->riskbitmask & (RISK_XSS | RISK_CONFIG | RISK_DATALOSS))) &&
                (isguestuser($userid) || $userid === 0);
            $gates[] = ['key' => 'guest_risky', 'triggered' => $riskyguest, 'decisive' => $riskyguest];
            if ($riskyguest) {
                $decisivegate = 'guest_risky';
            }

            $contextlocked = !empty($CFG->contextlocking) &&
                $capinfo->captype === 'write' &&
                !empty($context->locked) &&
                $capinfo->name !== 'moodle/site:managecontextlocks' &&
                (!$siteadmin || !empty($CFG->contextlockappliestoadmin));
            $gates[] = [
                'key' => 'context_locked',
                'triggered' => $contextlocked,
                'decisive' => $contextlocked && $decisivegate === null,
            ];
            if ($contextlocked && $decisivegate === null) {
                $decisivegate = 'context_locked';
            }

            $invalidpath = empty($context->path) || (int)$context->depth === 0;
            $gates[] = [
                'key' => 'invalid_context_path',
                'triggered' => $invalidpath,
                'decisive' => $invalidpath && $decisivegate === null,
            ];
            if ($invalidpath && $decisivegate === null) {
                $decisivegate = 'invalid_context_path';
            }

            $loginasrestricted = false;
            if (!empty($USER->loginascontext) && $USER->loginascontext instanceof context) {
                $loginasrestricted = !$USER->loginascontext->is_parent_of($context, true);
            }
            $gates[] = [
                'key' => 'loginas_restriction',
                'triggered' => $loginasrestricted,
                'decisive' => $loginasrestricted && $decisivegate === null,
            ];
            if ($loginasrestricted && $decisivegate === null) {
                $decisivegate = 'loginas_restriction';
            }
        }

        $switchroleid = $this->get_active_switched_role($userid, $paths);
        $adminshortcut = $capabilityexists && $doanything && $siteadmin && $decisivegate === null &&
            ($userid !== (int)$USER->id || $switchroleid === null);
        $gates[] = [
            'key' => 'site_admin',
            'triggered' => $adminshortcut,
            'decisive' => $adminshortcut,
        ];
        if ($adminshortcut) {
            $decisivegate = 'site_admin';
        }

        $roles = $this->resolve_applicable_roles($userid, $paths, $switchroleid);
        $roletraces = $capabilityexists
            ? $this->build_role_traces(array_keys($roles), $paths, $capability, $contexts)
            : [];

        $roleaggregate = $this->aggregate_role_traces($roletraces);
        $reason = $this->determine_reason($decisivegate, $roleaggregate);

        return [
            'userid' => $userid,
            'contextid' => $contextid,
            'capability' => $capability,
            'capabilityexists' => $capabilityexists,
            'capabilityinfo' => $capabilityexists ? [
                'name' => $capinfo->name,
                'component' => $capinfo->component,
                'captype' => $capinfo->captype,
                'contextlevel' => (int)$capinfo->contextlevel,
                'riskbitmask' => (int)$capinfo->riskbitmask,
            ] : null,
            'officialresult' => (bool)$officialresult,
            'withoutdoanything' => (bool)$withoutdoanything,
            'doanything' => $doanything,
            'siteadmin' => $siteadmin,
            'adminshortcut' => $adminshortcut,
            'switchroleid' => $switchroleid,
            'gates' => $gates,
            'reason' => $reason,
            'contexts' => array_values(array_reverse($contexts)),
            'paths_bottom_up' => $paths,
            'rolesources' => array_values($roles),
            'roles' => $roletraces,
            'roleaggregate' => $roleaggregate,
        ];
    }

    /**
     * Get the target context path and all parent paths, bottom to top, exactly as accesslib evaluates them.
     *
     * @param context $context Target context.
     * @return array
     */
    private function get_paths_bottom_up(context $context): array {
        $path = $context->path;
        if (empty($path)) {
            return [];
        }

        $paths = [$path];
        while ($path = rtrim($path, '0123456789')) {
            $path = rtrim($path, '/');
            if ($path === '') {
                break;
            }
            $paths[] = $path;
        }
        return $paths;
    }

    /**
     * Build context metadata keyed by context path.
     *
     * @param array $paths Context paths from target to system.
     * @return array
     */
    private function get_context_chain(array $paths): array {
        $contexts = [];
        foreach ($paths as $path) {
            $parts = explode('/', trim($path, '/'));
            $contextid = (int)end($parts);
            $ctx = context::instance_by_id($contextid, MUST_EXIST);
            $contexts[$path] = [
                'id' => (int)$ctx->id,
                'path' => $ctx->path,
                'depth' => (int)$ctx->depth,
                'contextlevel' => (int)$ctx->contextlevel,
                'instanceid' => (int)$ctx->instanceid,
                'name' => $ctx->get_context_name(false, true),
            ];
        }
        return $contexts;
    }

    /**
     * Detect a switched role affecting the target context for the current user.
     *
     * @param int $userid Target user ID.
     * @param array $paths Context paths from target to system.
     * @return int|null
     */
    private function get_active_switched_role(int $userid, array $paths): ?int {
        global $USER;

        if ($userid !== (int)$USER->id || empty($USER->access['rsw'])) {
            return null;
        }
        foreach ($paths as $path) {
            if (!empty($USER->access['rsw'][$path])) {
                return (int)$USER->access['rsw'][$path];
            }
        }
        return null;
    }

    /**
     * Determine all role IDs used by Moodle in the target path and annotate where they came from.
     *
     * @param int $userid Target user ID.
     * @param array $paths Context paths from target to system.
     * @param int|null $switchroleid Active switched role ID, if any.
     * @return array Keyed by role ID.
     */
    private function resolve_applicable_roles(int $userid, array $paths, ?int $switchroleid): array {
        global $CFG, $DB, $USER;

        $roles = [];
        if ($switchroleid !== null) {
            $this->add_role_source($roles, $switchroleid, 'switched', null, null);
            if (!empty($CFG->defaultuserroleid)) {
                $this->add_role_source(
                    $roles,
                    (int)$CFG->defaultuserroleid,
                    'defaultuser',
                    (int)context_system::instance()->id,
                    context_system::instance()->path
                );
            }
            return $this->attach_role_metadata($roles);
        }

        if (isguestuser($userid)) {
            $guestrole = get_guest_role();
            if ($guestrole) {
                $this->add_role_source(
                    $roles,
                    (int)$guestrole->id,
                    'guest',
                    (int)context_system::instance()->id,
                    context_system::instance()->path
                );
            }
            return $this->attach_role_metadata($roles);
        }

        if (!empty($CFG->defaultuserroleid)) {
            $systemcontext = context_system::instance();
            $this->add_role_source(
                $roles,
                (int)$CFG->defaultuserroleid,
                'defaultuser',
                (int)$systemcontext->id,
                $systemcontext->path
            );
        }

        if (!empty($CFG->defaultfrontpageroleid)) {
            $frontpagecontext = context_course::instance(SITEID);
            if (in_array($frontpagecontext->path, $paths, true)) {
                $this->add_role_source(
                    $roles,
                    (int)$CFG->defaultfrontpageroleid,
                    'frontpage',
                    (int)$frontpagecontext->id,
                    $frontpagecontext->path
                );
            }
        }

        $contextids = [];
        $pathbycontextid = [];
        foreach ($paths as $path) {
            $parts = explode('/', trim($path, '/'));
            $contextid = (int)end($parts);
            $contextids[] = $contextid;
            $pathbycontextid[$contextid] = $path;
        }

        if ($contextids) {
            [$insql, $params] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctx');
            $params['userid'] = $userid;
            $sql = "SELECT ra.id, ra.roleid, ra.contextid
                      FROM {role_assignments} ra
                     WHERE ra.userid = :userid
                       AND ra.contextid {$insql}";
            foreach ($DB->get_records_sql($sql, $params) as $assignment) {
                $this->add_role_source(
                    $roles,
                    (int)$assignment->roleid,
                    'explicit',
                    (int)$assignment->contextid,
                    $pathbycontextid[(int)$assignment->contextid] ?? null
                );
            }
        }

        // Temporary course roles only exist in the current user's runtime access data.
        if ($userid === (int)$USER->id && !empty($USER->access['ra'])) {
            foreach ($paths as $path) {
                if (empty($USER->access['ra'][$path])) {
                    continue;
                }
                $parts = explode('/', trim($path, '/'));
                $contextid = (int)end($parts);
                foreach ($USER->access['ra'][$path] as $roleid) {
                    $roleid = (int)$roleid;
                    $hassourceatcontext = false;
                    if (isset($roles[$roleid])) {
                        foreach ($roles[$roleid]['sources'] as $source) {
                            if ((int)($source['contextid'] ?? 0) === $contextid) {
                                $hassourceatcontext = true;
                                break;
                            }
                        }
                    }
                    if (!$hassourceatcontext) {
                        $this->add_role_source($roles, $roleid, 'runtime', $contextid, $path);
                    }
                }
            }
        }

        return $this->attach_role_metadata($roles);
    }

    /**
     * Add a role source without losing other assignments for the same role.
     *
     * @param array $roles Role data.
     * @param int $roleid Role ID.
     * @param string $type Source type.
     * @param int|null $contextid Source context ID.
     * @param string|null $path Source context path.
     * @return void
     */
    private function add_role_source(array &$roles, int $roleid, string $type, ?int $contextid, ?string $path): void {
        if (!isset($roles[$roleid])) {
            $roles[$roleid] = [
                'roleid' => $roleid,
                'sources' => [],
            ];
        }
        $sourcekey = $type . ':' . ($contextid ?? 0) . ':' . ($path ?? '');
        $roles[$roleid]['sources'][$sourcekey] = [
            'type' => $type,
            'contextid' => $contextid,
            'path' => $path,
        ];
    }

    /**
     * Add role names and archetypes.
     *
     * @param array $roles Role data.
     * @return array
     */
    private function attach_role_metadata(array $roles): array {
        global $DB;

        if (!$roles) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal(array_keys($roles), SQL_PARAMS_NAMED, 'role');
        $records = $DB->get_records_select('role', "id {$insql}", $params, 'id ASC');
        foreach ($roles as $roleid => &$roledata) {
            $record = $records[$roleid] ?? null;
            $roledata['shortname'] = $record ? (string)$record->shortname : '';
            $roledata['name'] = $record ? (string)$record->name : '';
            $roledata['archetype'] = $record ? (string)$record->archetype : '';
            $roledata['sources'] = array_values($roledata['sources']);
        }
        unset($roledata);
        ksort($roles);
        return $roles;
    }

    /**
     * Build each role's bottom-to-top rule evaluation.
     *
     * @param array $roleids Applicable role IDs.
     * @param array $paths Context paths from target to system.
     * @param string $capability Capability name.
     * @param array $contexts Context metadata keyed by path.
     * @return array
     */
    private function build_role_traces(array $roleids, array $paths, string $capability, array $contexts): array {
        global $DB;

        if (!$roleids || !$paths) {
            return [];
        }

        $contextids = array_map(static function (string $path): int {
            $parts = explode('/', trim($path, '/'));
            return (int)end($parts);
        }, $paths);

        [$roleinsql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid');
        [$ctxinsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'cid');
        $params = array_merge($roleparams, $ctxparams, ['capability' => $capability]);
        $sql = "SELECT rc.id, rc.roleid, rc.contextid, rc.permission, c.path
                  FROM {role_capabilities} rc
                  JOIN {context} c ON c.id = rc.contextid
                 WHERE rc.roleid {$roleinsql}
                   AND rc.contextid {$ctxinsql}
                   AND rc.capability = :capability";
        $records = $DB->get_records_sql($sql, $params);

        $rulesbyrole = [];
        foreach ($records as $record) {
            $rulesbyrole[(int)$record->roleid][$record->path] = $record;
        }

        $rolerecords = $DB->get_records_list('role', 'id', $roleids, 'id ASC');
        $traces = [];
        foreach ($roleids as $roleid) {
            $role = $rolerecords[$roleid] ?? new stdClass();
            $effective = null;
            $effectivecontextid = null;
            $hasprohibit = false;
            $rules = [];

            foreach ($paths as $path) {
                $stored = $rulesbyrole[$roleid][$path] ?? null;
                $permission = $stored ? (int)$stored->permission : null;
                $isprohibit = $permission === CAP_PROHIBIT;
                if ($isprohibit) {
                    $hasprohibit = true;
                }
                $effectiverule = false;
                if ($stored && $effective === null) {
                    $effective = $permission;
                    $effectivecontextid = (int)$stored->contextid;
                    $effectiverule = true;
                }

                $contextdata = $contexts[$path];
                $rules[] = [
                    'contextid' => $contextdata['id'],
                    'contextlevel' => $contextdata['contextlevel'],
                    'instanceid' => $contextdata['instanceid'],
                    'contextname' => $contextdata['name'],
                    'path' => $path,
                    'permission' => $permission,
                    'permissionkey' => self::permission_key($permission),
                    'stored' => (bool)$stored,
                    'source' => $stored && $contextdata['contextlevel'] === CONTEXT_SYSTEM
                        ? 'definition'
                        : ($stored ? 'override' : 'none'),
                    'effective_for_role' => $effectiverule,
                    'prohibit' => $isprohibit,
                ];
            }

            $traces[] = [
                'roleid' => (int)$roleid,
                'shortname' => isset($role->shortname) ? (string)$role->shortname : '',
                'name' => isset($role->name) ? (string)$role->name : '',
                'archetype' => isset($role->archetype) ? (string)$role->archetype : '',
                'effectivepermission' => $effective,
                'effectivepermissionkey' => self::permission_key($effective),
                'effectivecontextid' => $effectivecontextid,
                'hasprohibit' => $hasprohibit,
                'allows' => $effective === CAP_ALLOW,
                'rules' => $rules,
            ];
        }
        return $traces;
    }

    /**
     * Aggregate role traces using the same semantics as has_capability_in_accessdata().
     *
     * @param array $traces Role traces.
     * @return array
     */
    private function aggregate_role_traces(array $traces): array {
        $allowed = false;
        $prohibits = [];
        $allowroles = [];

        foreach ($traces as $trace) {
            foreach ($trace['rules'] as $rule) {
                if ($rule['prohibit']) {
                    $prohibits[] = [
                        'roleid' => $trace['roleid'],
                        'contextid' => $rule['contextid'],
                        'path' => $rule['path'],
                    ];
                }
            }
            if ($trace['allows']) {
                $allowed = true;
                $allowroles[] = $trace['roleid'];
            }
        }

        return [
            'result' => !$prohibits && $allowed,
            'hasprohibit' => (bool)$prohibits,
            'prohibits' => $prohibits,
            'allowroles' => $allowroles,
        ];
    }

    /**
     * Determine a machine-readable explanation reason.
     *
     * @param string|null $decisivegate Earlier accesslib short-circuit, if any.
     * @param array $roleaggregate Role aggregation details.
     * @return string
     */
    private function determine_reason(?string $decisivegate, array $roleaggregate): string {
        if ($decisivegate !== null) {
            return $decisivegate;
        }
        if ($roleaggregate['hasprohibit']) {
            return 'prohibit';
        }
        return $roleaggregate['result'] ? 'role_allow' : 'role_deny';
    }

    /**
     * Convert a permission constant to a stable key.
     *
     * @param int|null $permission Permission value.
     * @return string
     */
    public static function permission_key(?int $permission): string {
        if ($permission === null || $permission === CAP_INHERIT) {
            return 'inherit';
        }
        if ($permission === CAP_ALLOW) {
            return 'allow';
        }
        if ($permission === CAP_PREVENT) {
            return 'prevent';
        }
        if ($permission === CAP_PROHIBIT) {
            return 'prohibit';
        }
        return 'unknown';
    }
}
