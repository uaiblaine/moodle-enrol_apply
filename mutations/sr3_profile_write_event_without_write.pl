# SR3: fire user_updated even when nothing was written.
s#if \(!\$written\) \{#if (false) {#;
