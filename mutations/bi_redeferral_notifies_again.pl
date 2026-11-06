# BI: notify on every deferral again, including one that only corrects the internal note. The
# applicant is then re-mailed "your application was deferred" - news they already had - every
# time a colleague edits two words nobody outside the site can see. Only the notification is
# moved out of the shared rule; the deferral event stays under it (gate EV7 breaks that half).
#
# The delimiter is # and not |, and that is not cosmetic. Perl strips the backslash from an
# ESCAPED DELIMITER before compiling, so `\|\|` inside a |-delimited s/// becomes bare `||` in the
# pattern - alternation with an empty branch, which matches at offset 0. The first version of this
# script therefore inserted its replacement before the opening <?php and reddened nothing.
s#            if \(\$moved \|\| \$withmessage\) \{\n                \$this->notify_applicant\(\n                    \$instance,\n                    \$userenrolment,\n                    'waitinglist',\n                    get_config\('enrol_apply', 'waitmailsubject'\),\n                    get_config\('enrol_apply', 'waitmailcontent'\)\n                \);\n#            \$this->notify_applicant(\n                \$instance,\n                \$userenrolment,\n                'waitinglist',\n                get_config('enrol_apply', 'waitmailsubject'),\n                get_config('enrol_apply', 'waitmailcontent')\n            );\n            if (\$moved || \$withmessage) {\n#s;
