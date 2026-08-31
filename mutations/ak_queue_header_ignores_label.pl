# AK: make the queue's comment header ignore the instance's own wording, so the question the
# applicant answered and the heading above the answers drift apart again.
s{\$headers\[\] = \$commentlabel !== '' \? \$commentlabel : get_string\('applycomment', 'enrol_apply'\);}{\$headers[] = get_string('applycomment', 'enrol_apply');}s;
