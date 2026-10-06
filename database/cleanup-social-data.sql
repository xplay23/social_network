USE pg629141_newsportal;

START TRANSACTION;
DELETE FROM comments;
DELETE FROM likes;
DELETE FROM friendships;
DELETE FROM posts;
COMMIT;

SELECT
  (SELECT COUNT(*) FROM posts) AS posts,
  (SELECT COUNT(*) FROM comments) AS comments,
  (SELECT COUNT(*) FROM likes) AS likes,
  (SELECT COUNT(*) FROM friendships) AS friendships;
