# F3A: never register the site-wide queue's admin node.
s#\nif \(\$hassiteconfig\) \{\n#\nif (false) {\n#s;
