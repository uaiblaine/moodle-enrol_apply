# XA17: the queue draws a selection box on the reader's own application, which every decision then skips in silence.
s#if \(queue::is_own_application\(\(int\) \$row->userid\)\) \{\n            return '';#if (false) {\n            return '';#;
