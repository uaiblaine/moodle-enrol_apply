# SF21: a report under the plugin's component is the plugin's whatever its source, another plugin's report included.
s/AND LOWER\(\{\$alias\}\.source\) \{\$insql\}\)/AND ({\$alias}.component = 'enrol_apply' OR LOWER({\$alias}.source) {\$insql}))/;
