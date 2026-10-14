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

/**
 * Authorization for inspecting capability traces in a target context.
 *
 * Uses the same role capabilities as Moodle's admin/roles/check.php.
 *
 * @package   local_roleexplainer
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access_checker {
    /**
     * Whether the current user may check another user's permissions in this context.
     *
     * @param context $context Context being inspected.
     * @return bool
     */
    public static function can_analyse(context $context): bool {
        return has_any_capability([
            'moodle/role:assign',
            'moodle/role:safeoverride',
            'moodle/role:override',
            'moodle/role:manage',
        ], $context);
    }
}
