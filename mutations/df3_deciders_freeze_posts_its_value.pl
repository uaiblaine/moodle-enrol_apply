# DF3: a freeze that posts its value through a hidden input, so a forged post replaces the stored answer.
s#\$mform->hardFreeze\('deciders'\);#\$mform->freeze('deciders');#;
