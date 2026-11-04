# GV4: a MEMBERS or OWN group is named to somebody who is not a member.
s#return \$DB->record_exists\('groups_members', \['groupid' => \(int\) \$group->id, 'userid' => \$readerid\]\);#return true;#;
