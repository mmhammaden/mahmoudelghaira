CREATE TABLE IF NOT EXISTS classes (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS chapters (
    id SERIAL PRIMARY KEY,
    name TEXT NOT NULL,
    class_id INTEGER NOT NULL REFERENCES classes(id),
    exercise_count INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id TEXT PRIMARY KEY,
    phone TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS profiles (
    id TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    phone TEXT NOT NULL UNIQUE,
    role TEXT NOT NULL DEFAULT 'student'
        CHECK (role IN ('student', 'admin')),
    class_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE IF NOT EXISTS sessions (
    token TEXT PRIMARY KEY,
    user_id TEXT NOT NULL,
    expires_at BIGINT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS exercises (
    id SERIAL PRIMARY KEY,
    title TEXT NOT NULL,
    question TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct_answer TEXT NOT NULL
        CHECK (correct_answer IN ('A', 'B', 'C', 'D')),
    class_id INTEGER NOT NULL,
    chapter_id INTEGER DEFAULT 1 REFERENCES chapters(id),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id)
);

CREATE TABLE IF NOT EXISTS results (
    id SERIAL PRIMARY KEY,
    user_id TEXT NOT NULL,
    exercise_id INTEGER NOT NULL,
    score INTEGER NOT NULL,
    total INTEGER NOT NULL,
    submitted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES profiles(id),
    FOREIGN KEY (exercise_id) REFERENCES exercises(id),
    UNIQUE (user_id, exercise_id)
);

CREATE INDEX IF NOT EXISTS idx_profiles_class ON profiles(class_id);
CREATE INDEX IF NOT EXISTS idx_exercises_class ON exercises(class_id);
CREATE INDEX IF NOT EXISTS idx_exercises_chapter ON exercises(chapter_id);
CREATE INDEX IF NOT EXISTS idx_results_user ON results(user_id);
CREATE INDEX IF NOT EXISTS idx_results_exercise ON results(exercise_id);
CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_expiry ON sessions(expires_at);
