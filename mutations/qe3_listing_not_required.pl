# QE3: the headless door does not ask listing_scope() whether the reader may open the queue.
s#if \(!\$listing->allowed\) \{#if (false) {#;
