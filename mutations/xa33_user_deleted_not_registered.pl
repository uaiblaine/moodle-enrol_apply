# XA33: the user_deleted observer is registered on another event, so deleting a user never reaches it.
s#'\\core\\event\\user_deleted'#'\\core\\event\\user_updated'#;
