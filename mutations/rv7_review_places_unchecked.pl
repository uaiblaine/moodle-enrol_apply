# RV7: an application for review is written whatever the method's places.
s#(function apply_for_review\(.*?)if \(\\enrol_apply\\local\\capacity::places_full\(\$instance\)\) \{#${1}if (false) {#s;
