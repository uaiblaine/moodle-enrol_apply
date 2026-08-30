# AA: stop clearing the expiry when deferring, so a once-approved row lands on the waiting
# list carrying a past timeend - swept by nothing, listed by nothing, undecidable.
s{\$this->update_user_enrol\(\$instance, \$userenrolment->userid, ENROL_APPLY_USER_WAIT, null, 0\);}{\$this->update_user_enrol(\$instance, \$userenrolment->userid, ENROL_APPLY_USER_WAIT);};
