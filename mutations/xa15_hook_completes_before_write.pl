# XA15: the hook completes the approval itself, before core writes the row, so a listener that throws after it leaves a role and groups on an application still pending.
s#local\\approvalwrite::expect\(\$userenrolmentid\);#enrol_get_plugin('apply')->complete_approval(\$hook->enrolinstance, (int) \$userenrolment->userid, \$userenrolmentid);#;
