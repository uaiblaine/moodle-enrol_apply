# CF5A: the envelope migration re-writes a record that already has rows.
s{if \(\$DB->record_exists\(\\enrol_apply\\local\\submission::ANSWER_TABLE, \['submissionid' => \$row->id\]\)\) \{\n\s*continue;\n\s*\}\n}{}s;
