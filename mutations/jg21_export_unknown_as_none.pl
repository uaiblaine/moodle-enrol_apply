# JG21: the privacy export reads groups joined that are not known as "joined none".
s#(\$export->joinedgroups = \$row->joinedgroups === null\n\s*)\? null#${1}? []#;
