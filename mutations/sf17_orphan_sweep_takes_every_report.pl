# SF17: the sweep of a deleted course takes every report of the plugin, whose context exists or not.
s/NOT EXISTS \(SELECT 1 FROM \{context\} ctx WHERE ctx\.id = r\.contextid\)/1 = 1/;
