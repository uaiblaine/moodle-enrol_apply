# SF25: the deleted readers' cut takes their rows of every report, not only the plugin's.
s/'reportid IN \(SELECT r\.id FROM \{' \. report::TABLE \. "\} r WHERE \{\$isplugins\}\)"/'1 = 1'/;
