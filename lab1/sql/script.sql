
CREATE DATABASE my_database;
USE my_database;


CREATE TABLE author (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE book (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    author_id INT,
    FOREIGN KEY (author_id) REFERENCES author(id) ON DELETE CASCADE
);

CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(200) NOT NULL
);

-- Использование базы данных
USE my_database;

-- Вставка данных в таблицу author
INSERT INTO author (name) VALUES
('J.K. Rowling'),
('George R.R. Martin'),
('J.R.R. Tolkien');

-- Вставка данных в таблицу book
INSERT INTO book (title, author_id) VALUES
('Harry Potter and the Philosopher\'s Stone', 1),
('A Game of Thrones', 2),
('The Hobbit', 3);