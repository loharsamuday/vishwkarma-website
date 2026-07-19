USE Vishwkarma;
INSERT IGNORE INTO religions (id, name) VALUES (1, 'Hindu');
INSERT IGNORE INTO castes (id, religion_id, name) VALUES (1, 1, 'Vishwakarma'), (2, 1, 'Lohar'), (3, 1, 'Sutar'), (4, 1, 'Sonar'), (5, 1, 'Kasar');
