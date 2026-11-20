# GD13: the notice names a chosen group to a recipient without the course capability (a mentor).
s#\n            && has_capability\('enrol/apply:manageapplications', \$coursecontext, \$recipientid\)##;
