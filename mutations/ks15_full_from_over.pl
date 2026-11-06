# KS15: a group at exactly its seats is not called full, on the queue's chooser or the review page's
# cards (repointed for V3 to group_facts(), and for V4 to local\groupfacts).
s|                'full' => \$hasseats && \$members >= \$total,|                'full' => \$hasseats && \$members > \$total,|;
