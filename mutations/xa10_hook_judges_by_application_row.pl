# XA10: the hook knows an application by its applicationinfo row, which an approval deletes, so a re-queued enrolment (approved, then suspended) is reactivated unjudged and without the approval work.
s#if \(!\$before \|\| !self::is_application\(\$before\)\) \{#if (!\$before || !\$DB->record_exists('enrol_apply_applicationinfo', ['userenrolmentid' => \$userenrolmentid])) {#;
