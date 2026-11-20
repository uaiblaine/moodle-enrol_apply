# FG11: "Who decides" is saved as the group's deciders whatever was chosen.
s#(=== instanceoptions::DECIDERS_ALLGROUPS\n\s*\? )instanceoptions::DECIDERS_ALLGROUPS#${1}instanceoptions::DECIDERS_GROUP#;
