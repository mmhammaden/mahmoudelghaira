PRAGMA foreign_keys = OFF;

DROP TABLE IF EXISTS sessions;
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS exercises;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS chapters;
DROP TABLE IF EXISTS classes;

PRAGMA foreign_keys = ON;

CREATE TABLE classes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
);

CREATE TABLE chapters (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    class_id INTEGER NOT NULL,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    UNIQUE (class_id, title)
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
    role TEXT NOT NULL DEFAULT 'student'
        CHECK (role IN ('student', 'admin')),
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
    chapter_id INTEGER,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id),
    FOREIGN KEY (chapter_id) REFERENCES chapters(id)
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
CREATE INDEX idx_chapters_class ON chapters(class_id, position);
CREATE INDEX idx_exercises_class ON exercises(class_id);
CREATE INDEX idx_exercises_chapter ON exercises(chapter_id);
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

INSERT INTO chapters (id, title, description, class_id, position) VALUES
    (101, 'Addition des nombres relatifs', 'Calculer des sommes avec des nombres positifs, négatifs et opposés.', 1, 1),
    (102, 'Comparer les nombres relatifs', 'Choisir le symbole < ou > et ordonner des nombres relatifs.', 1, 2),
    (103, 'Repérage sur une droite graduée', 'Lire des abscisses, ordonner des points et reconnaître un milieu.', 1, 3);

INSERT INTO exercises (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id, chapter_id) VALUES
    ('Somme 1', 'Calcule : (+58) + 0', '+58', '0', '-58', '+116', 'A', 1, 101),
    ('Somme 2', 'Calcule : (-94) + (+94)', '-188', '+188', '0', '-94', 'C', 1, 101),
    ('Somme 3', 'Calcule : (-134) + (+134)', '+268', '0', '-268', '+134', 'B', 1, 101),
    ('Somme 4', 'Calcule : (-281) + 0', '0', '+281', '-281', '-562', 'C', 1, 101),
    ('Comparaison 1', 'Complète : -7 … 5', '-7 < 5', '-7 > 5', '-7 = 5', 'Impossible', 'A', 1, 102),
    ('Comparaison 2', 'Complète : 0 … -3', '0 < -3', '0 > -3', '0 = -3', 'Impossible', 'B', 1, 102),
    ('Comparaison 3', 'Complète : -12 … -18', '-12 < -18', '-12 > -18', '-12 = -18', 'Impossible', 'B', 1, 102),
    ('Comparaison 4', 'Complète : 36 … -36', '36 < -36', '36 = -36', '36 > -36', 'Impossible', 'C', 1, 102),
    ('Droite graduée 1', 'Sur une droite graduée, A(2), B(-3), C(-5) et D(-1). Quel point est le plus à gauche ?', 'A', 'B', 'C', 'D', 'C', 1, 103),
    ('Droite graduée 2', 'Avec A(2), B(-3), C(-5) et D(-1), quel est l’ordre de gauche à droite ?', 'C, B, D, A', 'A, D, B, C', 'C, D, B, A', 'B, C, D, A', 'A', 1, 103),
    ('Droite graduée 3', 'Quelle est la distance entre C(-5) et D(-1) ?', '3 unités', '4 unités', '5 unités', '6 unités', 'B', 1, 103),
    ('Droite graduée 4', 'Que représente B(-3) pour le segment [CD], avec C(-5) et D(-1) ?', 'Une extrémité', 'Le milieu', 'Un point extérieur', 'L’origine', 'B', 1, 103),
    ('Somme 5', 'Calcule : (+124) + (-124)', '+248', '-248', '0', '+124', 'C', 1, 101),
    ('Somme 6', 'Calcule : (+211) + 0', '0', '-211', '+211', '+422', 'C', 1, 101),
    ('Somme 7', 'Calcule : 0 + (-114)', '+114', '-114', '0', '-228', 'B', 1, 101),
    ('Somme 8', 'Calcule : (+9) + 0', '-9', '0', '+18', '+9', 'D', 1, 101),
    ('Somme 9', 'Calcule : (-2) + (+2)', '-4', '0', '+4', '-2', 'B', 1, 101),
    ('Somme 10', 'Calcule : (+8) + (-8)', '+16', '-16', '0', '+8', 'C', 1, 101),
    ('Comparaison 5', 'Complète : 4 … -14', '4 < -14', '4 > -14', '4 = -14', 'Impossible', 'B', 1, 102),
    ('Comparaison 6', 'Complète : -2 … 2', '-2 > 2', '-2 = 2', '-2 < 2', 'Impossible', 'C', 1, 102),
    ('Comparaison 7', 'Complète : -24 … -23', '-24 < -23', '-24 > -23', '-24 = -23', 'Impossible', 'A', 1, 102),
    ('Comparaison 8', 'Complète : -61 … 16', '-61 > 16', '-61 < 16', '-61 = 16', 'Impossible', 'B', 1, 102),
    ('Comparaison 9', 'Complète : +9 … 0', '+9 < 0', '+9 = 0', '+9 > 0', 'Impossible', 'C', 1, 102),
    ('Comparaison 10', 'Complète : -5 … -5', '-5 < -5', '-5 > -5', '-5 = -5', 'Impossible', 'C', 1, 102),
    ('Droite graduée 5', 'Avec A(2), B(-3), C(-5) et D(-1), quel point est le plus à droite ?', 'A', 'B', 'C', 'D', 'A', 1, 103),
    ('Droite graduée 6', 'Quelle est l’abscisse du point B ?', '2', '-3', '-5', '-1', 'B', 1, 103),
    ('Droite graduée 7', 'Quelle est la distance entre C(-5) et B(-3) ?', '1 unité', '2 unités', '3 unités', '8 unités', 'B', 1, 103),
    ('Droite graduée 8', 'Quel point est le plus proche de l’origine O ?', 'A(2)', 'B(-3)', 'C(-5)', 'D(-1)', 'D', 1, 103),
    ('Droite graduée 9', 'Entre quels points se trouve l’origine O ?', 'C et B', 'B et D', 'D et A', 'C et A uniquement', 'C', 1, 103),
    ('Droite graduée 10', 'Quelle est la distance entre B(-3) et A(2) ?', '1 unité', '3 unités', '5 unités', '6 unités', 'C', 1, 103),
    ('Physics quiz', 'What is gravity?', 'A. Magic', 'B. Force', 'C. Light', 'D. Sound', 'B', 2, NULL),
    ('Chemistry quiz', 'H2O is?', 'A. Hydrogen', 'B. Water', 'C. Oxygen', 'D. Salt', 'B', 3, NULL),
    ('Math quiz 3', 'What is 5*5?', 'A. 10', 'B. 20', 'C. 25', 'D. 30', 'C', 5, NULL);

INSERT INTO users (id, phone, password_hash) VALUES
    ('user-1', '31406022', 'pbkdf2_sha256$100000$da6c8bfec34cfa069d1a9bfbcbd52fd2$104b1c7899383b5da8261612ee53cbfc365bac3ea380dd5c0348a379fe33f5d2'),
    ('user-2', '31406024', 'pbkdf2_sha256$210000$102132435465768798a9bacbdcedfe0f$26d436d3bf132d16f7fbb6f32614e8029ae3d0a3bdee923fdcd937eda97de6c8'),
    ('admin-user', '31406023', 'pbkdf2_sha256$210000$2031425364758697a8b9cadbecfd0e1f$2e4b9edf873859ee1aabcaf549a2111e9625d6eda9316bcbb8d1b7b362f05901');

INSERT INTO profiles (id, name, phone, role, class_id) VALUES
    ('user-1', 'المشرف العام', '31406022', 'admin', NULL),
    ('user-2', 'Student Two', '31406024', 'student', 1),
    ('admin-user', 'Admin User', '31406023', 'admin', NULL);
