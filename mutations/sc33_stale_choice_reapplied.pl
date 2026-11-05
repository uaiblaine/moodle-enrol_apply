# SC33: a decider who may not choose leaves an earlier approval's recorded choice in place instead of clearing it.
s#\$rowdecision = array_merge\(\(array\) \$decision, \['groups' => \[\], 'roleid' => 0\]\);#\$rowdecision = \$decision; unset(\$rowdecision['groups'], \$rowdecision['roleid']);#;
