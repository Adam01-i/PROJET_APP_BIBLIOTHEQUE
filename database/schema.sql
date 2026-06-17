CREATE TABLE books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    genre VARCHAR(100) NOT NULL DEFAULT 'Non classé',
    year INT NOT NULL,
    available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO books (title, author, genre, year, available) VALUES
('Le Petit Prince','Antoine de Saint-Exupéry','Roman',1943,1),
('1984','George Orwell','Science-fiction',1949,1),
('Les Misérables','Victor Hugo','Roman historique',1862,0),
('L''Étranger','Albert Camus','Roman',1942,1),
('Harry Potter à l''école des sorciers','J.K. Rowling','Fantasy',1997,1),
('Dune','Frank Herbert','Science-fiction',1965,0),
('Le Seigneur des Anneaux','J.R.R. Tolkien','Fantasy',1954,1),
('Crime et Châtiment','Fiodor Dostoïevski','Roman',1866,1),
('Le Meilleur des mondes','Aldous Huxley','Science-fiction',1932,0),
('Cent ans de solitude','Gabriel García Márquez','Réalisme magique',1967,1);
