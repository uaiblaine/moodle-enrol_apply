# SF2: a report is the plugin's by its component, the label a client of core's services chooses, instead of its source.
s/LOWER\(\{\$alias\}\.source\) \{\$insql\}/{\$alias}.component = 'enrol_apply'/;
