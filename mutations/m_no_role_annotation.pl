# M: stop annotating the decided role, so it never reaches roles.xml.
s|\n        \$submission->annotate_ids\('role', 'decidedrole'\);\n|\n|s;
