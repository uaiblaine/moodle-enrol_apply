# PL21: the batch forgets that profiles not forced to log in are open to every logged-in user.
s#if \(empty\(\$CFG->forceloginforprofiles\)\) \{#if (false) {#;
