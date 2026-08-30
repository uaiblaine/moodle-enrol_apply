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

namespace enrol_apply\local;

use coding_exception;

/**
 * What submit_application() did, in a form the caller can route on.
 *
 * The method used to return a bool, and that bool fused two outcomes which need opposite
 * treatment: "there is already an application" is benign and the acknowledgement page is the
 * right destination for it, while "this was refused" needs to say so and go somewhere else.
 * Nothing could tell them apart, so the caller discarded the value entirely and sent every
 * outcome to the acknowledgement page - where a refusal, having written no enrolment row, met
 * that page's own access gate and became a bare "Invalid access detected".
 *
 * Three states rather than a nullable reason string, because the distinction between CREATED
 * and ALREADY is what the tests assert on: with a two-state return, a test proving that a
 * second submission created nothing would be indistinguishable from one proving it succeeded.
 *
 * @package    enrol_apply
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class application_result {
    /** @var string This call wrote the application. */
    private const CREATED = 'created';

    /** @var string An application was already there; this call wrote nothing and that is fine. */
    private const ALREADY = 'already';

    /** @var string Nothing was written and the applicant has to be told why. */
    private const REFUSED = 'refused';

    /** @var string Which of the three outcomes this is. */
    private $state;

    /** @var string The reason to show the applicant. Empty unless this is a refusal. */
    private $reason;

    /**
     * Private on purpose: an instance only ever comes from one of the three named constructors.
     *
     * @param string $state One of the three state constants.
     * @param string $reason Reason to show the applicant, empty for the two non-refusals.
     */
    private function __construct(string $state, string $reason) {
        $this->state = $state;
        $this->reason = $reason;
    }

    /**
     * The application was written by this call.
     *
     * @return self
     */
    public static function created(): self {
        return new self(self::CREATED, '');
    }

    /**
     * An application was already there, so this call wrote nothing.
     *
     * This is the ordinary double submission - two tabs, a double click, or a lost race for
     * the lock - and it is not a failure: the applicant does have an application, so the
     * acknowledgement page is telling them the truth.
     *
     * @return self
     */
    public static function already_applied(): self {
        return new self(self::ALREADY, '');
    }

    /**
     * The application was refused, and this is what to tell the applicant.
     *
     * The reason must not be empty. A refusal nobody can explain is the defect this class was
     * introduced to remove, so producing one is a coding error rather than a silent fallback:
     * a fallback would put the generic message back and hide the caller that forgot.
     *
     * @param string $reason Ready-to-render reason, already through get_string().
     * @return self
     * @throws coding_exception When the reason is empty.
     */
    public static function refused(string $reason): self {
        if (trim($reason) === '') {
            throw new coding_exception('A refused application must carry a reason to show the applicant.');
        }

        return new self(self::REFUSED, $reason);
    }

    /**
     * Whether this call wrote the application.
     *
     * @return bool True only for the created outcome.
     */
    public function was_created(): bool {
        return $this->state === self::CREATED;
    }

    /**
     * Whether the applicant has to be told why nothing happened.
     *
     * @return bool True only for the refused outcome.
     */
    public function is_refusal(): bool {
        return $this->state === self::REFUSED;
    }

    /**
     * The reason to show the applicant.
     *
     * @return string The reason, or the empty string when this is not a refusal.
     */
    public function reason(): string {
        return $this->reason;
    }
}
