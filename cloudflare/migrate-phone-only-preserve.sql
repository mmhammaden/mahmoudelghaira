PRAGMA foreign_keys = OFF;

ALTER TABLE classes RENAME TO legacy_classes;
ALTER TABLE exercises RENAME TO legacy_exercises;
ALTER TABLE profiles RENAME TO legacy_profiles;
ALTER TABLE results RENAME TO legacy_results;

DROP INDEX IF EXISTS idx_profiles_class;
DROP INDEX IF EXISTS idx_exercises_class;
DROP INDEX IF EXISTS idx_results_user;
DROP INDEX IF EXISTS idx_results_exercise;

CREATE TABLE classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE users (
    id TEXT PRIMARY KEY,
    phone TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE profiles (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    phone TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('student', 'admin')),
    class_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE sessions (
    token TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    expires_at INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE exercises (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    question TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_answer TEXT NOT NULL CHECK (correct_answer IN ('A', 'B', 'C', 'D')),
    class_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE results (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT NOT NULL,
    exercise_id INTEGER NOT NULL,
    score INTEGER NOT NULL,
    total INTEGER NOT NULL,
    submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES profiles(id) ON DELETE CASCADE,
    FOREIGN KEY (exercise_id) REFERENCES exercises(id) ON DELETE CASCADE,
    UNIQUE (user_id, exercise_id)
);

CREATE INDEX idx_profiles_class ON profiles(class_id);
CREATE INDEX idx_exercises_class ON exercises(class_id);
CREATE INDEX idx_results_user ON results(user_id);
CREATE INDEX idx_results_exercise ON results(exercise_id);
CREATE INDEX idx_sessions_user ON sessions(user_id);
CREATE INDEX idx_sessions_expiry ON sessions(expires_at);

INSERT INTO classes (id, name) VALUES
    (1, '2AS maths'),
    (2, '3AS PC'),
    (3, '4AS PC'),
    (4, '5D maths'),
    (5, '5A maths'),
    (6, '6A maths');

INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id, created_at)
SELECT title, question, option_a, option_b, option_c, option_d, correct_answer, class_id, created_at
FROM legacy_exercises ORDER BY rowid;

INSERT INTO users (id, phone, password_hash) VALUES
    ('user-1', '31406022', 'pbkdf2_sha256$210000$00112233445566778899aabbccddeeff$9eca4e82cf4f2b8fe191ea9ab000f511d0ceed58221c4ed1439671ed51a49063'),
    ('user-2', '31406024', 'pbkdf2_sha256$210000$102132435465768798a9bacbdcedfe0f$26d436d3bf132d16f7fbb6f32614e8029ae3d0a3bdee923fdcd937eda97de6c8'),
    ('admin-user', '31406023', 'pbkdf2_sha256$210000$2031425364758697a8b9cadbecfd0e1f$2e4b9edf873859ee1aabcaf549a2111e9625d6eda9316bcbb8d1b7b362f05901');

INSERT INTO profiles (id, name, phone, role, class_id, created_at)
SELECT id, name, phone, role, class_id, created_at FROM legacy_profiles;

PRAGMA foreign_keys = ON;
