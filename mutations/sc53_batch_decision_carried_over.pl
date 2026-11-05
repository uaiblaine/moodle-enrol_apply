# SC53: the choosedecision override reassigns the batch's decision, so later rows inherit the refusal.
s#\$rowdecision = array_merge\(\(array\) \$decision,#\$rowdecision = \$decision = array_merge((array) \$decision,#;
