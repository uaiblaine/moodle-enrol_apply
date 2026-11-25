# SF8: any approved user context is read as the subject's own.
s/ && \(int\) \$context->instanceid === \$userid\)/)/;
