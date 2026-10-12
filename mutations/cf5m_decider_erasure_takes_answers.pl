# CF5M: a decider's erasure deletes the answers of the applications they decided.
s{(\[\$deciderinsql, \$deciderparams\] = \$DB->get_in_or_equal\(\$userids, SQL_PARAMS_NAMED, 'decider'\);\n)}{$1        submission::delete_answers("courseid {\$courseinsql} AND decidedby {\$deciderinsql}", \$courseparams + \$deciderparams);\n}s;
