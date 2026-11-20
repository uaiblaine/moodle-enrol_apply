# XA16: the observer completes an approval for any update of an active enrolment, not only one the hook expected.
s#if \(!approvalwrite::take\(\$userenrolmentid\)\) \{#if (!approvalwrite::take(\$userenrolmentid) && false) {#;
