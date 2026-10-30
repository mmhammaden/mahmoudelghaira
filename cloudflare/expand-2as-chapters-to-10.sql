INSERT INTO exercises
    (title, question, option_a, option_b, option_c, option_d, correct_answer, class_id, chapter_id)
VALUES
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
    ('Droite graduée 10', 'Quelle est la distance entre B(-3) et A(2) ?', '1 unité', '3 unités', '5 unités', '6 unités', 'C', 1, 103);
