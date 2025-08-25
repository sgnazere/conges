-- Création de la table admin
CREATE TABLE IF NOT EXISTS admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prenoms VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM('superadmin', 'admin', 'user') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Création de la table employees
CREATE TABLE IF NOT EXISTS employees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenoms VARCHAR(100) NOT NULL,
    sexe ENUM('Homme', 'Femme') NOT NULL,
    adresse VARCHAR(255),
    email VARCHAR(100),
    date_naissance DATE NOT NULL,
    poste VARCHAR(100) NOT NULL,
    projet VARCHAR(100),
    date_embauche DATE NOT NULL,
    telephone VARCHAR(20) NOT NULL,
    numero_cnps VARCHAR(12) NOT NULL,
    type_contrat ENUM('CDI', 'CDD', 'Stage', 'Prestation') NOT NULL,
    numero_urgence VARCHAR(20),
    status ENUM('actif', 'inactif') DEFAULT 'actif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Création de la table conges
CREATE TABLE IF NOT EXISTS conges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    type_conge ENUM('Annuel', 'Maladie', 'Maternité', 'Paternité', 'Formation', 'Autre') NOT NULL,
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    nombre_jours INT NOT NULL,
    motif TEXT,
    statut ENUM('En attente', 'Approuvé', 'Refusé', 'En cours', 'Terminé') DEFAULT 'En attente',
    approuve_par INT,
    date_demande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    date_approbation TIMESTAMP NULL,
    commentaire_admin TEXT,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (approuve_par) REFERENCES admin(id) ON DELETE SET NULL
);

-- Création de la table license
CREATE TABLE IF NOT EXISTS license (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(255) UNIQUE NOT NULL,
    domain VARCHAR(255) NOT NULL,
    status ENUM('active', 'expired', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL
);

-- Insertion d'un administrateur par défaut (mot de passe: admin123)
INSERT INTO admin (username, password, nom, prenoms, email, role) VALUES 
('admin', '$2y$10$3GX3lYs0f/iSttZhUNYf1.3026KnlSv18x2TNkj2N2thSxjcHnwjG', 'Administrateur', 'Principal', 'admin@example.com', 'superadmin')
ON DUPLICATE KEY UPDATE id=id;

-- Création d'index pour améliorer les performances
CREATE INDEX idx_employees_status ON employees(status);
CREATE INDEX idx_employees_projet ON employees(projet);
CREATE INDEX idx_conges_employee_id ON conges(employee_id);
CREATE INDEX idx_conges_statut ON conges(statut);
CREATE INDEX idx_conges_date_debut ON conges(date_debut);

