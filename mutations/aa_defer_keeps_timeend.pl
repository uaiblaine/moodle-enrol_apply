# AA: stop clearing the expiry when deferring, so a row carrying a future timeend (suspended
# mid-period from core's "Edit enrolment" screen, or restored) lands on the waiting list with it
# and is stranded once the date passes - swept by nothing, listed by nothing, undecidable.
s{\$this->update_user_enrol\(\$instance, \$userenrolment->userid, ENROL_APPLY_USER_WAIT, null, 0\);}{\$this->update_user_enrol(\$instance, \$userenrolment->userid, ENROL_APPLY_USER_WAIT);};
