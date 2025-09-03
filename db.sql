DROP TABLE IF EXISTS spreadly_love;

DROP TABLE IF EXISTS spreadly_campaign_admin;

DROP TABLE IF EXISTS spreadly_campaign_user;

DROP TABLE IF EXISTS spreadly_user;

DROP TABLE IF EXISTS spreadly_campaign;

CREATE TABLE spreadly_campaign (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    theme VARCHAR(50) DEFAULT 'christmas',
    mail_subject VARCHAR(255) DEFAULT 'Your love messages',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_slug (slug),
    INDEX idx_active (is_active)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE spreadly_user (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    login_code VARCHAR(6) NULL,
    login_code_expires DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_email (email)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE spreadly_campaign_user (
    id INT NOT NULL AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    receiver BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_campaign_user (campaign_id, user_id),
    KEY fk_campaign_user_campaign (campaign_id),
    KEY fk_campaign_user_user (user_id),
    CONSTRAINT fk_campaign_user_campaign FOREIGN KEY (campaign_id) REFERENCES spreadly_campaign (id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_user_user FOREIGN KEY (user_id) REFERENCES spreadly_user (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE spreadly_campaign_admin (
    id INT NOT NULL AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_campaign_user (campaign_id, user_id),
    KEY fk_campaign_admin_campaign (campaign_id),
    KEY fk_campaign_admin_user (user_id),
    CONSTRAINT fk_campaign_admin_campaign FOREIGN KEY (campaign_id) REFERENCES spreadly_campaign (id) ON DELETE CASCADE,
    CONSTRAINT fk_campaign_admin_user FOREIGN KEY (user_id) REFERENCES spreadly_user (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

CREATE TABLE spreadly_love (
    id INT NOT NULL AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    id_receiver INT NOT NULL,
    id_sender INT NULL,
    sender VARCHAR(100) NOT NULL,
    content text NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_love_campaign (campaign_id),
    KEY fk_love_receiver (id_receiver),
    KEY fk_love_sender (id_sender),
    CONSTRAINT fk_love_campaign FOREIGN KEY (campaign_id) REFERENCES spreadly_campaign (id) ON DELETE CASCADE,
    CONSTRAINT fk_love_receiver FOREIGN KEY (id_receiver) REFERENCES spreadly_user (id) ON DELETE CASCADE,
    CONSTRAINT fk_love_sender FOREIGN KEY (id_sender) REFERENCES spreadly_user (id) ON DELETE SET NULL
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;