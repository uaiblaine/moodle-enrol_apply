# KW9: managers are asked to decide an application the key already admitted.
s#if \(\$keyoutcome !== \\enrol_apply\\local\\submission::KEY_ADMITTED\) \{\n(\s*)\$this->send_application_notification#if (true) \{\n${1}\$this->send_application_notification#;
