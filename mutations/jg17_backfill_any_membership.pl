# JG17: the upgrade takes any membership of the applicant as evidence, stamped by this method or not.
s#JOIN \{groups_members\} gm ON gm\.userid = s\.userid AND gm\.component = :component AND gm\.itemid = s\.enrolid#JOIN {groups_members} gm ON gm.userid = s.userid#;
