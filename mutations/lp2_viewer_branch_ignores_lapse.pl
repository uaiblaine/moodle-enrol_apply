# LP2: can_view_application()'s viewer branch reads the capability with has_capability() alone.
s#\\enrol_apply\\local\\groupscope::holds_in_course\(\$view, \$coursecontext\)#has_capability(\$view, \$coursecontext)#;
