CREATE DATABASE arbitros_extremadura;

USE arbitros_extremadura;

CREATE TABLE arbitros (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(50),
  apellidos VARCHAR(50),
  email VARCHAR(100),
  password VARCHAR(255)
);

CREATE TABLE disponibilidad (
  id INT PRIMARY KEY AUTO_INCREMENT,
  arbitro_id INT,
  semana DATE,
  disponible TINYINT(1),
  FOREIGN KEY (arbitro_id) REFERENCES arbitros(id)
);

CREATE TABLE partidos (
  id INT PRIMARY KEY AUTO_INCREMENT,
  fecha DATE,
  equipo_local VARCHAR(50),
  equipo_visitante VARCHAR(50),
  arbitro_id INT,
  resultado VARCHAR(50),
  FOREIGN KEY (arbitro_id) REFERENCES arbitros(id)
);