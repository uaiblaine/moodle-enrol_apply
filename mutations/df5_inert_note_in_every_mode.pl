# DF5: the inert note and the freeze in every group mode, separate groups included.
s#if \(!\$separate\) \{(\n\s*\$mform->hardFreeze\('deciders'\);)#if (true) {$1#;
