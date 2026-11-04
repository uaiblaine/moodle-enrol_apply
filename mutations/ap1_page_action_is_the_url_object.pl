# AP1: apply.php hands the form its moodle_url object again, which dynamic_form casts to the escaped spelling.
s#application_form\(\$pageurl->out\(false\), #application_form(\$pageurl, #;
