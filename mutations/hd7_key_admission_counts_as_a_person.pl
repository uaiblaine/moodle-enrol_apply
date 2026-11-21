# HD7: a key admission is not a decision by a person (AR-D5).
s#return \$decidedby > 0 && \$decidedby !== \(int\) \$record->userid;#return \$decidedby > 0;#;
