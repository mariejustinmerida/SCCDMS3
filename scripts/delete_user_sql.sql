-- SQL script to delete user with ID 7 and all related records
-- Run these statements in order in phpMyAdmin or your SQL client

-- Start transaction for safety
START TRANSACTION;

-- Step 1: Delete related records from tables WITHOUT CASCADE delete
DELETE FROM `user_logs` WHERE `user_id` = 7;
DELETE FROM `collaborative_cursors` WHERE `user_id` = 7;
DELETE FROM `document_actions` WHERE `user_id` = 7;
DELETE FROM `document_drafts` WHERE `user_id` = 7;
DELETE FROM `signature_approvals` WHERE `user_id` = 7;
DELETE FROM `edit_conflicts` WHERE `user_id` = 7;
DELETE FROM `edit_conflicts` WHERE `conflicting_user_id` = 7;

-- Step 2: Delete the user (this will automatically cascade delete records from tables WITH CASCADE)
DELETE FROM `users` WHERE `user_id` = 7;

-- Step 3: Review what was deleted, then commit
-- If everything looks good, run: COMMIT;
-- If something went wrong, run: ROLLBACK;

-- COMMIT;  -- Uncomment this line when you're ready to commit the changes

