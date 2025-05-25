CREATE TABLE Application (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    FIO VARCHAR(150) NOT NULL,
    Phone_number VARCHAR(15) NOT NULL,
    Email VARCHAR(255) NOT NULL,
    Birth_day DATE NOT NULL,
    Gender ENUM('male', 'female') NOT NULL,
    Biography TEXT NOT NULL,
    Contract_accepted BOOLEAN NOT NULL,
    Created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE Programming_Languages (
    Language_ID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(50) UNIQUE NOT NULL
);

INSERT INTO Programming_Languages (Name) VALUES 
('Pascal'), ('C'), ('C++'), ('JavaScript'), ('PHP'),
('Python'), ('Java'), ('Haskell'), ('Clojure'),
('Prolog'), ('Scala'), ('Go');

CREATE TABLE Application_Languages (
    Application_ID INT NOT NULL,
    Language_ID INT NOT NULL,
    FOREIGN KEY (Application_ID) REFERENCES Application(ID) ON DELETE CASCADE,
    FOREIGN KEY (Language_ID) REFERENCES Programming_Languages(Language_ID),
    PRIMARY KEY (Application_ID, Language_ID)
);

CREATE TABLE Users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);