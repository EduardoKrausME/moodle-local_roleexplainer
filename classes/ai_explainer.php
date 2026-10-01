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

use local_ai_bridge\api;

/**
 * Converts a deterministic permission trace into a human explanation through local_ai_bridge.
 *
 * @package   local_roleexplainer
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_explainer {
    /**
     * AI purpose configured in local_ai_bridge.
     */
    private const PURPOSE = 'roleexplainer-explain';

    /**
     * Explain an already-computed analysis.
     *
     * @param array $analysis Deterministic analysis.
     * @param string $mode Question mode.
     * @return array
     */
    public function explain(array $analysis, string $mode = 'full'): array {
        $payload = $this->build_payload($analysis, $mode);
        $messages = [
            [
                'role' => 'system',
                'content' => implode("\n", [
                    'You explain Moodle role/capability diagnostics that were already calculated by Moodle.',
                    'The field official_result is authoritative. Never recalculate or contradict it.',
                    'Use only rules and contexts present in the payload. Never invent a permission, assignment, or override.',
                    'A PROHIBIT is globally decisive. PREVENT is not globally decisive when another applicable role allows.',
                    'Do not suggest changing permissions unless you identify an exact role_id and context_id ' .
                    'present in the payload.',
                    'No automatic change is possible. Suggestions must remain checks for a human administrator.',
                    'Return valid JSON only with keys: summary, decisive_rules, explanation, suggested_checks.',
                    'summary and explanation are strings; decisive_rules and suggested_checks are arrays of strings.',
                ]),
            ],
            [
                'role' => 'user',
                'content' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            ],
        ];

        $response = api::generate(self::PURPOSE, $messages);
        return $this->parse_response($response->text, $analysis);
    }

    /**
     * Remove personal display data before sending the trace to AI.
     *
     * @param array $analysis Deterministic analysis.
     * @param string $mode Question mode.
     * @return array
     */
    private function build_payload(array $analysis, string $mode): array {
        $roles = [];
        foreach ($analysis['roles'] as $role) {
            $rules = [];
            foreach ($role['rules'] as $rule) {
                $rules[] = [
                    'context_id' => $rule['contextid'],
                    'context_level' => $rule['contextlevel'],
                    'permission' => $rule['permissionkey'],
                    'source' => $rule['source'],
                    'effective_for_role' => $rule['effective_for_role'],
                    'prohibit' => $rule['prohibit'],
                ];
            }
            $roles[] = [
                'role_id' => $role['roleid'],
                'archetype' => $role['archetype'],
                'effective_permission' => $role['effectivepermissionkey'],
                'effective_context_id' => $role['effectivecontextid'],
                'has_prohibit' => $role['hasprohibit'],
                'rules' => $rules,
            ];
        }

        $sources = [];
        foreach ($analysis['rolesources'] as $role) {
            $sources[] = [
                'role_id' => $role['roleid'],
                'archetype' => $role['archetype'],
                'sources' => array_map(static function (array $source): array {
                    return [
                        'type' => $source['type'],
                        'context_id' => $source['contextid'],
                    ];
                }, $role['sources']),
            ];
        }

        return [
            'subject' => 'Target user',
            'question_mode' => in_array($mode, ['full', 'whycan', 'whycannot'], true) ? $mode : 'full',
            'capability' => $analysis['capability'],
            'capability_exists' => $analysis['capabilityexists'],
            'target_context_id' => $analysis['contextid'],
            'official_result' => $analysis['officialresult'],
            'doanything' => $analysis['doanything'],
            'site_admin' => $analysis['siteadmin'],
            'admin_shortcut' => $analysis['adminshortcut'],
            'result_without_doanything' => $analysis['withoutdoanything'],
            'reason' => $analysis['reason'],
            'gates' => $analysis['gates'],
            'context_chain' => array_map(static function (array $context): array {
                return [
                    'context_id' => $context['id'],
                    'context_level' => $context['contextlevel'],
                    'instance_id' => $context['instanceid'],
                ];
            }, $analysis['contexts']),
            'role_sources' => $sources,
            'roles' => $roles,
            'role_aggregate' => $analysis['roleaggregate'],
        ];
    }

    /**
     * Parse a JSON response, with a safe text fallback if the configured model did not obey JSON mode.
     *
     * @param string $text Provider response text.
     * @param array $analysis Deterministic analysis used to validate actionable suggestions.
     * @return array
     */
    private function parse_response(string $text, array $analysis): array {
        $candidate = trim($text);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $candidate, $matches)) {
            $candidate = trim($matches[1]);
        }
        $decoded = json_decode($candidate, true);
        if (!is_array($decoded)) {
            return [
                'summary' => '',
                'decisive_rules' => [],
                'explanation' => $this->guard_change_suggestions(clean_text($text, FORMAT_PLAIN), $analysis),
                'suggested_checks' => [],
            ];
        }

        return [
            'summary' => isset($decoded['summary']) && is_string($decoded['summary'])
                ? $this->guard_change_suggestions($decoded['summary'], $analysis) : '',
            'decisive_rules' => $this->guard_string_list($decoded['decisive_rules'] ?? [], $analysis),
            'explanation' => isset($decoded['explanation']) && is_string($decoded['explanation'])
                ? $this->guard_change_suggestions($decoded['explanation'], $analysis) : '',
            'suggested_checks' => $this->guard_string_list($decoded['suggested_checks'] ?? [], $analysis),
        ];
    }


    /**
     * Filter a model-produced list so permission-changing suggestions name a concrete role and context from the trace.
     *
     * @param mixed $value Input list.
     * @param array $analysis Deterministic analysis.
     * @return array
     */
    private function guard_string_list($value, array $analysis): array {
        $result = [];
        foreach ($this->string_list($value) as $item) {
            $guarded = $this->guard_change_suggestions($item, $analysis);
            if (trim($guarded) !== '') {
                $result[] = $guarded;
            }
        }
        return $result;
    }

    /**
     * Remove AI sentences that recommend a permission change without naming an exact role and context from the trace.
     *
     * This is a second line of defence after the prompt. The model may explain permissions freely, but actionable
     * changes are only displayed when the referenced role/context pair is concrete and belongs to this analysis.
     *
     * @param string $text Model text.
     * @param array $analysis Deterministic analysis.
     * @return string
     */
    private function guard_change_suggestions(string $text, array $analysis): string {
        $parts = preg_split('/(?<=[.!?])\s+|\R+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return '';
        }
        $kept = [];
        foreach ($parts as $part) {
            if (!$this->looks_like_change_suggestion($part) || $this->has_valid_role_context_reference($part, $analysis)) {
                $kept[] = $part;
            }
        }
        return implode(' ', $kept);
    }

    /**
     * Detect language that is likely to recommend a permission mutation.
     *
     * @param string $text Text fragment.
     * @return bool
     */
    private function looks_like_change_suggestion(string $text): bool {
        $pattern = '/\b(change|modify|edit|remove|assign|grant|revoke|set|alterar|modificar|editar|' .
            'remover|atribuir|conceder|revogar|definir)\b/ui';
        return (bool)preg_match($pattern, $text);
    }

    /**
     * Verify that an actionable suggestion names role/context IDs that actually exist in the analysis.
     *
     * @param string $text Text fragment.
     * @param array $analysis Deterministic analysis.
     * @return bool
     */
    private function has_valid_role_context_reference(string $text, array $analysis): bool {
        if (!preg_match('/\brole(?:_id|\s+id)?\s*[:#]?\s*(\d+)\b/ui', $text, $rolematch)) {
            return false;
        }
        if (!preg_match('/\bcontext(?:_id|\s+id|o)?\s*[:#]?\s*(\d+)\b/ui', $text, $contextmatch)) {
            return false;
        }
        $roleids = array_map(static fn(array $role): int => (int)$role['roleid'], $analysis['rolesources']);
        $contextids = array_map(static fn(array $context): int => (int)$context['id'], $analysis['contexts']);
        return in_array((int)$rolematch[1], $roleids, true) && in_array((int)$contextmatch[1], $contextids, true);
    }

    /**
     * Keep only string items in an AI-produced list.
     *
     * @param mixed $value Input value.
     * @return array
     */
    private function string_list($value): array {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $result[] = $item;
            }
        }
        return $result;
    }
}
