CREATE DATABASE IF NOT EXISTS api_wa_fonnte
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE api_wa_fonnte;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    no_hp VARCHAR(20) NOT NULL,
    alamat VARCHAR(500) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    UNIQUE KEY uq_users_no_hp (no_hp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS log_whatsapp (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    no_tujuan VARCHAR(20) NOT NULL,
    pesan TEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    response LONGTEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_whatsapp_user_id (user_id),
    CONSTRAINT fk_log_whatsapp_user
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
