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
 * English strings.
 *
 * @package local_roleexplainer
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$string['adminshortcut'] = 'The target user is a site administrator and Moodle returned true through the admin doanything shortcut.';
$string['aiexplanation'] = 'AI explanation';
$string['aisummary'] = 'Summary';
$string['aiunavailable'] = 'The deterministic analysis completed, but the AI explanation could not be generated: {$a}';
$string['allow'] = 'Allow';
$string['allowed'] = 'Allowed';
$string['analyse'] = 'Analyse permission';
$string['analysisdetails'] = 'Deterministic details';
$string['archetype'] = 'Archetype';
$string['assignmentsource'] = 'Assignment source';
$string['assignroleslink'] = 'Assign roles';
$string['block'] = 'Block';
$string['cannotanalyseownsession'] = 'The current session state prevented this analysis from being completed safely.';
$string['capability'] = 'Capability';
$string['capabilitymissingreason'] = 'The capability is not registered, so Moodle treats the check as false.';
$string['capabilityname'] = 'Capability';
$string['context'] = 'Context';
$string['contextchain'] = 'Context chain';
$string['contextid'] = 'Context ID';
$string['contextid_help'] = 'Enter the Moodle context ID to inspect. System context is usually 1. Course, category, activity and block contexts are supported.';
$string['contextidlabel'] = 'Context ID';
$string['contextinstance'] = 'Instance ID';
$string['contextlevel'] = 'Context level';
$string['contextlocked'] = 'The context is locked and this is a write capability, so Moodle denied it before normal role aggregation.';
$string['course'] = 'Course';
$string['coursecategory'] = 'Course category';
$string['decisivefactors'] = 'Decisive factors';
$string['decisiverules'] = 'Decisive rules';
$string['defaultuserrole'] = 'Default authenticated user role';
$string['definition'] = 'Role definition';
$string['denied'] = 'Denied';
$string['doanything'] = 'doanything';
$string['effective'] = 'Effective';
$string['effectivepermission'] = 'Effective permission for this role';
$string['explicitassignment'] = 'Explicit role assignment';
$string['frontpagerole'] = 'Default front page role';
$string['guestrisky'] = 'Guest or not-logged-in users cannot receive write or risky capabilities.';
$string['guestrole'] = 'Guest role';
$string['inherited'] = 'Inherited';
$string['intro'] = 'Inspect how Moodle resolved a capability for a target user in a specific context. The final result is always taken from has_capability(); AI only explains the deterministic evidence.';
$string['invalidcontext'] = 'The selected context does not exist.';
$string['invaliduser'] = 'The selected user does not exist or is deleted.';
$string['links'] = 'Moodle permission screens';
$string['loginasrestricted'] = 'The current login-as session restricts capability checks outside its allowed context subtree.';
$string['missingcapability'] = 'This capability does not exist in the Moodle capability registry.';
$string['mode'] = 'Question mode';
$string['modefull'] = 'Explain the result';
$string['modewhycan'] = 'Why can this user do it?';
$string['modewhycannot'] = 'Why can this user not do it?';
$string['module'] = 'Activity module';
$string['no'] = 'No';
$string['noexplicitroles'] = 'No explicit role assignment was found in this context path.';
$string['norule'] = 'No explicit rule at this context';
$string['officialresult'] = 'Official Moodle result';
$string['othercontext'] = 'Other context';
$string['override'] = 'Override';
$string['overridelink'] = 'Override this role';
$string['permission'] = 'Permission';
$string['permissionslink'] = 'Review permissions';
$string['pluginname'] = 'Role explainer';
$string['prevent'] = 'Prevent';
$string['privacy:metadata'] = 'The Role explainer does not persist analysis data.';
$string['prohibit'] = 'Prohibit';
$string['prohibitdecisive'] = 'A PROHIBIT rule was found. In Moodle, any prohibit in any applicable role denies the capability.';
$string['role'] = 'Role';
$string['roleaggregatedallow'] = 'At least one applicable role resolved to ALLOW and no applicable role contained PROHIBIT.';
$string['roleaggregateddeny'] = 'No applicable role resolved to ALLOW, or the closest effective rules resolved to PREVENT/inherit.';
$string['rolechain'] = 'Role evaluation';
$string['roleexplainer:use'] = 'Use the role and permission explainer';
$string['roleid'] = 'Role ID';
$string['runtimeassignment'] = 'Runtime role assignment';
$string['source'] = 'Source';
$string['suggestedchecks'] = 'Suggested checks';
$string['switchedrole'] = 'Switched role';
$string['system'] = 'System';
$string['targetuser'] = 'Target user';
$string['targetuseranonymous'] = 'Target user';
$string['targetuserlabel'] = 'Target user';
$string['unknown'] = 'Unknown';
$string['usercontext'] = 'User';
$string['withoutadminshortcut'] = 'Result with doanything=false';
$string['yes'] = 'Yes';
