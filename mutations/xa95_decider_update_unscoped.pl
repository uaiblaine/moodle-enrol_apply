# XA95: the decider update loses its WHERE and clears every decider.
s#decidedby = 0 WHERE decidedby = :userid#decidedby = 0 WHERE decidedby >= 0#;
