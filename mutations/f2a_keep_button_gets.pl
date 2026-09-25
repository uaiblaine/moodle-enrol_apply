# F2A: send the cancel confirmation's Keep button as a GET form again. The decider's private note
# and the message to the applicant then travel back in the review page's query string.
s{get_string\('reviewkeep', 'enrol_apply'\),\n                'post'}{get_string('reviewkeep', 'enrol_apply'),\n                'get'}s;
