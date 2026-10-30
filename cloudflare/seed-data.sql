PRAGMA foreign_keys = OFF;

INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id) VALUES ('Math quiz 1', 'What is 2+2?', 'A. 3', 'B. 4', 'C. 5', 'D. 6', 'B', 1);
INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id) VALUES ('Math quiz 2', 'What is 3+3?', 'A. 5', 'B. 6', 'C. 7', 'D. 8', 'B', 1);
INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id) VALUES ('Physics quiz', 'What is gravity?', 'A. Magic', 'B. Force', 'C. Light', 'D. Sound', 'B', 2);
INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id) VALUES ('Chemistry quiz', 'H2O is?', 'A. Hydrogen', 'B. Water', 'C. Oxygen', 'D. Salt', 'B', 3);
INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id) VALUES ('Math quiz 3', 'What is 5*5?', 'A. 10', 'B. 20', 'C. 25', 'D. 30', 'C', 5);

INSERT INTO profiles (id, name, phone, role, class_id) VALUES ('user-1', 'المشرف العام', '31406022', 'admin', NULL);
INSERT INTO profiles (id, name, phone, role, class_id) VALUES ('user-2', 'Student Two', '31406024', 'student', 1);
INSERT INTO profiles (id, name, phone, role, class_id) VALUES ('admin-user', 'Admin User', '31406023', 'admin', NULL);

INSERT INTO users (id, phone, password_hash) VALUES ('user-1', '31406022', 'pbkdf2_sha256$100000$da6c8bfec34cfa069d1a9bfbcbd52fd2$104b1c7899383b5da8261612ee53cbfc365bac3ea380dd5c0348a379fe33f5d2');
INSERT INTO users (id, phone, password_hash) VALUES ('user-2', '31406024', 'pbkdf2_sha256$210000$102132435465768798a9bacbdcedfe0f$26d436d3bf132d16f7fbb6f32614e8029ae3d0a3bdee923fdcd937eda97de6c8');
INSERT INTO users (id, phone, password_hash) VALUES ('admin-user', '31406023', 'pbkdf2_sha256$210000$2031425364758697a8b9cadbecfd0e1f$2e4b9edf873859ee1aabcaf549a2111e9625d6eda9316bcbb8d1b7b362f05901');

PRAGMA foreign_keys = ON;
