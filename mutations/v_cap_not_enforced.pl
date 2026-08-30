# V: drop the places cap from the write door. Before this change the cap had no mutation at
# all and deleting it reddened NOTHING - it was held by one test on one of its three doors.
s{\n            if \(\$instance->customint3 > 0\) \{.*?\n            \}\n}{\n}s;
