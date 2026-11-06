# EV16: the observer answers every method's user_enrolment_updated, not only this plugin's.
s#\(\$event->other\['enrol'\] \?\? null\) !== 'apply'#false#;
