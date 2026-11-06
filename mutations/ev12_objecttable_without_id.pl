# EV12: an application with no durable record keeps the object table with no object id.
s#        if \(!\$record\) \{\n            \$event->data\['objecttable'\] = null;\n        \}\n##s;
