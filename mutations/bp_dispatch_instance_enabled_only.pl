# BP: resolve the dispatch instance from the enabled-only list. It then agrees with core exactly
# when it does not matter and disagrees in the case it exists to name: a DISABLED apply method
# sorting first captures the whole dispatch, and the warning would name the wrong method.
s#foreach \(enrol_get_instances\(\$courseid, false\) as \$instance\)#foreach (enrol_get_instances(\$courseid, true) as \$instance)#;
