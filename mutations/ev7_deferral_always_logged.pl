# EV7: the deferral event leaves the notification's rule and fires on every deferral, so a note
# corrected in silence is logged as a decision.
s#\n\n                \\enrol_apply\\event\\application_deferred::for_application\(\n.*?\)->trigger\(\);\n#\n#s;
s#(            if \(\$moved \|\| \$withmessage\) \{\n)#            \\enrol_apply\\event\\application_deferred::for_application((int) \$userenrolment->id, \$instance, (int) \$userenrolment->userid, ['moved' => \$moved, 'withmessage' => \$withmessage])->trigger();\n$1#;
