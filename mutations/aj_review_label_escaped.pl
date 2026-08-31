# AJ: hand the review page the ESCAPED label. It renders through a DOUBLE stash, so the
# reader would see the entities. This is the sink that differs from the other two.
s{commentlabel::custom\(\$instance, false\)}{commentlabel::custom(\$instance)}s;
