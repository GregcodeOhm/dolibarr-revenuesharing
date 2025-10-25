CREATE TABLE llx_revenuesharing_collaborator (
    rowid INTEGER PRIMARY KEY AUTO_INCREMENT,
    fk_user INTEGER NOT NULL,
    label VARCHAR(255),
    default_percentage DECIMAL(5,2) DEFAULT 60.00,
    cost_per_session DECIMAL(10,2) DEFAULT 0,
    active TINYINT DEFAULT 1,
    note_private TEXT,
    note_public TEXT,
    date_creation DATETIME,
    date_modification DATETIME,
    fk_user_creat INTEGER,
    fk_user_modif INTEGER,
    INDEX idx_fk_user (fk_user),
    FOREIGN KEY (fk_user) REFERENCES llx_user(rowid)
) ENGINE=InnoDB;
