CREATE TABLE /*_*/push_subscriptions (
  psb_id                INT UNSIGNED    NOT NULL PRIMARY KEY AUTO_INCREMENT,
  psb_topic             VARCHAR(2048)   NOT NULL,
  psb_expires           BINARY(14),
  psb_confirmed         BOOL            NOT NULL DEFAULT 0,
  psb_unsubscribe       BOOL            NOT NULL DEFAULT 0
) /*$wgDBTableOptions*/;
